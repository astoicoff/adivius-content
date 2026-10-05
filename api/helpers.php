<?php
// Load from env vars (Vercel) or fall back to adcontent_config.php (cPanel)
if (!defined('SUPABASE_URL')) {
    $cfg = __DIR__ . '/../../adcontent_config.php';
    if (file_exists($cfg)) require_once $cfg;
}
if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL',         getenv('SUPABASE_URL')         ?: '');
    define('SUPABASE_ANON_KEY',    getenv('SUPABASE_ANON_KEY')    ?: '');
    define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY') ?: '');
    define('DIRECTIVES_DIR',       getenv('DIRECTIVES_DIR')       ?: __DIR__ . '/../directives');
    define('NEURONWRITER_API_KEY', getenv('NEURONWRITER_API_KEY') ?: '');
    define('NEURONWRITER_API_URL', getenv('NEURONWRITER_API_URL') ?: 'https://app.neuronwriter.com/neuron-api/0.5/writer');
    define('NUCLEUS_SERVICE_TOKEN', getenv('NUCLEUS_SERVICE_TOKEN') ?: '');
    define('NUCLEUS_BASE_URL',      getenv('NUCLEUS_BASE_URL')      ?: '');
    define('NUCLEUS_TOOL_SLUG',     getenv('NUCLEUS_TOOL_SLUG')     ?: '');
    // Auth uses Nucleus's shared Supabase project; data stays on Content Creator's own project.
    define('AUTH_SUPABASE_URL',      getenv('AUTH_SUPABASE_URL')      ?: '');
    define('AUTH_SUPABASE_ANON_KEY', getenv('AUTH_SUPABASE_ANON_KEY') ?: '');
}

// The hub contract (generated from scripts/hub-contract.mjs): specs and
// HUB_BLOG_PILLARS, used by the Nucleus handoff builder below.
require_once __DIR__ . '/contracts/hub_contract.php';

// Model defaults — the one place to change when a new model ships.
// Verified 2026-10-05 against /v1/models: GPT-6.1 Sol (released 2026-09-29,
// near GPT-6 Astra on professional work) answers the exact Chat Completions
// request shape used below (no temperature/reasoning params), streaming
// included, at GPT-6 Sol's price ($2/$10 per 1M tokens; cached input $0.10).
// GPT Image 2.5 Flare takes the same size/quality/output_format params as
// gpt-image-2 at the same token price, with higher quality and lower latency.
const DEFAULT_TEXT_MODEL = 'gpt-6.1-sol';
const IMAGE_MODEL        = 'gpt-image-2.5-flare';

function set_headers() {
    header('Content-Type: application/json');
    $allowed = ['https://adivius.com', 'https://www.adivius.com', 'http://localhost:8000',
                getenv('VERCEL_URL') ? 'https://' . getenv('VERCEL_URL') : ''];
    $allowed  = array_filter($allowed);
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowed, true)) header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
}

function get_authed_user() {
    // $_SERVER works in FastCGI/Vercel; getallheaders() works in Apache/cPanel
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$auth && function_exists('getallheaders')) {
        $h    = getallheaders();
        $auth = $h['Authorization'] ?? $h['authorization'] ?? '';
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        http_response_code(401);
        echo json_encode(['detail' => 'Missing authorization token.']);
        exit;
    }
    // Use a direct curl call — supabase_call() injects the service key which
    // would conflict with the user token in the Authorization header.
    // Auth validates against Nucleus's shared Supabase project.
    $authUrl = (AUTH_SUPABASE_URL ?: SUPABASE_URL) . '/auth/v1/user';
    $authKey  = AUTH_SUPABASE_ANON_KEY ?: SUPABASE_ANON_KEY;
    $ch = curl_init($authUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $m[1],
            'apikey: ' . $authKey,
        ],
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200) {
        http_response_code(401);
        echo json_encode(['detail' => 'Invalid or expired token. Please sign in again.']);
        exit;
    }
    return json_decode($body, true);
}

function supabase_call($method, $path, $body = null, $extra_headers = []) {
    $ch = curl_init(SUPABASE_URL . $path);
    $headers = array_merge([
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
    ], $extra_headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $response];
}

