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

$client_id = trim($_GET['client_id'] ?? '');
if (!$client_id) {
    http_response_code(400);
    echo json_encode(['detail' => 'client_id is required.']);
    exit;
}

// drafts_count: completed pieces not yet handed off for this client
$draftsRes    = supabase_call('GET',
    '/rest/v1/content_generations?client_id=eq.' . urlencode($client_id)
    . '&status=eq.completed&handed_off_at=is.null&select=id'
);
$drafts_count = count(json_decode($draftsRes['body'], true) ?: []);

// last_handoff_at: most recent handoff for this client
$handoffRes      = supabase_call('GET',
    '/rest/v1/content_generations?client_id=eq.' . urlencode($client_id)
    . '&handed_off_at=not.is.null&select=handed_off_at&order=handed_off_at.desc&limit=1'
);
$handoffs        = json_decode($handoffRes['body'], true) ?: [];
$last_handoff_at = $handoffs[0]['handed_off_at'] ?? null;

echo json_encode([
    'drafts_count'      => $drafts_count,
    'in_review'         => null,
    'ready_to_publish'  => null,
    'last_handoff_at'   => $last_handoff_at,
]);
