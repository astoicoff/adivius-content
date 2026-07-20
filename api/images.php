<?php
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];
$method  = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {

    if (isset($_GET['id'])) {
        $id   = $_GET['id'];
        $res  = supabase_call('GET', '/rest/v1/image_generations?id=eq.' . urlencode($id) . '&user_id=eq.' . urlencode($user_id) . '&select=*');
        $data = json_decode($res['body'], true);
        if (empty($data)) { http_response_code(404); echo json_encode(['detail' => 'Image not found.']); exit; }
        echo json_encode($data[0]);

    } elseif (isset($_GET['group_id'])) {
        $group_id = $_GET['group_id'];
        if (!check_group_access($user_id, $group_id)) {
            http_response_code(403); echo json_encode(['detail' => 'Group not found or insufficient permissions.']); exit;
        }
        $res  = supabase_call('GET',
            '/rest/v1/image_generations?group_id=eq.' . urlencode($group_id)
            . '&select=id,keyword,image_url,size,quality,model,status,created_at,updated_at'
            . '&order=created_at.desc'
        );
        $data = json_decode($res['body'], true);
        echo json_encode(['images' => $data ?: []]);

    } else {
        $limit = min(200, max(1, intval($_GET['limit'] ?? 100)));
        $res  = supabase_call('GET',
            '/rest/v1/image_generations?user_id=eq.' . urlencode($user_id)
            . '&select=id,keyword,image_url,size,quality,model,status,group_id,created_at,updated_at'
            . '&order=created_at.desc&limit=' . $limit
        );
        $data = json_decode($res['body'], true);
        echo json_encode(['images' => $data ?: [], 'limit' => $limit]);
    }

} elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) { http_response_code(400); echo json_encode(['detail' => 'Image ID is required.']); exit; }

    // Fetch full row so we can delete the main image + all versions from Storage
    $check = supabase_call('GET',
        '/rest/v1/image_generations?id=eq.' . urlencode($id)
        . '&user_id=eq.' . urlencode($user_id)
        . '&select=id,image_url,image_versions'
    );
    $rows = json_decode($check['body'], true);
    if (empty($rows)) {
        http_response_code(404); echo json_encode(['detail' => 'Image not found.']); exit;
    }
    $row = $rows[0];

    // Extracts the storage-relative path from a public URL and issues a DELETE
    // (non-fatal — DB row is deleted regardless of Storage outcome)
    $storage_prefix = SUPABASE_URL . '/storage/v1/object/public/generated-images/';
    $delete_by_url  = function ($url) use ($storage_prefix) {
        if (!$url || strpos($url, $storage_prefix) !== 0) return;
        $path = substr($url, strlen($storage_prefix));
        $ch   = curl_init(SUPABASE_URL . '/storage/v1/object/generated-images/' . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HTTPHEADER     => [
                'apikey: ' . SUPABASE_SERVICE_KEY,
                'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        curl_exec($ch);
        curl_close($ch);
    };

    $delete_by_url($row['image_url'] ?? '');
    $versions = is_array($row['image_versions']) ? $row['image_versions'] : [];
    foreach ($versions as $v) {
        $delete_by_url($v['url'] ?? '');
    }

    supabase_call('DELETE', '/rest/v1/image_generations?id=eq.' . urlencode($id));
    echo json_encode(['status' => 'deleted']);

} elseif ($method === 'PATCH') {
    $id = $_GET['id'] ?? '';
    if (!$id) { http_response_code(400); echo json_encode(['detail' => 'Image ID is required.']); exit; }

    $body   = json_decode(file_get_contents('php://input'), true);
    $prompt = trim($body['prompt'] ?? '');
    if (!$prompt) { http_response_code(400); echo json_encode(['detail' => 'Prompt is required.']); exit; }

    $check = supabase_call('GET', '/rest/v1/image_generations?id=eq.' . urlencode($id) . '&user_id=eq.' . urlencode($user_id) . '&select=id');
    if (empty(json_decode($check['body'], true))) {
        http_response_code(404); echo json_encode(['detail' => 'Image not found.']); exit;
    }

    supabase_call('PATCH', '/rest/v1/image_generations?id=eq.' . urlencode($id), [
        'prompt'     => $prompt,
        'updated_at' => date('c'),
    ]);

    echo json_encode(['status' => 'saved', 'prompt' => $prompt]);

} else {
    http_response_code(405); echo json_encode(['detail' => 'Method not allowed.']);
}
