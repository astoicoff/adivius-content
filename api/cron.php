<?php
// Protected cron endpoint — run one overdue scheduled generation per call.
// cPanel cron: */5 * * * * curl -s "https://content.adivius.com/api/cron.php?token=YOUR_SECRET" > /dev/null 2>&1
set_time_limit(600);
require_once __DIR__ . '/helpers.php';

$token = $_GET['token'] ?? '';
if (!defined('CRON_SECRET') || !$token || !hash_equals(CRON_SECRET, $token)) {
    http_response_code(403); echo json_encode(['detail' => 'Forbidden.']); exit;
}

header('Content-Type: application/json');

function cron_serpapi($keyword, $api_key) {
    if (!$api_key) {
        return ['text' => '[No SerpAPI key]', 'urls' => [
            'https://example1.com','https://example2.com','https://example3.com',
            'https://example4.com','https://example5.com'
        ]];
    }
    $ch = curl_init('https://serpapi.com/search?' . http_build_query([
        'q' => $keyword, 'engine' => 'google', 'api_key' => $api_key, 'num' => 10,
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200) throw new RuntimeException('SerpApi error: ' . $body);
    $results = json_decode($body, true);
    if (isset($results['error'])) throw new RuntimeException('SerpApi: ' . $results['error']);
    $text = ''; $urls = [];
    foreach ($results['organic_results'] ?? [] as $item) {
        $link = $item['link'] ?? null; $snippet = $item['snippet'] ?? '';
        if ($link && strpos($link, 'https://www.youtube') !== 0) {
            $urls[] = $link;
            $text  .= "- url: $link\n  snippet: $snippet\n";
        }
        if (count($urls) >= 5) break;
    }
    if (empty($urls)) throw new RuntimeException('SerpApi returned no organic URLs.');
    return ['text' => $text, 'urls' => $urls];
}

function cron_perplexity($messages, $api_key) {
    if (!$api_key) return '[No Perplexity key]';
    $ch = curl_init('https://api.perplexity.ai/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode(['model' => 'sonar-pro', 'messages' => $messages]),
        CURLOPT_TIMEOUT        => 120,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200) throw new RuntimeException('Perplexity error HTTP ' . $status);
    return json_decode($body, true)['choices'][0]['message']['content'] ?? '';
}

function cron_openai($system, $user_msg, $api_key) {
    if (!$api_key) throw new RuntimeException('OpenAI API key not configured.');
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'model'    => 'gpt-5',
            'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user_msg]],
        ]),
        CURLOPT_TIMEOUT => 300,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200) throw new RuntimeException('OpenAI error HTTP ' . $status);
    return json_decode($body, true)['choices'][0]['message']['content'] ?? '';
}

$res  = supabase_call('GET',
    '/rest/v1/content_generations?status=eq.scheduled&scheduled_for=lte.'
    . urlencode(date('c'))
    . '&select=id,user_id,keyword,group_id&order=scheduled_for.asc&limit=1'
);
$jobs = json_decode($res['body'], true) ?: [];

if (empty($jobs)) { echo json_encode(['message' => 'No scheduled jobs due.']); exit; }

$job   = $jobs[0];
$jobId = $job['id'];
update_generation_row($jobId, ['status' => 'generating_instructions']);

try {
    $s  = get_user_settings($job['user_id']);
    $ok = $s['openai_key']     ?: '';
    $pk = $s['perplexity_key'] ?: '';
    $sk = $s['serpapi_key']    ?: '';
    if (!$ok) throw new RuntimeException('No OpenAI API key for this user.');

    $grpRes  = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($job['group_id']) . '&select=instructions_rules,content_rules');
    $grpData = json_decode($grpRes['body'], true);
    if (empty($grpData)) throw new RuntimeException('Content group not found.');
    $grp = $grpData[0]; $kw = $job['keyword'];

    // Phase 1
    $serp    = cron_serpapi($kw, $sk);
    $urlList = implode("\n", array_map(fn($u) => "- $u", $serp['urls']));
    $compAnalysis = cron_perplexity([
        ['role' => 'system', 'content' => 'You are a deep-scraping research assistant. Visit these competitor URLs and scrape their article body text. Provide a detailed summary of their content structure and word counts.'],
        ['role' => 'user',   'content' => "Top 5 competitor URLs for '$kw'. Analyze their content structure and word counts:\n$urlList"],
    ], $pk);
    $brief = cron_openai($grp['instructions_rules'],
        "Keyword: $kw\n\nSERP Competitors:\n{$serp['text']}\n\nCompetitor Analysis:\n$compAnalysis\n\nOutput ONLY the Markdown brief structure. List ALL 5 competitor URLs.", $ok);
    update_generation_row($jobId, ['status' => 'instructions_ready', 'instructions' => $brief, 'serpapi_raw' => $serp['text']]);

    // Phase 2
    update_generation_row($jobId, ['status' => 'generating_content']);
    $research = cron_perplexity([
        ['role' => 'system', 'content' => 'You are a factual research assistant. Provide EEAT insights and verify claims.'],
        ['role' => 'user',   'content' => "Research this keyword deeply for an SEO article: $kw"],
    ], $pk);
    $content = cron_openai($grp['content_rules'],
        "Think step-by-step using the E-E-A-T framework, then output WordPress HTML.\n\nBrief:\n$brief\n\nSERP:\n{$serp['text']}\n\nResearch:\n$research", $ok);
    update_generation_row($jobId, ['status' => 'completed', 'content' => $content]);

    echo json_encode(['status' => 'completed', 'id' => $jobId, 'keyword' => $kw]);

} catch (Throwable $e) {
    update_generation_row($jobId, ['status' => 'failed']);
    echo json_encode(['status' => 'failed', 'id' => $jobId, 'error' => $e->getMessage()]);
}
