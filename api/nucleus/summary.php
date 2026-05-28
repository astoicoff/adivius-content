<?php
require_once __DIR__ . '/../helpers.php';

// Machine-to-machine: service token auth, no user session
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!$auth && function_exists('getallheaders')) {
    $h    = getallheaders();
    $auth = $h['Authorization'] ?? $h['authorization'] ?? '';
}
header('Content-Type: application/json');

if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)
    || !NUCLEUS_SERVICE_TOKEN
    || !hash_equals(NUCLEUS_SERVICE_TOKEN, $m[1])) {
    http_response_code(401);
    echo json_encode(['detail' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['detail' => 'Method not allowed.']);
    exit;
}

$site_id   = trim($_GET['site_id']   ?? '');
$client_id = trim($_GET['client_id'] ?? '');

$zero = [
    'drafts_count'     => 0,
    'in_review'        => 0,
    'ready_to_publish' => 0,
    'last_handoff_at'  => null,
    'velocity_30d'     => 0,
    'pipeline'         => ['research' => 0, 'drafting' => 0, 'in_review' => 0, 'ready_to_publish' => 0, 'scheduled' => 0],
    'recent_publishes' => [],
    'upcoming'         => [],
];

if (!$site_id && !$client_id) {
    echo json_encode($zero);
    exit;
}

// Prefer site_id when both are provided
$filter = $site_id
    ? 'site_id=eq.' . urlencode($site_id)
    : 'client_id=eq.' . urlencode($client_id);

// ── Pipeline (all non-published) ──────────────────────────────────────────────
$pipeRes  = supabase_call('GET',
    '/rest/v1/content_generations?' . $filter
    . '&status=neq.published&select=id,status,handed_off_at'
);
$pipeRows = json_decode($pipeRes['body'], true) ?: [];

$research         = 0;
$drafting         = 0;
$in_review        = 0;
$ready_to_publish = 0;
$last_handoff_at  = null;

$researchStatuses = ['pending', 'generating_instructions', 'instructions_ready'];

foreach ($pipeRows as $row) {
    $status = $row['status']      ?? '';
    $ho     = $row['handed_off_at'] ?? null;

    if (in_array($status, $researchStatuses)) {
        $research++;
    } elseif ($status === 'generating_content') {
        $drafting++;
    } elseif ($status === 'completed') {
        if ($ho) {
            $in_review++;
            if (!$last_handoff_at || $ho > $last_handoff_at) $last_handoff_at = $ho;
        } else {
            $ready_to_publish++;
        }
    }
}

// ── Published pieces ──────────────────────────────────────────────────────────
$publishedRes  = supabase_call('GET',
    '/rest/v1/content_generations?' . $filter
    . '&status=eq.published'
    . '&select=id,keyword,content,wp_post_url,handed_off_at,created_at'
    . '&order=handed_off_at.desc.nullslast,created_at.desc'
    . '&limit=50'
);
$publishedRows = json_decode($publishedRes['body'], true) ?: [];

// Track last_handoff_at from published pieces
foreach ($publishedRows as $row) {
    $ho = $row['handed_off_at'] ?? null;
    if ($ho && (!$last_handoff_at || $ho > $last_handoff_at)) $last_handoff_at = $ho;
}

// velocity_30d: published pieces created in last 30 days
$thirtyAgo    = date('c', strtotime('-30 days'));
$velocity_30d = 0;
foreach ($publishedRows as $row) {
    if (($row['created_at'] ?? '') >= $thirtyAgo) $velocity_30d++;
}

// recent_publishes: last 10 published
$recentPublishes = [];
foreach (array_slice($publishedRows, 0, 10) as $row) {
    $text = strip_tags($row['content'] ?? '');
    $wc   = $text ? count(array_filter(preg_split('/\s+/u', trim($text)))) : 0;
    $recentPublishes[] = [
        'title'        => $row['keyword'] ?? '',
        'published_at' => $row['handed_off_at'] ?? $row['created_at'],
        'url'          => $row['wp_post_url'] ?? null,
        'word_count'   => $wc,
        'keywords'     => array_filter([$row['keyword'] ?? '']),
    ];
}

echo json_encode([
    'drafts_count'     => $ready_to_publish,
    'in_review'        => $in_review,
    'ready_to_publish' => $ready_to_publish,
    'last_handoff_at'  => $last_handoff_at,
    'velocity_30d'     => $velocity_30d,
    'pipeline'         => [
        'research'         => $research,
        'drafting'         => $drafting,
        'in_review'        => $in_review,
        'ready_to_publish' => $ready_to_publish,
        'scheduled'        => 0,
    ],
    'recent_publishes' => $recentPublishes,
    'upcoming'         => [],
]);
