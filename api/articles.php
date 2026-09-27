<?php
// Every content generation the caller can see: their own, plus everything in
// groups they own or are a member of. Unlike /api/history (own rows, latest
// 50), this is the full list behind the Articles page.
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); echo json_encode(['detail' => 'Method not allowed.']); exit;
}

// Accessible groups — same rule as check_group_access(): owner, or any membership.
$owned = json_decode(supabase_call('GET',
    '/rest/v1/content_groups?user_id=eq.' . urlencode($user_id) . '&select=id,name'
)['body'], true) ?: [];
$memberships = json_decode(supabase_call('GET',
    '/rest/v1/content_group_members?user_id=eq.' . urlencode($user_id) . '&select=group_id'
)['body'], true) ?: [];

$groups = [];
foreach ($owned as $g) $groups[$g['id']] = $g['name'];
$shared_ids = array_values(array_diff(array_column($memberships, 'group_id'), array_keys($groups)));
if ($shared_ids) {
    $shared = json_decode(supabase_call('GET',
        '/rest/v1/content_groups?id=in.(' . implode(',', array_map('urlencode', $shared_ids)) . ')&select=id,name'
    )['body'], true) ?: [];
    foreach ($shared as $g) $groups[$g['id']] = $g['name'];
}

$filter = 'user_id.eq.' . urlencode($user_id);
if ($groups) {
    $filter .= ',group_id.in.(' . implode(',', array_map('urlencode', array_keys($groups))) . ')';
}

// No `content` — it can be tens of KB per row, and the list only needs metadata.
$res = supabase_call('GET',
    '/rest/v1/content_generations?or=(' . $filter . ')'
    . '&select=id,keyword,status,model,group_id,user_id,created_at,updated_at,'
    . 'handed_off_at,published_at,nucleus_publish_error,wp_post_url'
    . '&order=created_at.desc&limit=1000'
);
if ($res['status'] >= 400) {
    http_response_code(500); echo json_encode(['detail' => 'Failed to load articles.']); exit;
}

echo json_encode([
    'articles' => json_decode($res['body'], true) ?: [],
    'groups'   => $groups,
]);
