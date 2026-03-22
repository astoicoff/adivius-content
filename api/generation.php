<?php
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];
$id      = $_GET['id'] ?? '';

if (!$id) { http_response_code(400); echo json_encode(['detail' => 'Generation ID is required.']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Duplicate: copy keyword, group_id, content into a new completed generation
    $src     = supabase_call('GET',
        '/rest/v1/content_generations?id=eq.' . urlencode($id)
        . '&user_id=eq.' . urlencode($user_id)
        . '&select=keyword,group_id,content'
    );
    $srcData = json_decode($src['body'], true);
    if (empty($srcData)) { http_response_code(404); echo json_encode(['detail' => 'Generation not found.']); exit; }
    $s = $srcData[0];

    $res = supabase_call('POST', '/rest/v1/content_generations', [
        'user_id'  => $user_id,
        'keyword'  => $s['keyword'],
        'group_id' => $s['group_id'] ?? null,
        'content'  => $s['content']  ?? null,
        'status'   => 'completed',
    ], ['Prefer: return=representation']);
    if ($res['status'] >= 400) { http_response_code(500); echo $res['body']; exit; }
    $newRow = json_decode($res['body'], true);
    echo json_encode(['id' => $newRow[0]['id']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $res = supabase_call('DELETE',
        '/rest/v1/content_generations?id=eq.' . urlencode($id) . '&user_id=eq.' . urlencode($user_id)
    );
    if ($res['status'] >= 400) { http_response_code($res['status']); echo $res['body']; exit; }
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $body         = json_decode(file_get_contents('php://input'), true) ?: [];
    $content      = $body['content']      ?? null;
    $instructions = $body['instructions'] ?? null;
    if ($content === null && $instructions === null) { http_response_code(400); echo json_encode(['detail' => 'content or instructions is required.']); exit; }

    $patch = [];
    if ($content      !== null) $patch['content']      = $content;
    if ($instructions !== null) $patch['instructions'] = $instructions;

    $res = supabase_call('PATCH',
        '/rest/v1/content_generations?id=eq.' . urlencode($id) . '&user_id=eq.' . urlencode($user_id),
        $patch,
        ['Prefer: return=representation']
    );
    if ($res['status'] >= 400) { http_response_code($res['status']); echo $res['body']; exit; }
    echo json_encode(['success' => true]);
    exit;
}

$res  = supabase_call('GET',
    '/rest/v1/content_generations?id=eq.' . urlencode($id)
    . '&user_id=eq.' . urlencode($user_id)
    . '&select=id,keyword,status,content,instructions,serpapi_raw,created_at,group_id,wp_post_url'
);
$data = json_decode($res['body'], true);
if (empty($data)) { http_response_code(404); echo json_encode(['detail' => 'Generation not found.']); exit; }

echo json_encode($data[0]);
