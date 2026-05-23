<?php
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];
$id      = $_GET['id'] ?? '';

if (!$id) { http_response_code(400); echo json_encode(['detail' => 'Generation ID is required.']); exit; }

// Fetch the generation (no user filter) to resolve group_id, then verify access.
function fetch_gen_with_access(string $id, string $user_id, string $min_role = 'viewer'): array|null {
    $res  = supabase_call('GET',
        '/rest/v1/content_generations?id=eq.' . urlencode($id)
        . '&select=id,keyword,status,content,instructions,serpapi_raw,created_at,group_id,user_id,wp_post_url,webhook_delivered_at,webhook_error,model,client_id,handed_off_at,nucleus_queue_id'
    );
    $data = json_decode($res['body'], true);
    if (empty($data)) return null;
    $gen = $data[0];

    // Owner of the generation
    if ($gen['user_id'] === $user_id) return $gen;

    // Shared group member
    $group_id = $gen['group_id'] ?? '';
    if ($group_id && check_group_access($user_id, $group_id, $min_role) !== false) return $gen;

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gen = fetch_gen_with_access($id, $user_id, 'moderator');
    if (!$gen) { http_response_code(404); echo json_encode(['detail' => 'Generation not found.']); exit; }

    $res = supabase_call('POST', '/rest/v1/content_generations', [
        'user_id'  => $user_id,
        'keyword'  => $gen['keyword'],
        'group_id' => $gen['group_id'] ?? null,
        'content'  => $gen['content']  ?? null,
        'status'   => 'completed',
    ], ['Prefer: return=representation']);
    if ($res['status'] >= 400) { http_response_code(500); echo $res['body']; exit; }
    $newRow = json_decode($res['body'], true);
    echo json_encode(['id' => $newRow[0]['id']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $gen = fetch_gen_with_access($id, $user_id, 'viewer');
    if (!$gen) { http_response_code(404); echo json_encode(['detail' => 'Generation not found.']); exit; }

    // Only the generation owner or group owner may delete
    $is_gen_owner   = $gen['user_id'] === $user_id;
    $is_group_owner = $gen['group_id'] && check_group_access($user_id, $gen['group_id'], 'owner') === 'owner';
    if (!$is_gen_owner && !$is_group_owner) {
        http_response_code(403); echo json_encode(['detail' => 'You cannot delete this content.']); exit;
    }

    supabase_call('DELETE', '/rest/v1/content_generations?id=eq.' . urlencode($id));
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $gen = fetch_gen_with_access($id, $user_id, 'moderator');
    if (!$gen) { http_response_code(404); echo json_encode(['detail' => 'Generation not found.']); exit; }

    $body         = json_decode(file_get_contents('php://input'), true) ?: [];
    $content      = $body['content']      ?? null;
    $instructions = $body['instructions'] ?? null;
    if ($content === null && $instructions === null) { http_response_code(400); echo json_encode(['detail' => 'content or instructions is required.']); exit; }

    $patch = [];
    if ($content      !== null) $patch['content']      = $content;
    if ($instructions !== null) $patch['instructions'] = $instructions;

    $res = supabase_call('PATCH',
        '/rest/v1/content_generations?id=eq.' . urlencode($id),
        $patch,
        ['Prefer: return=representation']
    );
    if ($res['status'] >= 400) { http_response_code($res['status']); echo $res['body']; exit; }
    echo json_encode(['success' => true]);
    exit;
}

// GET
$gen = fetch_gen_with_access($id, $user_id, 'viewer');
if (!$gen) { http_response_code(404); echo json_encode(['detail' => 'Generation not found.']); exit; }
unset($gen['user_id']);  // don't expose internal user_id to client
echo json_encode($gen);
