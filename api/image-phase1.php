<?php
set_time_limit(120);
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];
$body    = json_decode(file_get_contents('php://input'), true);
$keyword  = trim($body['keyword']  ?? '');
$group_id = trim($body['group_id'] ?? '');
$model    = trim($body['model']    ?? 'gpt-5.5');

if (!$keyword)  { http_response_code(400); echo json_encode(['detail' => 'Keyword is required.']); exit; }
if (!$group_id) { http_response_code(400); echo json_encode(['detail' => 'Content group is required.']); exit; }

$settings   = get_user_settings($user_id);
$openai_key = $settings['openai_key'] ?? '';
if (!$openai_key) {
    http_response_code(400); echo json_encode(['detail' => 'An OpenAI API key is required for image generation. Add one in API Keys.']); exit;
}

if (!check_group_access($user_id, $group_id, 'moderator')) {
    http_response_code(403); echo json_encode(['detail' => 'Content group not found or insufficient permissions.']); exit;
}

$group_res  = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($group_id) . '&select=image_rules');
$group_data = json_decode($group_res['body'], true);
if (empty($group_data)) { http_response_code(400); echo json_encode(['detail' => 'Content group not found.']); exit; }

$image_rules = trim($group_data[0]['image_rules'] ?? '');
if (!$image_rules) {
    $image_rules = 'You are an expert AI image prompt engineer. Create a detailed, vivid, and effective AI image generation prompt based on the keyword provided. The prompt should describe the subject, composition, style, lighting, and mood clearly.';
}

// Create or reuse a generation row
$generation_id = trim($body['generation_id'] ?? '');
if ($generation_id) {
    $check = supabase_call('GET', '/rest/v1/image_generations?id=eq.' . urlencode($generation_id) . '&user_id=eq.' . urlencode($user_id) . '&select=id');
    if (empty(json_decode($check['body'], true))) {
        http_response_code(404); echo json_encode(['detail' => 'Generation not found.']); exit;
    }
} else {
    $res  = supabase_call('POST', '/rest/v1/image_generations', [
        'user_id'  => $user_id,
        'group_id' => $group_id,
        'keyword'  => $keyword,
        'model'    => $model,
        'status'   => 'pending',
    ], ['Prefer: return=representation']);
    $row = json_decode($res['body'], true);
    if (empty($row[0]['id'])) {
        http_response_code(500); echo json_encode(['detail' => 'Failed to create generation row.']); exit;
    }
    $generation_id = $row[0]['id'];
}

supabase_call('PATCH', '/rest/v1/image_generations?id=eq.' . urlencode($generation_id), [
    'status'     => 'generating_prompt',
    'model'      => $model,
    'updated_at' => date('c'),
]);

// All validation passed — switch to SSE
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

try {
    emit_sse(['type' => 'progress', 'message' => 'Generating image prompt…']);

    $prompt = stream_ai($image_rules, $keyword, $model, $settings);

    supabase_call('PATCH', '/rest/v1/image_generations?id=eq.' . urlencode($generation_id), [
        'prompt'     => $prompt,
        'status'     => 'pending',
        'updated_at' => date('c'),
    ]);

    emit_sse(['type' => 'done', 'generation_id' => $generation_id, 'prompt' => $prompt]);

} catch (Throwable $e) {
    supabase_call('PATCH', '/rest/v1/image_generations?id=eq.' . urlencode($generation_id), [
        'status'     => 'failed',
        'error'      => substr($e->getMessage(), 0, 500),
        'updated_at' => date('c'),
    ]);
    emit_sse(['type' => 'error', 'message' => $e->getMessage()]);
}
