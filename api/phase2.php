<?php
set_time_limit(300);
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];
$body    = json_decode(file_get_contents('php://input'), true);

$keyword       = trim($body['keyword']       ?? '');
$edited_brief  = trim($body['edited_brief']  ?? '');
$serpapi_text  = trim($body['serpapi_text']  ?? '');
$generation_id = trim($body['generation_id'] ?? '');
$group_id      = trim($body['group_id']      ?? '');

$settings   = get_user_settings($user_id);
$openai_key = $settings['openai_key']     ?: '';
$perp_key   = $settings['perplexity_key'] ?: '';

if (!$openai_key) { http_response_code(400); echo json_encode(['detail' => 'OpenAI API key is required. Please add it in API Keys settings.']); exit; }

$group_res  = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($group_id) . '&user_id=eq.' . urlencode($user_id) . '&select=content_rules');
$group_data = json_decode($group_res['body'], true);
if (empty($group_data)) { http_response_code(400); echo json_encode(['detail' => 'Content group not found.']); exit; }

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

$final_content = call_openai($system_prompt, $user_prompt, $openai_key);

if ($generation_id) {
    update_generation_row($generation_id, [
        'status'  => 'completed',
        'content' => $final_content,
    ]);
}

echo json_encode([
    'html_content' => $final_content,
    'research_raw' => $research_data,
]);