function get_user_settings($user_id) {
    $res  = supabase_call('GET', '/rest/v1/user_settings?select=*&user_id=eq.' . urlencode($user_id));
    $data = json_decode($res['body'], true);
    return $data[0] ?? [];
}

// Returns 'owner'|'moderator'|'viewer', or false if the user has no access.
function check_group_access(string $user_id, string $group_id, string $min_role = 'viewer'): string|false {
    $hierarchy = ['viewer' => 1, 'moderator' => 2, 'owner' => 3];

    $own = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($group_id) . '&user_id=eq.' . urlencode($user_id) . '&select=id');
    if (!empty(json_decode($own['body'], true))) return 'owner';

    $mem  = supabase_call('GET', '/rest/v1/content_group_members?group_id=eq.' . urlencode($group_id) . '&user_id=eq.' . urlencode($user_id) . '&select=role');
    $rows = json_decode($mem['body'], true);
    if (empty($rows)) return false;

    $role = $rows[0]['role'];
    return ($hierarchy[$role] ?? 0) >= ($hierarchy[$min_role] ?? 0) ? $role : false;
}

function upsert_user_settings($user_id, $openai_key, $perplexity_key, $serpapi_key, $claude_key = '', $gemini_key = '') {
    return supabase_call('POST', '/rest/v1/user_settings?on_conflict=user_id', [
        'user_id'        => $user_id,
        'openai_key'     => $openai_key,
        'perplexity_key' => $perplexity_key,
        'serpapi_key'    => $serpapi_key,
        'claude_key'     => $claude_key,
        'gemini_key'     => $gemini_key,
    ], ['Prefer: resolution=merge-duplicates,return=representation']);
}

function create_generation_row($user_id, $keyword, $group_id = null) {
    $row = ['user_id' => $user_id, 'keyword' => $keyword, 'status' => 'generating_instructions'];
    if ($group_id) {
        $row['group_id'] = $group_id;
        $gRes  = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($group_id) . '&select=client_id,site_id');
        $gData = json_decode($gRes['body'], true);
        if (!empty($gData[0]['client_id'])) $row['client_id'] = $gData[0]['client_id'];
        if (!empty($gData[0]['site_id']))   $row['site_id']   = $gData[0]['site_id'];
    }
    $res  = supabase_call('POST', '/rest/v1/content_generations', $row, ['Prefer: return=representation']);
    $data = json_decode($res['body'], true);
    return $data[0]['id'] ?? '';
}

function update_generation_row($id, $fields) {
    $fields['updated_at'] = date('c');
    supabase_call('PATCH', '/rest/v1/content_generations?id=eq.' . urlencode($id), $fields);
}

