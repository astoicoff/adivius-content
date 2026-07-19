<?php
set_time_limit(300);
require_once __DIR__ . '/helpers.php';
set_headers();

// Validate auth + inputs before switching to SSE (errors return normal JSON)
$user    = get_authed_user();
$user_id = $user['id'];
$body    = json_decode(file_get_contents('php://input'), true);
$keyword  = trim($body['keyword']  ?? '');
$group_id = trim($body['group_id'] ?? '');
$model    = trim($body['model']    ?? 'gpt-5.5');

if (!$keyword)  { http_response_code(400); echo json_encode(['detail' => 'Keyword is required.']); exit; }
if (!$group_id) { http_response_code(400); echo json_encode(['detail' => 'Content group is required.']); exit; }

$settings = get_user_settings($user_id);
$perp_key = $settings['perplexity_key'] ?: '';
$serp_key = $settings['serpapi_key']    ?: '';

if (!check_group_access($user_id, $group_id, 'moderator')) {
    http_response_code(403); echo json_encode(['detail' => 'Content group not found or insufficient permissions.']); exit;
}
$group_res  = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($group_id) . '&select=instructions_rules');
$group_data = json_decode($group_res['body'], true);
if (empty($group_data)) { http_response_code(400); echo json_encode(['detail' => 'Content group not found.']); exit; }

$generation_id = trim($body['generation_id'] ?? '');
if ($generation_id) {
    $check = supabase_call('GET', '/rest/v1/content_generations?id=eq.' . urlencode($generation_id) . '&user_id=eq.' . urlencode($user_id) . '&select=id');
    if (empty(json_decode($check['body'], true))) { http_response_code(404); echo json_encode(['detail' => 'Generation not found.']); exit; }
    update_generation_row($generation_id, ['status' => 'generating_instructions', 'model' => $model]);
} else {
    $generation_id = create_generation_row($user_id, $keyword, $group_id);
    update_generation_row($generation_id, ['model' => $model]);
}

// All validation passed — switch to SSE
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

try {
    // 1.3 — Cache check: reuse SerpAPI + Perplexity data if same keyword < 48h old
    $cache_res  = supabase_call('GET',
        '/rest/v1/content_generations'
        . '?group_id=eq.'                   . urlencode($group_id)
        . '&keyword=eq.'                    . urlencode($keyword)
        . '&serpapi_raw=not.is.null'
        . '&competitor_analysis_raw=not.is.null'
        . '&created_at=gte.'                . urlencode(gmdate('Y-m-d\TH:i:s\Z', time() - 48 * 3600))
        . '&select=serpapi_raw,competitor_analysis_raw'
        . '&order=created_at.desc&limit=1'
    );
    $cache_data = ($cache_res['status'] === 200) ? json_decode($cache_res['body'], true) : null;

    if (!empty($cache_data)) {
        $serp_text           = $cache_data[0]['serpapi_raw'];
        $competitor_analysis = $cache_data[0]['competitor_analysis_raw'];
        emit_sse(['type' => 'progress', 'step' => 1, 'message' => 'Using cached research (< 48h old)…']);
    } else {
        // 1. SerpAPI
        emit_sse(['type' => 'progress', 'step' => 1, 'message' => 'Fetching competitor data…']);
        $serp      = call_serpapi($keyword, $serp_key);
        $serp_text = $serp['text'];
        $serp_urls = $serp['urls'];

        // 2. Perplexity competitor analysis
        emit_sse(['type' => 'progress', 'step' => 2, 'message' => 'Analyzing top results…']);
        $url_list            = implode("\n", array_map(fn($u) => "- $u", $serp_urls));
        $competitor_analysis = call_perplexity([
            ['role' => 'system', 'content' => 'You are a deep-scraping research assistant. I will provide you with the exact 5 main competitor URLs from Google. You must visit these 5 URLs and heavily scrape their actual article body text. Do not just read the meta description. Provide a detailed summary of their actual content structure, and calculate an accurate estimation of their total word counts so the AI can determine exactly how long the article should be.'],
            ['role' => 'user',   'content' => "Here are the exact top 5 competitor URLs for the keyword '$keyword'. Thoroughly read the articles on these pages. Give me their detailed content structure and word counts:\n$url_list"],
        ], $perp_key);

        update_generation_row($generation_id, [
            'serpapi_raw'             => $serp_text,
            'competitor_analysis_raw' => $competitor_analysis,
        ]);
    }

    // 3. AI brief generation
    emit_sse(['type' => 'progress', 'step' => 3, 'message' => 'Writing brief…']);
    $system_prompt = $group_data[0]['instructions_rules'];
    $user_prompt   =
        "Keyword: $keyword\n\n"
        . "True Top 5 SERP Competitors (from SerpApi):\n$serp_text\n\n"
        . "Deep Content & Word Count Analysis (from Perplexity):\n$competitor_analysis\n\n"
        . "CRITICAL INSTRUCTIONS:\n"
        . "1. Output ONLY the exact Markdown brief structure requested in the rules. Do not include any conversational filler, estimates, or extra text at the end.\n"
        . "2. If you include an FAQ section, you MUST format the title exactly as an <h2> tag as specified in the rules, but the questions MUST be <h3> and ONLY capitalize the first letter of the first word.\n"
        . "3. In the Output section, you MUST explicitly list ALL 5 competitor URLs provided to you. Do not abbreviate with 'etc.'.";

    $brief = stream_ai($system_prompt, $user_prompt, $model, $settings);

    update_generation_row($generation_id, [
        'status'       => 'instructions_ready',
        'instructions' => $brief,
    ]);

    emit_sse(['type' => 'done', 'generation_id' => $generation_id, 'brief' => $brief, 'serpapi_raw' => $serp_text]);

} catch (Throwable $e) {
    emit_sse(['type' => 'error', 'message' => $e->getMessage()]);
}
