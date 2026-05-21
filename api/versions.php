<?php
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];
$gen_id  = $_GET['generation_id'] ?? '';

if (!$gen_id) { http_response_code(400); echo json_encode(['detail' => 'generation_id required.']); exit; }

// Resolve generation + check access
function resolve_gen_access(string $gen_id, string $user_id, string $min_role = 'moderator'): bool {
    $res  = supabase_call('GET', '/rest/v1/content_generations?id=eq.' . urlencode($gen_id) . '&select=user_id,group_id');
    $data = json_decode($res['body'], true);
    if (empty($data)) return false;
    $gen = $data[0];
    if ($gen['user_id'] === $user_id) return true;
    $gid = $gen['group_id'] ?? '';
    return $gid && check_group_access($user_id, $gid, $min_role) !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true) ?: [];
    $content = $body['content'] ?? '';
    if (!$content) { http_response_code(400); echo json_encode(['detail' => 'content required.']); exit; }
    if (!resolve_gen_access($gen_id, $user_id, 'moderator')) { http_response_code(404); echo json_encode(['detail' => 'Not found.']); exit; }
    supabase_call('POST', '/rest/v1/content_generation_versions', [
        'generation_id' => $gen_id,
        'user_id'       => $user_id,
        'content'       => $content,
    ]);
    echo json_encode(['success' => true]);
    exit;
}

// GET — viewer+ access to see versions
if (!resolve_gen_access($gen_id, $user_id, 'viewer')) { http_response_code(404); echo json_encode(['detail' => 'Not found.']); exit; }

$res  = supabase_call('GET',
    '/rest/v1/content_generation_versions?generation_id=eq.' . urlencode($gen_id)
    . '&order=created_at.desc'
    . '&select=id,content,created_at'
);
$data = json_decode($res['body'], true);
echo json_encode(['versions' => $data ?: []]);