function call_serpapi($keyword, $api_key) {
    if (!$api_key) {
        return ['text' => '[Mock SerpApi Data: Missing API Key]', 'urls' => [
            'https://example1.com','https://example2.com','https://example3.com',
            'https://example4.com','https://example5.com',
        ]];
    }
    $ch = curl_init('https://serpapi.com/search?' . http_build_query([
        'q' => $keyword, 'engine' => 'google', 'api_key' => $api_key, 'num' => 10,
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200) throw new \RuntimeException('SerpApi HTTP Error: ' . $body);
    $results = json_decode($body, true);
    if (isset($results['error'])) throw new \RuntimeException('SerpApi Error: ' . $results['error']);
    $text = ''; $urls = [];
    foreach ($results['organic_results'] ?? [] as $item) {
        $link    = $item['link'] ?? null;
        $snippet = $item['snippet'] ?? 'No snippet available';
        if ($link && strpos($link, 'https://www.youtube') !== 0) {
            $urls[] = $link;
            $text  .= "- url: $link\n  snippet: $snippet\n";
        }
        if (count($urls) >= 5) break;
    }
    if (empty($urls)) throw new \RuntimeException('SerpApi returned no valid organic URLs.');
    return ['text' => $text, 'urls' => $urls];
}

function call_perplexity($messages, $api_key) {
    if (!$api_key) return '[Mock Perplexity Data: Missing API Key]';
    $ch = curl_init('https://api.perplexity.ai/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode(['model' => 'sonar-pro', 'messages' => $messages]),
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200) throw new \RuntimeException('Perplexity API Error: ' . $body);
    return json_decode($body, true)['choices'][0]['message']['content'];
}

// Strip JPEG metadata segments — APP1 (EXIF/XMP), APP11 (JUMBF/C2PA), COM —
// before hosting. OpenAI embeds C2PA credentials + IPTC DigitalSourceType
// "trainedAlgorithmicMedia", which Google reads to label images as
// AI-generated in search results. Lossless: the entropy-coded image data is
// copied untouched (APP0/JFIF, ICC and Adobe color segments are kept).
// Returns the input unchanged on any parse anomaly — generation must never
// fail over metadata hygiene.
function strip_jpeg_metadata($bytes) {
    $len = strlen($bytes);
    if ($len < 4 || ord($bytes[0]) !== 0xFF || ord($bytes[1]) !== 0xD8) return $bytes;
    $out = "\xFF\xD8";
    $i   = 2;
    while ($i + 4 <= $len) {
        if (ord($bytes[$i]) !== 0xFF) return $bytes;
        $marker = ord($bytes[$i + 1]);
        if ($marker === 0xDA) return $out . substr($bytes, $i);   // SOS: rest verbatim
        $segLen = (ord($bytes[$i + 2]) << 8) | ord($bytes[$i + 3]);
        $segEnd = $i + 2 + $segLen;
        if ($segEnd > $len) return $bytes;
        if (!in_array($marker, [0xE1, 0xEB, 0xFE], true)) {
            $out .= substr($bytes, $i, $segEnd - $i);
        }
        $i = $segEnd;
    }
    return $bytes;
}

// Article header: `h1:` / `title:` / `url:` / `description:` lines above the
// HTML body. Every server-side reader of stored content goes through
// parse_content_meta() (webhook, SEO rewrite, Nucleus handoff), and
// parseContentMeta() in view-content.js mirrors it — change both together.
// "Meta description:" is accepted too; models write it that way unprompted.
const CONTENT_META_LINE = '/^(h1|title|url|(?:meta[ _-]?)?description)\s*:\s*(.+)$/i';

function parse_content_meta($raw) {
    $lines     = explode("\n", (string)$raw);
    $meta      = [];
    $bodyStart = 0;
    foreach ($lines as $i => $line) {
        $trim = trim($line);
        if (!$trim) { if (!empty($meta)) { $bodyStart = $i + 1; break; } continue; }
        if ($trim[0] === '<') { $bodyStart = $i; break; }
        if (preg_match(CONTENT_META_LINE, $trim, $m)) {
            $key = strtolower($m[1]);
            if (str_ends_with($key, 'description')) $key = 'description';
            $meta[$key] = trim($m[2]);
            $bodyStart  = $i + 1;
        } else break;
    }
    $body = trim(implode("\n", array_slice($lines, $bodyStart)));

    // The header exactly as stored, so a rewrite of the body can put it back.
    $prefix           = $bodyStart > 0 ? implode("\n", array_slice($lines, 0, $bodyStart)) . "\n" : '';
    $description_line = $meta['description'] ?? null;

    // Enrich meta for downstream consumers (webhooks, Nucleus). `url` is the
    // URL slug in this system; downstream schemas often name that field `slug`.
    // `description` is the generated line when the article has one, else the
    // first ~155 chars of the body — webhooks require it to be a string.
    $meta['slug']        = $meta['url'] ?? '';
    $meta['description'] = $description_line ?? extract_meta_description($body, $meta);

    return ['meta' => $meta, 'body' => $body, 'prefix' => $prefix, 'description_line' => $description_line];
}

function extract_meta_description($body, $meta = []) {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$body)));
    if ($text === '') $text = $meta['title'] ?? $meta['h1'] ?? '';
    if ($text === '') return '';
    $max = 155;
    if (mb_strlen($text) <= $max) return $text;
    $cut = mb_substr($text, 0, $max);
    $sp  = mb_strrpos($cut, ' ');
    if ($sp !== false && $sp > 100) $cut = mb_substr($cut, 0, $sp);
    return $cut . '…';
}

// House rule for adivius.com (2026-10-04): no em dashes anywhere — the site's
// build fails on one. Mirrors Nucleus's own replacement (" - "), and catches
// the HTML entity forms too, since those render as the same character.
function no_em_dashes(?string $s): ?string {
    if ($s === null) return null;
    return preg_replace('/[ \t]*(?:\x{2014}|&mdash;|&#8212;|&#x2014;)[ \t]*/iu', ' - ', $s);
}

