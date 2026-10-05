<?php
ob_start();
require_once __DIR__ . '/../helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); ob_end_clean();
    echo json_encode(['detail' => 'Method not allowed.']); exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$gen_id = trim($body['generation_id'] ?? '');
// ?dry_run=1 returns the exact body that would be sent (and any contract
// problems) without sending it or changing any state. Same access rules.
$dry_run = !empty($_GET['dry_run']);
if (!$gen_id) {
    http_response_code(400); ob_end_clean();
    echo json_encode(['detail' => 'generation_id is required.']); exit;
}

// Load generation
$genRes  = supabase_call('GET',
    '/rest/v1/content_generations?id=eq.' . urlencode($gen_id)
    . '&select=id,keyword,content,client_id,site_id,group_id,user_id,status,handed_off_at'
);
$genData = json_decode($genRes['body'], true);
if (empty($genData)) {
    http_response_code(404); ob_end_clean();
    echo json_encode(['detail' => 'Generation not found.']); exit;
}
$gen = $genData[0];

// Access: owner or moderator+
if ($gen['user_id'] !== $user_id && !check_group_access($user_id, $gen['group_id'] ?? '', 'moderator')) {
    http_response_code(403); ob_end_clean();
    echo json_encode(['detail' => 'Access denied.']); exit;
}

// handed_off_at means "a handoff is awaiting review at Nucleus right now".
// It clears on publish success, publish failure, and return — so completed
// pieces re-send after a return, and published pieces re-send to update the
// live post (Nucleus PUTs to the stored WP post id on republish).
if (!empty($gen['handed_off_at']) && !$dry_run) {
    http_response_code(409); ob_end_clean();
    echo json_encode(['detail' => 'Already at Nucleus awaiting review (sent ' . $gen['handed_off_at'] . '). Edit it there, or wait for it to publish or be returned.']); exit;
}

if ((!NUCLEUS_BASE_URL || !NUCLEUS_SERVICE_TOKEN) && !$dry_run) {
    http_response_code(503); ob_end_clean();
    echo json_encode(['detail' => 'Nucleus integration is not configured on this server.']); exit;
}

// The group supplies the publishing fields (pillar, author) and, for a
// generation created before its group was linked, the site.
$group = [];
if (!empty($gen['group_id'])) {
    $grpRes = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($gen['group_id']) . '&select=site_id,client_id,pillar,author_slug');
    $group  = json_decode($grpRes['body'], true)[0] ?? [];
}

// A publishable piece needs a site (routes the article). Nucleus's
// contract v1 (post the §4.1 update) accepts site-only handoffs — no
// client required. Fall back to the group's current site (and its client)
// if the generation was created before a site was set.
if (empty($gen['site_id'])) {
    $gen['site_id']   = $group['site_id']   ?? null;
    $gen['client_id'] = $group['client_id'] ?? null;
}

if (empty($gen['site_id']) && !$dry_run) {
    http_response_code(400); ob_end_clean();
    echo json_encode(['detail' => 'This group has no Nucleus site set. Open the content group and pick a site in the Nucleus panel.']); exit;
}

// The full content-ready body: every HUB_CONTENT_READY_SPEC key present,
// em dashes replaced (adivius.com house rule). A shape problem is logged,
// never blocking — Nucleus validates too, and still accepts the old body.
// site_id routes the article; Nucleus verifies it belongs to our workspace
// and hands it to the site's adapter.
$payload  = build_content_ready_payload($gen, $group);
$problems = hub_validate_shape($payload, HUB_CONTENT_READY_SPEC, 'content-ready');
if ($problems) error_log('[handoff ' . $gen['id'] . '] content-ready shape: ' . implode('; ', $problems));

if ($dry_run) {
    ob_end_clean();
    echo json_encode(['dry_run' => true, 'payload' => $payload, 'problems' => $problems]);
    exit;
}

$ch = curl_init(rtrim(NUCLEUS_BASE_URL, '/') . '/api/inbound/content-ready');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . NUCLEUS_SERVICE_TOKEN,
        'X-Nucleus-Tool: ' . NUCLEUS_TOOL_SLUG,
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$respBody = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(502); ob_end_clean();
    echo json_encode(['detail' => 'Could not reach Nucleus: ' . $curlErr]); exit;
}

$resp = json_decode($respBody, true) ?: [];

if ($httpCode === 409) {
    http_response_code(409); ob_end_clean();
    echo json_encode(['detail' => $resp['error'] ?? 'No publishable site is mapped to this client.']); exit;
}
if ($httpCode === 400) {
    http_response_code(400); ob_end_clean();
    echo json_encode(['detail' => 'Nucleus rejected the payload: ' . ($resp['error'] ?? $respBody)]); exit;
}
if ($httpCode === 401) {
    http_response_code(401); ob_end_clean();
    echo json_encode(['detail' => 'Nucleus auth failure — check NUCLEUS_SERVICE_TOKEN and NUCLEUS_TOOL_SLUG.']); exit;
}
if ($httpCode !== 202) {
    http_response_code(502); ob_end_clean();
    echo json_encode(['detail' => 'Nucleus returned HTTP ' . $httpCode . ': ' . ($resp['error'] ?? $respBody)]); exit;
}

$queue_id     = $resp['queue_id']             ?? null;
$resolved_id  = $resp['resolved_site_id']     ?? null;
$resolved_dom = $resp['resolved_site_domain'] ?? null;

// Record handoff (site fields populated when Nucleus's contract >= v1).
// Prior-cycle state is cleared — a re-send after a failed publish or a
// Nucleus return starts fresh (banner and error disappear).
supabase_call('PATCH',
    '/rest/v1/content_generations?id=eq.' . urlencode($gen_id),
    [
        'handed_off_at'                 => date('c'),
        'nucleus_queue_id'              => $queue_id,
        'nucleus_resolved_site_id'      => $resolved_id,
        'nucleus_resolved_site_domain'  => $resolved_dom,
        'nucleus_publish_error'         => null,
        'nucleus_returned_at'           => null,
        'nucleus_return_note'           => null,
    ]
);

ob_end_clean();
echo json_encode([
    'ok'                    => true,
    'queue_id'              => $queue_id,
    'resolved_site_id'      => $resolved_id,
    'resolved_site_domain'  => $resolved_dom,
]);
