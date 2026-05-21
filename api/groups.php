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
        $id   = $_GET['id'];
        $role = check_group_access($user_id, $id);
        if (!$role) { http_response_code(404); echo json_encode(['detail' => 'Group not found.']); exit; }

        // Fetch from content_groups without user_id filter (access already verified above)
        $res  = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($id) . '&select=*');
        $data = json_decode($res['body'], true);
        if (empty($data)) { http_response_code(404); echo json_encode(['detail' => 'Group not found.']); exit; }
        $group = $data[0];

        $group['wp_configured'] = !empty($group['wp_site_url']) && !empty($group['wp_username']) && !empty($group['wp_app_password']);
        $group['my_role']       = $role;
        if ($role !== 'owner') unset($group['wp_app_password'], $group['webhook_url'], $group['instructions_rules'], $group['content_rules']);
        else unset($group['wp_app_password']);

        // Member count
        $memRes = supabase_call('GET', '/rest/v1/content_group_members?group_id=eq.' . urlencode($id) . '&select=id');
        $group['members_count'] = count(json_decode($memRes['body'], true) ?: []);

        $genRes = supabase_call('GET',
            '/rest/v1/content_generations?group_id=eq.' . urlencode($id)
            . '&select=id,keyword,status,content,created_at,updated_at'
            . '&order=created_at.desc'
        );
        $group['generations'] = json_decode($genRes['body'], true) ?: [];
        echo json_encode($group);

    } else {
        // Own groups
        $res    = supabase_call('GET',
            '/rest/v1/content_groups?user_id=eq.' . urlencode($user_id)
            . '&select=id,name,created_at,updated_at'
            . '&order=created_at.desc'
        );
        $owned = json_decode($res['body'], true) ?: [];
        foreach ($owned as &$g) $g['my_role'] = 'owner';

        // Shared groups (where user is a member)
        $memRes  = supabase_call('GET',
            '/rest/v1/content_group_members?user_id=eq.' . urlencode($user_id)
            . '&select=group_id,role'
        );
        $memberships = json_decode($memRes['body'], true) ?: [];
        $shared = [];
        foreach ($memberships as $m) {
            $gRes = supabase_call('GET',
                '/rest/v1/content_groups?id=eq.' . urlencode($m['group_id'])
                . '&select=id,name,created_at,updated_at'
            );
            $gData = json_decode($gRes['body'], true);
            if (!empty($gData)) {
                $entry            = $gData[0];
                $entry['my_role'] = $m['role'];
                $entry['is_shared'] = true;
                $shared[] = $entry;
            }
        }

        $groups = array_merge($owned, $shared);

        // Generation counts (all groups combined)
        if (!empty($groups)) {
            $allIds = array_column($groups, 'id');
            // Fetch counts per group_id for all relevant groups
            foreach ($groups as &$group) {
                $cRes = supabase_call('GET',
                    '/rest/v1/content_generations?group_id=eq.' . urlencode($group['id'])
                    . '&select=id'
                );
                $group['generation_count'] = count(json_decode($cRes['body'], true) ?: []);
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

    $role = check_group_access($user_id, $id, 'owner');
    if (!$role) { http_response_code(403); echo json_encode(['detail' => 'Only the group owner can edit settings.']); exit; }

    $update = ['updated_at' => date('c')];
    if (isset($body['name']))                $update['name']                = $body['name'];
    if (isset($body['instructions_rules']))  $update['instructions_rules']  = $body['instructions_rules'];
    if (isset($body['content_rules']))       $update['content_rules']       = $body['content_rules'];
    if (array_key_exists('wp_site_url',     $body)) $update['wp_site_url']     = $body['wp_site_url'];
    if (array_key_exists('wp_username',     $body)) $update['wp_username']     = $body['wp_username'];
    if (array_key_exists('wp_app_password', $body)) $update['wp_app_password'] = $body['wp_app_password'];
    if (array_key_exists('webhook_url',     $body)) $update['webhook_url']     = $body['webhook_url'];

    $res = supabase_call('PATCH', '/rest/v1/content_groups?id=eq.' . urlencode($id), $update);
    if ($res['status'] >= 400) { http_response_code(500); echo json_encode(['detail' => 'Failed to update group: ' . $res['body']]); exit; }
    echo json_encode(['status' => 'updated']);

} elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) { http_response_code(400); echo json_encode(['detail' => 'Group ID is required.']); exit; }

    $role = check_group_access($user_id, $id, 'owner');
    if (!$role) { http_response_code(403); echo json_encode(['detail' => 'Only the group owner can delete it.']); exit; }

    supabase_call('DELETE', '/rest/v1/content_groups?id=eq.' . urlencode($id));
    echo json_encode(['status' => 'deleted']);
}
