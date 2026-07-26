<?php
require_once __DIR__ . '/helpers.php';
set_headers();

$user     = get_authed_user();
$user_id  = $user['id'];
$group_id = $_GET['group_id'] ?? '';
$method   = $_SERVER['REQUEST_METHOD'];

if (!$group_id) { http_response_code(400); echo json_encode(['detail' => 'group_id required.']); exit; }

$min_role = $method === 'GET' ? 'viewer' : 'moderator';
if (!check_group_access($user_id, $group_id, $min_role)) {
    http_response_code(404); echo json_encode(['detail' => 'Group not found.']); exit;
}

$valid_sizes = ['1792x1024', '1024x1024', '1024x1792', '1536x1024', '1024x1536'];
$valid_quals = ['standard', 'hd', 'low', 'medium', 'high'];

if ($method === 'GET') {
    $res = supabase_call('GET',
        '/rest/v1/image_agents?group_id=eq.' . urlencode($group_id)
        . '&select=id,name,instructions,size,quality,sort,created_at'
        . '&order=sort.asc,created_at.asc'
    );
    echo json_encode(['agents' => json_decode($res['body'], true) ?: []]);
    exit;
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = trim($body['name'] ?? '');
    if (!$name) { http_response_code(400); echo json_encode(['detail' => 'Agent name is required.']); exit; }

    $size    = trim($body['size']    ?? '1792x1024');
    $quality = trim($body['quality'] ?? 'standard');
    if (!in_array($size,    $valid_sizes, true)) $size    = '1792x1024';
    if (!in_array($quality, $valid_quals, true)) $quality = 'standard';

    $res = supabase_call('POST', '/rest/v1/image_agents', [
        'group_id'     => $group_id,
        'user_id'      => $user_id,
        'name'         => substr($name, 0, 80),
        'instructions' => (string)($body['instructions'] ?? ''),
        'size'         => $size,
        'quality'      => $quality,
        'sort'         => (int)($body['sort'] ?? 0),
    ], ['Prefer: return=representation']);
    if ($res['status'] >= 400) { http_response_code(500); echo json_encode(['detail' => 'Failed to create agent.']); exit; }
    $data = json_decode($res['body'], true);
    echo json_encode($data[0]);
    exit;
}

if ($method === 'PATCH') {
    $agent_id = $_GET['id'] ?? '';
    if (!$agent_id) { http_response_code(400); echo json_encode(['detail' => 'Agent id required.']); exit; }
    $body   = json_decode(file_get_contents('php://input'), true) ?: [];
    $update = ['updated_at' => date('c')];
    if (isset($body['name']) && trim($body['name']) !== '') $update['name'] = substr(trim($body['name']), 0, 80);
    if (isset($body['instructions']))                       $update['instructions'] = (string)$body['instructions'];
    if (isset($body['size'])    && in_array($body['size'],    $valid_sizes, true)) $update['size']    = $body['size'];
    if (isset($body['quality']) && in_array($body['quality'], $valid_quals, true)) $update['quality'] = $body['quality'];
    if (isset($body['sort']))                               $update['sort'] = (int)$body['sort'];
    if (count($update) === 1) { http_response_code(400); echo json_encode(['detail' => 'No recognized fields to update.']); exit; }

    $res = supabase_call('PATCH',
        '/rest/v1/image_agents?id=eq.' . urlencode($agent_id) . '&group_id=eq.' . urlencode($group_id),
        $update
    );
    if ($res['status'] >= 400) { http_response_code(500); echo json_encode(['detail' => 'Failed to update agent.']); exit; }
    echo json_encode(['status' => 'updated']);
    exit;
}

if ($method === 'DELETE') {
    $agent_id = $_GET['id'] ?? '';
    if (!$agent_id) { http_response_code(400); echo json_encode(['detail' => 'Agent id required.']); exit; }
    $res = supabase_call('DELETE',
        '/rest/v1/image_agents?id=eq.' . urlencode($agent_id) . '&group_id=eq.' . urlencode($group_id)
    );
    if ($res['status'] >= 400) { http_response_code(500); echo json_encode(['detail' => 'Failed to delete agent.']); exit; }
    echo json_encode(['status' => 'deleted']);
    exit;
}

http_response_code(405);
echo json_encode(['detail' => 'Method not allowed.']);
