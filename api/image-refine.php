<?php
set_time_limit(120);
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];
$body    = json_decode(file_get_contents('php://input'), true);

$generation_id = trim($body['generation_id'] ?? '');
$instruction   = trim($body['instruction']   ?? '');

if (!$generation_id) { http_response_code(400); echo json_encode(['detail' => 'generation_id is required.']); exit; }
if (!$instruction)   { http_response_code(400); echo json_encode(['detail' => 'instruction is required.']);   exit; }

// Verify ownership + fetch existing prompt + model
$check = supabase_call('GET', '/rest/v1/image_generations?id=eq.' . urlencode($generation_id)
    . '&user_id=eq.' . urlencode($user_id) . '&select=id,prompt,model');
$rows  = json_decode($check['body'], true);

if (empty($rows)) {
    http_response_code(404);
    echo json_encode(['detail' => 'Image generation not found.']);
    exit;
}

$existing_prompt = $rows[0]['prompt'] ?? '';
$model           = $rows[0]['model']  ?? 'gpt-5';

if (!$existing_prompt) {
    http_response_code(400);
    echo json_encode(['detail' => 'No prompt stored for this generation. Use New Prompt to start fresh.']);
    exit;
}

$settings = get_user_settings($user_id);

// Switch to SSE
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

try {
    emit_sse(['type' => 'progress', 'message' => 'Refining prompt…']);

    $system = 'You are a prompt editor for AI image generation. Given an existing prompt and a modification instruction, return ONLY the updated prompt with the modification applied. Preserve all technical specifications, style rules, lighting details, and composition from the original unless the instruction explicitly changes them. Return just the prompt text — no explanations, no quotes, no preamble.';

    $user_msg = "Original prompt:\n{$existing_prompt}\n\nModification instruction:\n{$instruction}";

    $refined = stream_ai($system, $user_msg, $model, $settings);

    supabase_call('PATCH', '/rest/v1/image_generations?id=eq.' . urlencode($generation_id), [
        'prompt'     => $refined,
        'updated_at' => date('c'),
    ]);

    emit_sse(['type' => 'done', 'generation_id' => $generation_id, 'prompt' => $refined]);

} catch (\Throwable $e) {
    emit_sse(['type' => 'error', 'message' => $e->getMessage()]);
}
