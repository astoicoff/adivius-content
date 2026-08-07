<?php
set_time_limit(120);
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];
$body    = json_decode(file_get_contents('php://input'), true);
$keyword     = trim($body['keyword']     ?? '');
$description = trim($body['description'] ?? '');
$group_id    = trim($body['group_id']    ?? '');
$model       = trim($body['model']       ?? 'gpt-5.5');
$agent_id    = trim($body['agent_id']    ?? '');

if (!$keyword && !$description) { http_response_code(400); echo json_encode(['detail' => 'A keyword or a description is required.']); exit; }
if (!$group_id)                 { http_response_code(400); echo json_encode(['detail' => 'Content group is required.']); exit; }

// Description mode: keyword becomes the short display label. Derive one from
// the description's opening when the user didn't supply a keyword.
if (!$keyword) {
    $keyword = preg_replace('/\s+/', ' ', $description);
    if (mb_strlen($keyword) > 60) {
        $cut = mb_substr($keyword, 0, 60);
        $sp  = mb_strrpos($cut, ' ');
        $keyword = ($sp !== false && $sp > 30 ? mb_substr($cut, 0, $sp) : $cut) . '…';
    }
}

$settings   = get_user_settings($user_id);
$openai_key = $settings['openai_key'] ?? '';
if (!$openai_key) {
    http_response_code(400); echo json_encode(['detail' => 'An OpenAI API key is required for image generation. Add one in API Keys.']); exit;
}

if (!check_group_access($user_id, $group_id, 'moderator')) {
    http_response_code(403); echo json_encode(['detail' => 'Content group not found or insufficient permissions.']); exit;
}

// Resolve the image agent whose instructions drive the prompt engineering.
// Explicit agent_id wins; else the group's first agent (by sort/created);
// else a generic fallback so groups without agents still work.
$agent_name  = null;
$image_rules = '';
if ($agent_id) {
    $agent_res  = supabase_call('GET', '/rest/v1/image_agents?id=eq.' . urlencode($agent_id) . '&group_id=eq.' . urlencode($group_id) . '&select=id,name,instructions');
    $agent_data = json_decode($agent_res['body'], true);
    if (empty($agent_data)) { http_response_code(400); echo json_encode(['detail' => 'Image agent not found in this group.']); exit; }
    $agent_name  = $agent_data[0]['name'];
    $image_rules = trim($agent_data[0]['instructions'] ?? '');
} else {
    $agent_res  = supabase_call('GET', '/rest/v1/image_agents?group_id=eq.' . urlencode($group_id) . '&select=id,name,instructions&order=sort.asc,created_at.asc&limit=1');
    $agent_data = json_decode($agent_res['body'], true);
    if (!empty($agent_data)) {
        $agent_id    = $agent_data[0]['id'];
        $agent_name  = $agent_data[0]['name'];
        $image_rules = trim($agent_data[0]['instructions'] ?? '');
    }
}
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
        'user_id'     => $user_id,
        'group_id'    => $group_id,
        'keyword'     => $keyword,
        'description' => $description ?: null,
        'model'       => $model,
        'status'      => 'pending',
        'agent_id'    => $agent_id ?: null,
        'agent_name'  => $agent_name,
    ], ['Prefer: return=representation']);
    $row = json_decode($res['body'], true);
    if (empty($row[0]['id'])) {
        http_response_code(500); echo json_encode(['detail' => 'Failed to create generation row.']); exit;
    }
    $generation_id = $row[0]['id'];
}

supabase_call('PATCH', '/rest/v1/image_generations?id=eq.' . urlencode($generation_id), [
    'status'      => 'generating_prompt',
    'model'       => $model,
    'description' => $description ?: null,
    'agent_id'    => $agent_id ?: null,
    'agent_name'  => $agent_name,
    'updated_at'  => date('c'),
]);

// All validation passed — switch to SSE
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

try {
    emit_sse(['type' => 'progress', 'message' => 'Generating image prompt…']);

    // Keyword mode: the agent invents a scene from a topic. Description mode:
    // the user supplied the creative brief — the agent's style rules still
    // apply, but the brief's specifics are binding, not inspiration.
    if ($description) {
        $system_prompt = $image_rules
            . "\n\nINPUT MODE — FULL DESCRIPTION: the user has provided a complete description of the desired image, not just a keyword. "
            . "Treat it as a binding creative brief: every subject, object, setting, and compositional detail it specifies MUST appear in the final prompt. "
            . "Apply your style, lighting, and format rules AROUND the brief — do not replace or reinterpret its content.";
        $user_input = $description;
    } else {
        $system_prompt = $image_rules;
        $user_input    = $keyword;
    }

    $prompt = stream_ai($system_prompt, $user_input, $model, $settings);

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
