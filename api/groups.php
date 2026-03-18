<?php
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];
$method  = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {

    if (isset($_GET['defaults'])) {
        echo json_encode([
            'instructions_rules' => file_get_contents(DIRECTIVES_DIR . '/content-instructions.md'),
            'content_rules'      => file_get_contents(DIRECTIVES_DIR . '/content-creator.md'),
        ]);

    } elseif (isset($_GET['id'])) {
        $id  = $_GET['id'];
        $res = supabase_call('GET',
            '/rest/v1/content_groups?id=eq.' . urlencode($id)
            . '&user_id=eq.' . urlencode($user_id)
            . '&select=*'
        );
        $data = json_decode($res['body'], true);
        if (empty($data)) { http_response_code(404); echo json_encode(['detail' => 'Group not found.']); exit; }
        $group = $data[0];
        // Expose whether WP is configured without leaking the password
        $group['wp_configured'] = !empty($group['wp_site_url']) && !empty($group['wp_username']) && !empty($group['wp_app_password']);
        unset($group['wp_app_password']);

        $genRes = supabase_call('GET',
            '/rest/v1/content_generations?group_id=eq.' . urlencode($id)
            . '&select=id,keyword,status,content,created_at,updated_at'
            . '&order=created_at.desc'
        );
        $group['generations'] = json_decode($genRes['body'], true) ?: [];
        echo json_encode($group);

    } else {
        $res    = supabase_call('GET',
            '/rest/v1/content_groups?user_id=eq.' . urlencode($user_id)
            . '&select=id,name,created_at,updated_at'
            . '&order=created_at.desc'
        );
        $groups = json_decode($res['body'], true) ?: [];

        // Fetch generation counts
        if (!empty($groups)) {
            $countRes = supabase_call('GET',
                '/rest/v1/content_generations?user_id=eq.' . urlencode($user_id)
                . '&select=group_id'
            );
            $gens   = json_decode($countRes['body'], true) ?: [];
            $counts = [];
            foreach ($gens as $g) {
                $gid = $g['group_id'] ?? null;
                if ($gid) $counts[$gid] = ($counts[$gid] ?? 0) + 1;
            }
            foreach ($groups as &$group) {
                $group['generation_count'] = $counts[$group['id']] ?? 0;
            }
        }

        echo json_encode(['groups' => $groups]);
    }

} elseif ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $name = trim($body['name'] ?? '');
    if (!$name) { http_response_code(400); echo json_encode(['detail' => 'Group name is required.']); exit; }

    $res = supabase_call('POST', '/rest/v1/content_groups', [
        'user_id'            => $user_id,
        'name'               => $name,
        'instructions_rules' => $body['instructions_rules'] ?? '',
        'content_rules'      => $body['content_rules']      ?? '',
    ], ['Prefer: return=representation']);
    if ($res['status'] >= 400) { http_response_code(500); echo json_encode(['detail' => 'Failed to create group: ' . $res['body']]); exit; }
    $data = json_decode($res['body'], true);
    echo json_encode($data[0]);

} elseif ($method === 'PATCH') {
    $id   = $_GET['id'] ?? '';
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$id) { http_response_code(400); echo json_encode(['detail' => 'Group ID is required.']); exit; }

    $update = ['updated_at' => date('c')];
    if (isset($body['name']))                $update['name']                = $body['name'];
    if (isset($body['instructions_rules']))  $update['instructions_rules']  = $body['instructions_rules'];
    if (isset($body['content_rules']))       $update['content_rules']       = $body['content_rules'];
    if (array_key_exists('wp_site_url',      $body)) $update['wp_site_url']      = $body['wp_site_url'];
    if (array_key_exists('wp_username',      $body)) $update['wp_username']      = $body['wp_username'];
    if (array_key_exists('wp_app_password',  $body)) $update['wp_app_password']  = $body['wp_app_password'];

    $res = supabase_call('PATCH',
        '/rest/v1/content_groups?id=eq.' . urlencode($id) . '&user_id=eq.' . urlencode($user_id),
        $update
    );
    if ($res['status'] >= 400) { http_response_code(500); echo json_encode(['detail' => 'Failed to update group: ' . $res['body']]); exit; }
    echo json_encode(['status' => 'updated']);
}