// Visible text of an HTML fragment, whitespace collapsed.
function html_text(string $html): string {
    $html = preg_replace('/<\/(p|li|h[1-6]|div|tr)>|<br\s*\/?>/i', ' ', $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
}

// Cut at a word boundary to at most $max characters (no ellipsis — this is a
// meta description, not a teaser).
function clamp_words(string $s, int $max): string {
    $s = trim($s);
    if (mb_strlen($s) <= $max) return $s;
    $cut = mb_substr($s, 0, $max);
    $sp  = mb_strrpos($cut, ' ');
    return rtrim($sp !== false && $sp > $max * 0.6 ? mb_substr($cut, 0, $sp) : $cut, " ,;:-");
}

// FAQ pairs from an article's FAQ section: the first H2 whose text names one
// ("FAQ", "FAQs", "Frequently asked…"), each H3 inside it a question, and the
// content up to the next H3 its answer. Ends at the next H2. [] if none.
function extract_faq(string $html): array {
    if (!preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/is', $html, $h2s, PREG_OFFSET_CAPTURE)) return [];
    $start = null;
    $end   = strlen($html);
    foreach ($h2s[0] as $i => [$tag, $offset]) {
        if ($start === null) {
            if (preg_match('/\bFAQs?\b|frequently\s+asked/i', html_text($h2s[1][$i][0]))) $start = $offset + strlen($tag);
        } else {
            $end = $offset;
            break;
        }
    }
    if ($start === null) return [];

    $parts = preg_split('/(<h3\b[^>]*>.*?<\/h3>)/is', substr($html, $start, $end - $start), -1, PREG_SPLIT_DELIM_CAPTURE);
    $faq   = [];
    for ($i = 1; $i < count($parts); $i += 2) {
        $q = html_text($parts[$i]);
        $a = html_text($parts[$i + 1] ?? '');
        if ($q !== '' && $a !== '') $faq[] = ['q' => $q, 'a' => $a];
    }
    return $faq;
}

// Stored content after a Nucleus edit (content-updated callback). Keeps the
// header lines Nucleus doesn't send back (description, and the title line).
// The handoff sends the h1 as `title`, falling back to the title line when
// there is no h1, so an edited title lands on that same line.
function apply_nucleus_edit(string $stored_content, string $title, string $slug, string $body_html): string {
    $stored = parse_content_meta($stored_content);
    $header = [
        'h1'          => $stored['meta']['h1']    ?? null,
        'title'       => $stored['meta']['title'] ?? null,
        'url'         => $stored['meta']['url']   ?? null,
        'description' => $stored['description_line'],
    ];
    if ($title !== '') {
        if ($header['h1'] !== null || $header['title'] === null) $header['h1'] = $title;
        else                                                      $header['title'] = $title;
    }
    if ($slug !== '') $header['url'] = $slug;

    $content = '';
    foreach ($header as $key => $value) {
        if ($value !== null && $value !== '') $content .= "{$key}: {$value}\n";
    }
    return $content . "\n" . $body_html;
}

// The POST /api/inbound/content-ready body (HUB_CONTENT_READY_SPEC): every
// key present, null or [] when unknown. Pure — $gen is the generation row
// (id, keyword, content, site_id, client_id), $group its content group
// (pillar, author_slug).
function build_content_ready_payload(array $gen, array $group): array {
    $parsed = parse_content_meta($gen['content'] ?? '');
    $meta   = $parsed['meta'];
    $desc   = $parsed['description_line'];
    $pillar = $group['pillar'] ?? null;

    return [
        // The on-page heading. A site that renders its own <title> takes
        // meta_title for that, so h1 wins here (it used to be the title line).
        'title'            => no_em_dashes($meta['h1'] ?? $meta['title'] ?? (string)($gen['keyword'] ?? '')),
        'site_id'          => $gen['site_id']   ?? null,
        'client_id'        => $gen['client_id'] ?? null,
        'body_html'        => no_em_dashes($parsed['body']),
        'body_markdown'    => null,
        'slug'             => ($meta['url'] ?? '') !== '' ? $meta['url'] : null,
        'excerpt'          => null,
        'source_ref'       => isset($gen['id']) ? (string)$gen['id'] : null,
        'meta_title'       => isset($meta['title']) ? no_em_dashes($meta['title']) : null,
        // null when the article has no description line — Nucleus derives one.
        'meta_description' => $desc !== null ? clamp_words(no_em_dashes($desc), 155) : null,
        'author_slug'      => trim((string)($group['author_slug'] ?? '')) ?: null,
        'pillar'           => in_array($pillar, HUB_BLOG_PILLARS, true) ? $pillar : null,
        'tags'             => [],
        'faq'              => array_map(fn($f) => ['q' => no_em_dashes($f['q']), 'a' => no_em_dashes($f['a'])],
                                        extract_faq($parsed['body'])),
        // Filled once images are linked to articles (roadmap v3 Phase 8).
        'featured_image'   => null,
        'images'           => [],
    ];
}

function fire_webhook($url, $payload, $extra_headers = []) {
    $delays   = [0, 2, 5];
    $attempts = 0;
    $lastErr  = null;

    // Merge user-supplied headers with defaults. Content-Type and User-Agent
    // are reserved so they can't be overridden by group config.
    $reserved = ['content-type', 'user-agent'];
    $headers  = ['Content-Type: application/json', 'User-Agent: AdiviusContentCreator/1.0'];
    if (is_array($extra_headers)) {
        foreach ($extra_headers as $name => $value) {
            $name  = trim((string)$name);
            $value = trim((string)$value);
            if ($name === '' || $value === '')                     continue;
            if (!preg_match('/^[A-Za-z0-9\-_]+$/', $name))         continue;
            if (in_array(strtolower($name), $reserved, true))      continue;
            $headers[] = $name . ': ' . $value;
        }
    }

    foreach ($delays as $delay) {
        if ($delay) sleep($delay);
        $attempts++;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if (!$err && $code >= 200 && $code < 300) {
            return ['ok' => true, 'attempts' => $attempts, 'status' => $code];
        }
        $lastErr = $err ?: ('HTTP ' . $code . ': ' . substr((string)$body, 0, 300));
    }
    return ['ok' => false, 'attempts' => $attempts, 'error' => $lastErr];
}

function call_openai($system_prompt, $user_prompt, $api_key, $model = DEFAULT_TEXT_MODEL) {
    if (!$api_key) { http_response_code(400); echo json_encode(['detail' => 'OpenAI API key is required. Please add it in API Keys settings.']); exit; }
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user',   'content' => $user_prompt],
            ],
        ]),
        CURLOPT_TIMEOUT => 300,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200) { http_response_code(500); echo json_encode(['detail' => 'OpenAI API Error: ' . $body]); exit; }
    return json_decode($body, true)['choices'][0]['message']['content'] ?? 'Error: empty response.';
}

