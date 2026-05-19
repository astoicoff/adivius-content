<?php
ob_start();
set_time_limit(300);
require_once __DIR__ . '/helpers.php';
set_headers();

try {
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

    $group_res  = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($group_id) . '&user_id=eq.' . urlencode($user_id) . '&select=content_rules,webhook_url,name');
    $group_data = json_decode($group_res['body'], true);
    if (empty($group_data)) { http_response_code(400); ob_end_clean(); echo json_encode(['detail' => 'Content group not found.']); exit; }

    if ($generation_id) update_generation_row($generation_id, ['status' => 'generating_content']);

    // 1. Perplexity deep research
    $research_data = call_perplexity([
        ['role' => 'system', 'content' => 'You are a factual research assistant. Provide detailed factual research, EEAT insights, and verify claims for the given topic.'],
        ['role' => 'user',   'content' => "Research the following keyword deeply for an SEO article to ensure strong E-E-A-T: $keyword"],
    ], $perp_key);

    // 2. OpenAI content generation
    $system_prompt = $group_data[0]['content_rules'];
    $user_prompt   = "I have provided you with all tool outputs necessary below. Please 'think' step-by-step applying the E-E-A-T framework and then output the WordPress HTML text!\n\n"
        . "Content Brief from Phase 1:\n$edited_brief\n\n"
        . "Competitors (from SerpApi Phase 1):\n$serpapi_text\n\n"
        . "Perplexity Fact Research:\n$research_data";

    // Read model + snapshot existing content (same DB call)
    $snap     = $generation_id ? supabase_call('GET', '/rest/v1/content_generations?id=eq.' . urlencode($generation_id) . '&user_id=eq.' . urlencode($user_id) . '&select=content,model') : null;
    $snapData = $snap ? (json_decode($snap['body'], true) ?? []) : [];
    $model    = $snapData[0]['model'] ?? 'gpt-5';

    $final_content = call_ai($system_prompt, $user_prompt, $model, $settings);

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

    // Fire webhook if configured on the group. Failure does not fail the request.
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
            update_generation_row($generation_id, [
                'webhook_delivered_at' => date('c'),
                'webhook_error'        => null,
            ]);
        } else {
            update_generation_row($generation_id, [
                'webhook_delivered_at' => null,
                'webhook_error'        => substr($result['error'] ?? 'unknown error', 0, 500),
            ]);
        }
    }

    ob_end_clean();
    echo json_encode([
        'html_content' => $final_content,
        'research_raw' => $research_data,
    ]);

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['detail' => 'Server error: ' . $e->getMessage()]);
}
