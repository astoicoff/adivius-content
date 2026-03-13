<?php
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];
$id      = $_GET['id'] ?? '';

if (!$id) { http_response_code(400); echo json_encode(['detail' => 'Generation ID is required.']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $res = supabase_call('DELETE',
        '/rest/v1/content_generations?id=eq.' . urlencode($id) . '&user_id=eq.' . urlencode($user_id)
    );
    if ($res['status'] >= 400) { http_response_code($res['status']); echo $res['body']; exit; }
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $body    = json_decode(file_get_contents('php://input'), true) ?: [];
    $content = $body['content'] ?? null;
    if ($content === null) { http_response_code(400); echo json_encode(['detail' => 'content is required.']); exit; }

    $res = supabase_call('PATCH',
        '/rest/v1/content_generations?id=eq.' . urlencode($id) . '&user_id=eq.' . urlencode($user_id),
        ['content' => $content],
        ['Prefer: return=representation']
    );
    if ($res['status'] >= 400) { http_response_code($res['status']); echo $res['body']; exit; }
    echo json_encode(['success' => true]);
    exit;
}

$res  = supabase_call('GET',
    '/rest/v1/content_generations?id=eq.' . urlencode($id)
    . '&user_id=eq.' . urlencode($user_id)
    . '&select=id,keyword,status,content,created_at,group_id'
);
$data = json_decode($res['body'], true);
if (empty($data)) { http_response_code(404); echo json_encode(['detail' => 'Generation not found.']); exit; }

echo json_encode($data[0]);
