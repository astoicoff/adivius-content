<?php
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];
$gen_id  = $_GET['generation_id'] ?? '';

if (!$gen_id) { http_response_code(400); echo json_encode(['detail' => 'generation_id required.']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true) ?: [];
    $content = $body['content'] ?? '';
    if (!$content) { http_response_code(400); echo json_encode(['detail' => 'content required.']); exit; }
    $check = supabase_call('GET', '/rest/v1/content_generations?id=eq.' . urlencode($gen_id) . '&user_id=eq.' . urlencode($user_id) . '&select=id');
    if (empty(json_decode($check['body'], true))) { http_response_code(404); echo json_encode(['detail' => 'Not found.']); exit; }
    supabase_call('POST', '/rest/v1/content_generation_versions', [
        'generation_id' => $gen_id,
        'user_id'       => $user_id,
        'content'       => $content,
    ]);
    echo json_encode(['success' => true]);
    exit;
}

$res  = supabase_call('GET',
    '/rest/v1/content_generation_versions?generation_id=eq.' . urlencode($gen_id)
    . '&user_id=eq.' . urlencode($user_id)
    . '&order=created_at.desc'
    . '&select=id,content,created_at'
);
$data = json_decode($res['body'], true);
echo json_encode(['versions' => $data ?: []]);
