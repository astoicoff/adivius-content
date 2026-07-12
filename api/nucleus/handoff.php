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
if (!empty($gen['handed_off_at'])) {
    http_response_code(409); ob_end_clean();
    echo json_encode(['detail' => 'Already at Nucleus awaiting review (sent ' . $gen['handed_off_at'] . '). Edit it there, or wait for it to publish or be returned.']); exit;
}

if (!NUCLEUS_BASE_URL || !NUCLEUS_SERVICE_TOKEN) {
    http_response_code(503); ob_end_clean();
    echo json_encode(['detail' => 'Nucleus integration is not configured on this server.']); exit;
}

// A publishable piece needs a site (routes the article). Nucleus's
// contract v1 (post the §4.1 update) accepts site-only handoffs — no
// client required. Fall back to the group's current site_id if the
// generation was created before a site was set.
if (empty($gen['site_id'])) {
    $grpRes  = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($gen['group_id'] ?? '') . '&select=site_id');
    $grpData = json_decode($grpRes['body'], true);
    $gen['site_id'] = $grpData[0]['site_id'] ?? null;
}

if (empty($gen['site_id'])) {
    http_response_code(400); ob_end_clean();
    echo json_encode(['detail' => 'This group has no Nucleus site set. Open the content group and pick a site in the Nucleus panel.']); exit;
}

// Parse meta prefix from content (same logic as seo-apply.php)
$raw      = $gen['content'] ?? '';
$lines    = explode("\n", $raw);
$meta     = [];
$bodyStart = 0;
foreach ($lines as $i => $line) {
    $trim = trim($line);
    if (!$trim) { if (!empty($meta)) { $bodyStart = $i + 1; break; } continue; }
    if (isset($trim[0]) && $trim[0] === '<') { $bodyStart = $i; break; }
    if (preg_match('/^(h1|title|url)\s*:\s*(.+)$/i', $trim, $m)) {
        $meta[strtolower($m[1])] = trim($m[2]);
        $bodyStart = $i + 1;
    } else break;
}
$body_html = trim(implode("\n", array_slice($lines, $bodyStart)));
$title     = $meta['title'] ?? $meta['h1'] ?? $gen['keyword'];
$slug      = $meta['url'] ?? '';

// POST to Nucleus inbound endpoint. site_id routes the article; Nucleus
// verifies it belongs to our workspace and hands it to the site's adapter.
$payload = [
    'site_id'    => $gen['site_id'],
    'title'      => $title,
    'body_html'  => $body_html,
    'source_ref' => $gen['id'],
];
if ($slug) $payload['slug'] = $slug;

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