// Claude request shape, shared by the streaming and non-streaming paths.
//
// Opus 5 / Sonnet 5 think by default when `thinking` is omitted (4.7/4.6 did
// not), and max_tokens caps thinking + answer together — so the streaming path,
// which writes whole articles, gets 64K of headroom. The non-streaming path
// stays at 16K: that keeps it under the API's long-request limit for
// non-streamed calls, and it only ever returns one edited HTML document.
//
// Opus 5's safety classifiers can decline a request (HTTP 200,
// stop_reason "refusal"). `fallbacks: "default"` re-runs a declined request on
// Anthropic's recommended substitute server-side. It's documented for Opus 5
// specifically, so it is only sent for that model.
function claude_request(string $model, string $system_prompt, string $user_prompt, bool $stream, string $api_key): array {
    $body = [
        'model'      => $model,
        'max_tokens' => $stream ? 64000 : 16000,
        'system'     => $system_prompt,
        'messages'   => [['role' => 'user', 'content' => $user_prompt]],
    ];
    $headers = [
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01',
        'Content-Type: application/json',
    ];
    if ($model === 'claude-opus-5') {
        $body['fallbacks'] = 'default';
        $headers[]         = 'anthropic-beta: server-side-fallback-2026-07-01';
    }
    if ($stream) $body['stream'] = true;
    return [$headers, json_encode($body)];
}

