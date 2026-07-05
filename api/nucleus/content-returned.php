<?php
require_once __DIR__ . '/../helpers.php';
header('Content-Type: application/json');

// Machine-to-machine callback from Nucleus. Fires when an operator returns a
// handed-off piece for revision instead of publishing it (Nucleus Roadmap 6,
// §4). We stamp the return + note so the writer sees it needs work.
//
// Auth: Bearer <NUCLEUS_SERVICE_TOKEN> + X-Nucleus-Tool: nucleus.
//
// Expected payload:
//   {
//     "generation_id": "<uuid — content_generations.id / our source_ref>",
//     "note":          "<string, optional — why it was returned>"
//   }
//
// Effect: sets nucleus_returned_at + nucleus_return_note. The Content UI should
// treat a non-null nucleus_returned_at as "needs revision" (a returned piece
// was never published). Status is left as-is on purpose — Content owns its own
// status model; the return stamp is the signal.

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

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$gen_id = trim($body['generation_id'] ?? '');
$note   = trim($body['note'] ?? '');

if (!$gen_id) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'reason' => 'missing_field', 'field' => 'generation_id']);
    exit;
}

$genRes  = supabase_call('GET',
    '/rest/v1/content_generations?id=eq.' . urlencode($gen_id) . '&select=id&limit=1'
);
$gen_row = json_decode($genRes['body'], true);
if (empty($gen_row)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'reason' => 'unknown_generation_id']);
    exit;
}

$patch = [
    'nucleus_returned_at' => date('c'),
    'nucleus_return_note' => $note !== '' ? substr($note, 0, 1000) : null,
    'updated_at'          => date('c'),
];

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
