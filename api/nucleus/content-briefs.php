<?php
require_once __DIR__ . '/../helpers.php';
header('Content-Type: application/json');

// Machine-to-machine: service token auth, no user session.
// Caller must send Authorization: Bearer <NUCLEUS_SERVICE_TOKEN>
// and X-Nucleus-Tool: nucleus (identifies Nucleus as the calling tool).
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$tool = $_SERVER['HTTP_X_NUCLEUS_TOOL'] ?? '';
if (function_exists('getallheaders')) {
    $h    = getallheaders();
    $auth = $auth ?: ($h['Authorization']    ?? $h['authorization']    ?? '');
    $tool = $tool ?: ($h['X-Nucleus-Tool']   ?? $h['x-nucleus-tool']   ?? '');
}

if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)
    || !NUCLEUS_SERVICE_TOKEN
    || !hash_equals(NUCLEUS_SERVICE_TOKEN, $m[1])
    || $tool !== 'nucleus') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'reason' => 'method_not_allowed']);
    exit;
}

$body         = json_decode(file_get_contents('php://input'), true) ?: [];
$site_id      = trim($body['site_id']      ?? '');
$client_id    = trim($body['client_id']    ?? '');
$target_query = trim($body['target_query'] ?? '');
$outline_md   = trim($body['outline_md']   ?? '');
$title        = trim($body['title']        ?? '');
$intent       = trim($body['intent']       ?? '');
$target_wc    = isset($body['target_word_count']) ? (int)$body['target_word_count'] : null;
$links        = is_array($body['internal_links'] ?? null) ? $body['internal_links'] : [];
$src_ai_id    = trim($body['source_ai_output_id'] ?? '');

if (!$site_id) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'reason' => 'missing_field', 'field' => 'site_id']);
    exit;
}
if (!$target_query) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'reason' => 'missing_field', 'field' => 'target_query']);
    exit;
}

// Resolve the content group registered for this site. The group gives us the
// user_id (owner) and group_id the new row belongs to.
$grpRes  = supabase_call('GET',
    '/rest/v1/content_groups?site_id=eq.' . urlencode($site_id)
    . '&select=id,user_id,client_id'
    . '&order=created_at.desc&limit=1'
);
$grpData = json_decode($grpRes['body'], true);
$grp     = !empty($grpData) ? $grpData[0] : null;

if (!$grp) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'reason' => 'unknown_site']);
    exit;
}

$row = [
    'user_id'  => $grp['user_id'],
    'group_id' => $grp['id'],
    'site_id'  => $site_id,
    'keyword'  => $target_query,
    'status'   => 'pending',
];
if ($outline_md)          $row['content']   = $outline_md;
if ($client_id)           $row['client_id'] = $client_id;
elseif ($grp['client_id']) $row['client_id'] = $grp['client_id'];

$brief_meta = [];
if ($title)                  $brief_meta['title']               = $title;
if ($intent)                 $brief_meta['intent']              = $intent;
if ($target_wc > 0)          $brief_meta['target_word_count']   = $target_wc;
if (!empty($links))          $brief_meta['internal_links']      = array_slice($links, 0, 10);
if ($src_ai_id)              $brief_meta['source_ai_output_id'] = $src_ai_id;
if (!empty($brief_meta))     $row['nucleus_brief']              = $brief_meta;

$res  = supabase_call('POST', '/rest/v1/content_generations', $row, ['Prefer: return=representation']);
$data = json_decode($res['body'], true);
$id   = $data[0]['id'] ?? null;

if (!$id || $res['status'] >= 400) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'storage_error']);
    exit;
}

http_response_code(202);
echo json_encode(['ok' => true, 'content_id' => $id]);
