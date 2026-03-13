<?php
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $s = get_user_settings($user_id);
    echo json_encode([
        'openai_key'     => $s['openai_key']     ?? '',
        'perplexity_key' => $s['perplexity_key'] ?? '',
        'serpapi_key'    => $s['serpapi_key']    ?? '',
        'claude_key'     => $s['claude_key']     ?? '',
        'gemini_key'     => $s['gemini_key']     ?? '',
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $res  = upsert_user_settings(
        $user_id,
        $body['openai_key']     ?? '',
        $body['perplexity_key'] ?? '',
        $body['serpapi_key']    ?? '',
        $body['claude_key']     ?? '',
        $body['gemini_key']     ?? ''
    );
    if ($res['status'] >= 400) {
        http_response_code(500);
        echo json_encode(['detail' => 'Failed to save settings: ' . $res['body']]);
    } else {
        echo json_encode(['status' => 'saved']);
    }
}
