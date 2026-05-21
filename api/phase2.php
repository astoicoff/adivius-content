<?php
set_time_limit(300);
require_once __DIR__ . '/helpers.php';
set_headers();

// Validate auth + inputs before switching to SSE (errors return normal JSON)
$user    = get_authed_user();
$user_id = $user['id'];
$body    = json_decode(file_get_contents('php://input'), true);

$keyword       = trim($body['keyword']       ?? '');
$edited_brief  = trim($body['edited_brief']  ?? '');
$serpapi_text  = trim($body['serpapi_text']  ?? '');
$generation_id = trim($body['generation_id'] ?? '');
$group_id      = trim($body['group_id']      ?? '');

$settings = get_user_settings($user_id);
$perp_key = $settings['perplexity_key'] ?: '';

if (!check_group_access($user_id, $group_id, 'moderator')) {
    http_response_code(403); echo json_encode(['detail' => 'Content group not found or insufficient permissions.']); exit;
}
$group_res  = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($group_id) . '&select=content_rules,webhook_url,name');
$group_data = json_decode($group_res['body'], true);
if (empty($group_data)) { http_response_code(400); echo json_encode(['detail' => 'Content group not found.']); exit; }

if ($generation_id) update_generation_row($generation_id, ['status' => 'generating_content']);

// All validation passed — switch to SSE
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

try {
    // 1.2 — DB snapshot moved BEFORE Perplexity call (~200ms saved)
    emit_sse(['type' => 'progress', 'step' => 1, 'message' => 'Loading group settings…']);
    $snap     = $generation_id ? supabase_call('GET', '/rest/v1/content_generations?id=eq.' . urlencode($generation_id) . '&select=content,model') : null;
    $snapData = $snap ? (json_decode($snap['body'], true) ?? []) : [];
    $model    = $snapData[0]['model'] ?? 'gpt-5';

    // 2. Perplexity deep research
    emit_sse(['type' => 'progress', 'step' => 2, 'message' => 'Researching topic…']);
    $research_data = call_perplexity([
        ['role' => 'system', 'content' => 'You are a factual research assistant. Provide detailed factual research, EEAT insights, and verify claims for the given topic.'],
        ['role' => 'user',   'content' => "Research the following keyword deeply for an SEO article to ensure strong E-E-A-T: $keyword"],
    ], $perp_key);

    // 3. AI content generation (streaming)
    emit_sse(['type' => 'progress', 'step' => 3, 'message' => 'Writing content…']);
    $system_prompt = $group_data[0]['content_rules'];
    $user_prompt   = "I have provided you with all tool outputs necessary below. Please 'think' step-by-step applying the E-E-A-T framework and then output the WordPress HTML text!\n\n"
        . "Content Brief from Phase 1:\n$edited_brief\n\n"
        . "Competitors (from SerpApi Phase 1):\n$serpapi_text\n\n"
        . "Perplexity Fact Research:\n$research_data";

    $final_content = stream_ai($system_prompt, $user_prompt, $model, $settings);

    if ($generation_id) {
        if (!empty($snapData[0]['content'])) {
            supabase_call('POST', '/rest/v1/content_generation_versions', [
                'generation_id' => $generation_id,
                'user_id'       => $user_id,
                'content'       => $snapData[0]['content'],
            ]);
        }
        update_generation_row($generation_id, [
            'status'  => 'completed',
            'content' => $final_content,
        ]);
    }

    // Fire webhook (failure does not fail the request)
    $webhook_url = trim($group_data[0]['webhook_url'] ?? '');
    if ($generation_id && $webhook_url) {
        $parsed  = parse_content_meta($final_content);
        $payload = [
            'event'         => 'content.completed',
            'generation_id' => $generation_id,
            'keyword'       => $keyword,
            'group_id'      => $group_id,
            'group_name'    => $group_data[0]['name'] ?? null,
            'completed_at'  => date('c'),
            'meta'          => $parsed['meta'],
            'content'       => $final_content,
            'html'          => $parsed['body'],
        ];
        $result = fire_webhook($webhook_url, $payload);
        if ($result['ok']) {
            update_generation_row($generation_id, ['webhook_delivered_at' => date('c'), 'webhook_error' => null]);
        } else {
            update_generation_row($generation_id, ['webhook_delivered_at' => null, 'webhook_error' => substr($result['error'] ?? 'unknown error', 0, 500)]);
        }
    }

    emit_sse(['type' => 'done', 'generation_id' => $generation_id, 'html_content' => $final_content]);

} catch (Throwable $e) {
    emit_sse(['type' => 'error', 'message' => $e->getMessage()]);
}
