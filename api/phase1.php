<?php
set_time_limit(300);
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];
$body     = json_decode(file_get_contents('php://input'), true);
$keyword  = trim($body['keyword']  ?? '');
$group_id = trim($body['group_id'] ?? '');

if (!$keyword)  { http_response_code(400); echo json_encode(['detail' => 'Keyword is required.']); exit; }
if (!$group_id) { http_response_code(400); echo json_encode(['detail' => 'Content group is required.']); exit; }

$settings    = get_user_settings($user_id);
$openai_key  = $settings['openai_key']     ?: '';
$perp_key    = $settings['perplexity_key'] ?: '';
$serp_key    = $settings['serpapi_key']    ?: '';

if (!$openai_key) { http_response_code(400); echo json_encode(['detail' => 'OpenAI API key is required. Please add it in API Keys settings.']); exit; }

$group_res  = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($group_id) . '&user_id=eq.' . urlencode($user_id) . '&select=instructions_rules');
$group_data = json_decode($group_res['body'], true);
if (empty($group_data)) { http_response_code(400); echo json_encode(['detail' => 'Content group not found.']); exit; }

$generation_id = create_generation_row($user_id, $keyword, $group_id);

// 1. SerpAPI
$serp        = call_serpapi($keyword, $serp_key);
$serp_text   = $serp['text'];
$serp_urls   = $serp['urls'];

// 2. Perplexity competitor analysis
$url_list    = implode("\n", array_map(fn($u) => "- $u", $serp_urls));
$competitor_analysis = call_perplexity([
    ['role' => 'system', 'content' => 'You are a deep-scraping research assistant. I will provide you with the exact 5 main competitor URLs from Google. You must visit these 5 URLs and heavily scrape their actual article body text. Do not just read the meta description. Provide a detailed summary of their actual content structure, and calculate an accurate estimation of their total word counts so the AI can determine exactly how long the article should be.'],
    ['role' => 'user',   'content' => "Here are the exact top 5 competitor URLs for the keyword '$keyword'. Thoroughly read the articles on these pages. Give me their detailed content structure and word counts:\n$url_list"],
], $perp_key);

// 3. OpenAI brief generation
$system_prompt = $group_data[0]['instructions_rules'];
$user_prompt   =
    "Keyword: $keyword\n\n"
    . "True Top 5 SERP Competitors (from SerpApi):\n$serp_text\n\n"
    . "Deep Content & Word Count Analysis (from Perplexity):\n$competitor_analysis\n\n"
    . "CRITICAL INSTRUCTIONS:\n"
    . "1. Output ONLY the exact Markdown brief structure requested in the rules. Do not include any conversational filler, estimates, or extra text at the end.\n"
    . "2. If you include an FAQ section, you MUST format the title exactly as an <h2> tag as specified in the rules, but the questions MUST be <h3> and ONLY capitalize the first letter of the first word.\n"
    . "3. In the Output section, you MUST explicitly list ALL 5 competitor URLs provided to you. Do not abbreviate with 'etc.'.";

$brief = call_openai($system_prompt, $user_prompt, $openai_key);

if ($generation_id) {
    update_generation_row($generation_id, [
        'status'      => 'instructions_ready',
        'instructions' => $brief,
        'serpapi_raw' => $serp_text,
    ]);
}

echo json_encode([
    'brief'          => $brief,
    'serpapi_raw'    => $serp_text,
    'perplexity_raw' => $competitor_analysis,
    'generation_id'  => $generation_id,
]);