function claude_refusal_message(?array $stop_details): string {
    $why = $stop_details['explanation'] ?? $stop_details['category'] ?? null;
    return 'Claude declined this request' . ($why ? ' (' . $why . ')' : '') . '. Try again, or pick a different model.';
}

function call_claude($system_prompt, $user_prompt, $api_key, $model = 'claude-sonnet-5') {
    if (!$api_key) { http_response_code(400); echo json_encode(['detail' => 'Anthropic API key is required. Please add it in API Keys settings.']); exit; }
    [$headers, $payload] = claude_request($model, $system_prompt, $user_prompt, false, $api_key);
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 300,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200) { http_response_code(500); echo json_encode(['detail' => 'Claude API Error: ' . $body]); exit; }
    $data = json_decode($body, true);
    if (($data['stop_reason'] ?? '') === 'refusal') {
        http_response_code(422); echo json_encode(['detail' => claude_refusal_message($data['stop_details'] ?? null)]); exit;
    }
    // With thinking on, content[0] is usually a thinking block — collect the text blocks.
    $text = '';
    foreach ($data['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text') $text .= $block['text'];
    }
    if ($text === '') { http_response_code(500); echo json_encode(['detail' => 'Claude returned an empty response.']); exit; }
    return $text;
}

function call_gemini($system_prompt, $user_prompt, $api_key, $model = 'gemini-2.5-pro') {
    if (!$api_key) { http_response_code(400); echo json_encode(['detail' => 'Google API key is required. Please add it in API Keys settings.']); exit; }
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($api_key);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'system_instruction' => ['parts' => [['text' => $system_prompt]]],
            'contents'           => [['role' => 'user', 'parts' => [['text' => $user_prompt]]]],
            'generationConfig'   => ['maxOutputTokens' => 8192],
        ]),
        CURLOPT_TIMEOUT => 300,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200) { http_response_code(500); echo json_encode(['detail' => 'Gemini API Error: ' . $body]); exit; }
    return json_decode($body, true)['candidates'][0]['content']['parts'][0]['text'] ?? 'Error: empty response.';
}

// Routes to the correct AI provider based on model string prefix.
function call_ai($system_prompt, $user_prompt, $model, $settings) {
    if (str_starts_with($model, 'claude-')) return call_claude($system_prompt, $user_prompt, $settings['claude_key'] ?? '', $model);
    if (str_starts_with($model, 'gemini-')) return call_gemini($system_prompt, $user_prompt, $settings['gemini_key'] ?? '', $model);
    return call_openai($system_prompt, $user_prompt, $settings['openai_key'] ?? '', $model);
}

// ── SSE streaming helpers ────────────────────────────────────────────────────

function emit_sse(array $payload): void {
    echo 'data: ' . json_encode($payload) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}

function stream_openai(string $system_prompt, string $user_prompt, string $api_key, string $model): string {
    if (!$api_key) throw new \RuntimeException('OpenAI API key is required. Please add it in API Keys settings.');
    $full = '';
    $buf  = '';
    $raw  = '';   // on a 4xx OpenAI sends a plain JSON error body, not SSE lines
    $ch   = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_HTTPHEADER    => ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS    => json_encode([
            'model'    => $model,
            'stream'   => true,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user',   'content' => $user_prompt],
            ],
        ]),
        CURLOPT_TIMEOUT       => 300,
        CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$full, &$buf, &$raw) {
            $buf .= $data;
            if (strlen($raw) < 2000) $raw .= $data;
            $lines = explode("\n", $buf);
            $buf   = array_pop($lines);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!str_starts_with($line, 'data: ')) continue;
                $json = substr($line, 6);
                if ($json === '[DONE]') continue;
                $tok = json_decode($json, true)['choices'][0]['delta']['content'] ?? null;
                if ($tok !== null && $tok !== '') { $full .= $tok; emit_sse(['type' => 'token', 'text' => $tok]); }
            }
            return strlen($data);
        },
    ]);
    curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err || $code >= 400) {
        // Surface OpenAI's own reason ("model not found", "quota exceeded"...)
        // rather than a bare status code.
        $msg = json_decode($raw, true)['error']['message'] ?? null;
        throw new \RuntimeException('OpenAI error: ' . ($err ?: 'HTTP ' . $code . ($msg ? ' — ' . $msg : '')));
    }
    return $full;
}

