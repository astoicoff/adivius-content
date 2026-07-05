<?php
require_once __DIR__ . '/../helpers.php';
header('Content-Type: application/json');

// Machine-to-machine callback from Nucleus. Fires when an operator edits a
// piece in Nucleus's review inbox before publishing (Nucleus Roadmap 6, §3).
// We update our stored copy so the generation matches what Nucleus will ship.
//
// Auth: Bearer <NUCLEUS_SERVICE_TOKEN> + X-Nucleus-Tool: nucleus.
//
// Expected payload:
//   {
//     "generation_id": "<uuid — content_generations.id; the source_ref we
//                        sent Nucleus on handoff>",
//     "title":     "<string, optional>",
//     "body_html": "<string, edited body HTML>",
//     "slug":      "<string, optional>",
//     "excerpt":   "<string, optional>"
//   }

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

$body      = json_decode(file_get_contents('php://input'), true) ?: [];
$gen_id    = trim($body['generation_id'] ?? '');
$title     = trim($body['title']     ?? '');
$body_html = (string)($body['body_html'] ?? '');
$slug      = trim($body['slug']      ?? '');

if (!$gen_id) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'reason' => 'missing_field', 'field' => 'generation_id']);
    exit;
}

// Confirm the generation exists before writing.
$genRes  = supabase_call('GET',
    '/rest/v1/content_generations?id=eq.' . urlencode($gen_id) . '&select=id&limit=1'
);
$gen_row = json_decode($genRes['body'], true);
if (empty($gen_row)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'reason' => 'unknown_generation_id']);
    exit;
}

// Rebuild the meta-prefixed content string parse_content_meta() expects: a
// short `title:` / `url:` header, a blank line, then the body HTML.
$content = '';
if ($title) $content .= "title: {$title}\n";
if ($slug)  $content .= "url: {$slug}\n";
$content .= "\n" . $body_html;

$patch = [
    'content'           => $content,
    'nucleus_edited_at' => date('c'),
    'updated_at'        => date('c'),
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
