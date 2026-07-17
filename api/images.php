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
        $res  = supabase_call('GET',
            '/rest/v1/image_generations?user_id=eq.' . urlencode($user_id)
            . '&select=id,keyword,image_url,size,quality,model,status,group_id,created_at,updated_at'
            . '&order=created_at.desc&limit=50'
        );
        $data = json_decode($res['body'], true);
        echo json_encode(['images' => $data ?: []]);
    }

} elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) { http_response_code(400); echo json_encode(['detail' => 'Image ID is required.']); exit; }

    $check = supabase_call('GET', '/rest/v1/image_generations?id=eq.' . urlencode($id) . '&user_id=eq.' . urlencode($user_id) . '&select=id');
    if (empty(json_decode($check['body'], true))) {
        http_response_code(404); echo json_encode(['detail' => 'Image not found.']); exit;
    }

    // Remove from Supabase Storage (non-fatal — DB row is deleted regardless)
    $storage_path = $user_id . '/' . $id . '.jpg';
    $ch = curl_init(SUPABASE_URL . '/storage/v1/object/generated-images/' . $storage_path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . SUPABASE_SERVICE_KEY],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    curl_exec($ch);
    curl_close($ch);

    supabase_call('DELETE', '/rest/v1/image_generations?id=eq.' . urlencode($id));
    echo json_encode(['status' => 'deleted']);

} else {
    http_response_code(405); echo json_encode(['detail' => 'Method not allowed.']);
}