function stream_claude(string $system_prompt, string $user_prompt, string $api_key, string $model): string {
    if (!$api_key) throw new \RuntimeException('Anthropic API key is required. Please add it in API Keys settings.');
    $full         = '';
    $buf          = '';
    $raw          = '';     // on a 4xx the body is plain JSON, not SSE
    $stop_reason  = null;
    $stop_details = null;
    [$headers, $payload] = claude_request($model, $system_prompt, $user_prompt, true, $api_key);
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_HTTPHEADER    => $headers,
        CURLOPT_POSTFIELDS    => $payload,
        CURLOPT_TIMEOUT       => 300,
        CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$full, &$buf, &$raw, &$stop_reason, &$stop_details) {
            $buf .= $data;
            if (strlen($raw) < 2000) $raw .= $data;
            $lines = explode("\n", $buf);
            $buf   = array_pop($lines);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!str_starts_with($line, 'data: ')) continue;
                $ev   = json_decode(substr($line, 6), true);
                $type = $ev['type'] ?? '';
                if ($type === 'message_delta') {
                    $stop_reason  = $ev['delta']['stop_reason']  ?? $stop_reason;
                    $stop_details = $ev['delta']['stop_details'] ?? $stop_details;
                    continue;
                }
                // Only text deltas reach the page — thinking deltas are skipped.
                if ($type !== 'content_block_delta' || ($ev['delta']['type'] ?? '') !== 'text_delta') continue;
                $tok = $ev['delta']['text'] ?? null;
                if ($tok !== null && $tok !== '') { $full .= $tok; emit_sse(['type' => 'token', 'text' => $tok]); }
            }
            return strlen($data);
        },
    ]);
    curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err || $code >= 400) {
        $msg = json_decode($raw, true)['error']['message'] ?? null;
        throw new \RuntimeException('Claude error: ' . ($err ?: 'HTTP ' . $code . ($msg ? ' — ' . $msg : '')));
    }
    // A refusal can land mid-stream; the partial text must not be saved as a finished article.
    if ($stop_reason === 'refusal') throw new \RuntimeException(claude_refusal_message($stop_details));
    if ($stop_reason === 'max_tokens') throw new \RuntimeException('Claude hit its output limit before finishing. Try again, or use a shorter brief.');
    return $full;
}

function stream_gemini(string $system_prompt, string $user_prompt, string $api_key, string $model): string {
    if (!$api_key) throw new \RuntimeException('Google API key is required. Please add it in API Keys settings.');
    $full = '';
    $buf  = '';
    $url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model)
          . ':streamGenerateContent?key=' . urlencode($api_key) . '&alt=sse';
    $ch   = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_HTTPHEADER    => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS    => json_encode([
            'system_instruction' => ['parts' => [['text' => $system_prompt]]],
            'contents'           => [['role' => 'user', 'parts' => [['text' => $user_prompt]]]],
            'generationConfig'   => ['maxOutputTokens' => 8192],
        ]),
        CURLOPT_TIMEOUT       => 300,
        CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$full, &$buf) {
            $buf .= $data;
            $lines = explode("\n", $buf);
            $buf   = array_pop($lines);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!str_starts_with($line, 'data: ')) continue;
                $tok = json_decode(substr($line, 6), true)['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($tok !== null && $tok !== '') { $full .= $tok; emit_sse(['type' => 'token', 'text' => $tok]); }
            }
            return strlen($data);
        },
    ]);
    curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err || $code >= 400) throw new \RuntimeException('Gemini stream error: ' . ($err ?: 'HTTP ' . $code));
    return $full;
}

function stream_ai(string $system_prompt, string $user_prompt, string $model, array $settings): string {
    if (str_starts_with($model, 'claude-')) return stream_claude($system_prompt, $user_prompt, $settings['claude_key'] ?? '', $model);
    if (str_starts_with($model, 'gemini-')) return stream_gemini($system_prompt, $user_prompt, $settings['gemini_key'] ?? '', $model);
    return stream_openai($system_prompt, $user_prompt, $settings['openai_key'] ?? '', $model);
}
