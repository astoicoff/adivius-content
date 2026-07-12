<?php
require_once __DIR__ . '/../helpers.php';
header('Content-Type: application/json');

// Machine-to-machine callback from Nucleus. Fires when Nucleus's site adapter
// finishes publishing (or fails) a piece we handed off earlier.
//
// Auth: Bearer <NUCLEUS_SERVICE_TOKEN> + X-Nucleus-Tool: nucleus.
//
// Expected payload:
//   {
//     "queue_id":     "<uuid, matches what we stored as nucleus_queue_id>",
//     "status":       "published" | "failed",
//     "live_url":     "<string, required when status=published>",
//     "error":        "<string, optional, when status=failed>",
//     "published_at": "<ISO-8601, optional>"
//   }
//
// Effects on the matched generation row:
//   status=published → status='published', wp_post_url=live_url,
//                      nucleus_publish_error cleared.
//   status=failed    → status untouched; error stored on
//                      nucleus_publish_error and handed_off_at cleared so
//                      the Send-to-Nucleus button reappears for a re-send.

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$tool = $_SERVER['HTTP_X_NUCLEUS_TOOL'] ?? '';
if (function_exists('getallheaders')) {
    $h    = getallheaders();
    $auth = $auth ?: ($h['Authorization']  ?? $h['authorization']  ?? '');
    $tool = $tool ?: ($h['X-Nucleus-Tool'] ?? $h['x-nucleus-tool'] ?? '');
}
if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)
    || !NUCLEUS_SERVICE_TOKEN
    || !hash_equals(NUCLEUS_SERVICE_TOKEN, $m[1])
    || $tool !== 'nucleus') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'reason' => 'method_not_allowed']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true) ?: [];
$queue_id = trim($body['queue_id'] ?? '');
$status   = trim($body['status']   ?? '');
$live_url = trim($body['live_url'] ?? '');
$error    = trim($body['error']    ?? '');

if (!$queue_id) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'reason' => 'missing_field', 'field' => 'queue_id']);
    exit;
}
if (!in_array($status, ['published', 'failed'], true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'reason' => 'invalid_field', 'field' => 'status']);
    exit;
}

// Locate the generation by nucleus_queue_id.
$genRes  = supabase_call('GET',
    '/rest/v1/content_generations?nucleus_queue_id=eq.' . urlencode($queue_id)
    . '&select=id&limit=1'
);
$genData = json_decode($genRes['body'], true);
$gen_id  = $genData[0]['id'] ?? null;
if (!$gen_id) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'reason' => 'unknown_queue_id']);
    exit;
}

$patch = ['updated_at' => date('c')];
if ($status === 'published') {
    $patch['status'] = 'published';
    $patch['published_at'] = trim($body['published_at'] ?? '') ?: date('c');
    $patch['nucleus_publish_error'] = null;
    // The handoff cycle is over — clearing this re-arms Send to Nucleus so an
    // edited piece can be re-sent (Nucleus updates the live post in place).
    $patch['handed_off_at'] = null;
    if ($live_url) $patch['wp_post_url'] = $live_url;
} else {
    // Clear handed_off_at so the handoff button reappears — the user's
    // recovery path is to fix whatever broke and re-send. queue_id is kept
    // for audit; a re-send overwrites it.
    $patch['nucleus_publish_error'] = substr($error ?: 'unknown error', 0, 500);
    $patch['handed_off_at']         = null;
}

$res = supabase_call('PATCH',
    '/rest/v1/content_generations?id=eq.' . urlencode($gen_id),
    $patch
);
if ($res['status'] >= 400) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'storage_error']);
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true, 'generation_id' => $gen_id]);
