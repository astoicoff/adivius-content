<?php
ob_start();
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];

// Helper: count rows via PostgREST Content-Range header
function supabase_count($path) {
    $ch = curl_init(SUPABASE_URL . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'GET',
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'apikey: '         . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Prefer: count=exact',
            'Range: 0-0',
        ],
        CURLOPT_HEADER => true,
    ]);
    $raw        = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr($raw, 0, $headerSize);
    if (preg_match('/Content-Range:\s*[\d\*]+-[\d\*]+\/(\d+)/i', $headers, $m)) return (int)$m[1];
    return 0;
}

$startOfMonth = date('Y-m-01') . 'T00:00:00.000Z';

$total      = supabase_count('/rest/v1/content_generations?user_id=eq.' . urlencode($user_id) . '&select=id');
$thisMonth  = supabase_count('/rest/v1/content_generations?user_id=eq.' . urlencode($user_id) . '&created_at=gte.' . urlencode($startOfMonth) . '&select=id');
$groupCount = supabase_count('/rest/v1/content_groups?user_id=eq.'      . urlencode($user_id) . '&select=id');

// Most active group: count generations per group_id
$genRes  = supabase_call('GET', '/rest/v1/content_generations?user_id=eq.' . urlencode($user_id) . '&select=group_id');
$genRows = json_decode($genRes['body'], true) ?: [];
$groupCounts = [];
foreach ($genRows as $row) {
    $gid = $row['group_id'] ?? null;
    if ($gid) $groupCounts[$gid] = ($groupCounts[$gid] ?? 0) + 1;
}
$mostActiveId    = null;
$mostActiveCount = 0;
foreach ($groupCounts as $gid => $cnt) {
    if ($cnt > $mostActiveCount) { $mostActiveId = $gid; $mostActiveCount = $cnt; }
}
$mostActiveName = null;
if ($mostActiveId) {
    $grp = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($mostActiveId) . '&select=name');
    $grpData = json_decode($grp['body'], true);
    $mostActiveName = $grpData[0]['name'] ?? null;
}

// Recent 5 generations
$recentRes = supabase_call('GET',
    '/rest/v1/content_generations?user_id=eq.' . urlencode($user_id)
    . '&select=id,keyword,status,group_id,created_at'
    . '&order=created_at.desc&limit=5'
);
$recent = json_decode($recentRes['body'], true) ?: [];

ob_end_clean();
echo json_encode([
    'total'              => $total,
    'this_month'         => $thisMonth,
    'groups'             => $groupCount,
    'most_active_group'  => $mostActiveName,
    'most_active_count'  => $mostActiveCount,
    'recent'             => $recent,
]);
