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
        $gRes  = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($group_id) . '&select=client_id');
        $gData = json_decode($gRes['body'], true);
        if (!empty($gData[0]['client_id'])) $row['client_id'] = $gData[0]['client_id'];
    }
    $res  = supabase_call('POST', '/rest/v1/content_generations', $row, ['Prefer: return=representation']);
    $data = json_decode($res['body'], true);
    return $data[0]['id'] ?? '';
}

function update_generation_row($id, $fields) {
    $fields['updated_at'] = date('c');
    supabase_call('PATCH', '/rest/v1/content_generations?id=eq.' . urlencode($id), $fields);
}

function load_directive($filename) {
    $path = DIRECTIVES_DIR . '/' . $filename;
    if (!file_exists($path)) {
        http_response_code(500);
        echo json_encode(['detail' => "Directive `$filename` not found."]);
        exit;
    }
    return file_get_contents($path);
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

function parse_content_meta($raw) {
    $lines     = explode("\n", (string)$raw);
    $meta      = [];
    $bodyStart = 0;
    foreach ($lines as $i => $line) {
        $trim = trim($line);
        if (!$trim) { if (!empty($meta)) { $bodyStart = $i + 1; break; } continue; }
        if ($trim[0] === '<') { $bodyStart = $i; break; }
        if (preg_match('/^(h1|title|url)\s*:\s*(.+)$/i', $trim, $m)) {
            $meta[strtolower($m[1])] = trim($m[2]);
            $bodyStart = $i + 1;
        } else break;
    }
    return ['meta' => $meta, 'body' => trim(implode("\n", array_slice($lines, $bodyStart)))];
}

function fire_webhook($url, $payload) {
    $delays   = [0, 2, 5];
    $attempts = 0;
    $lastErr  = null;
    foreach ($delays as $delay) {
        if ($delay) sleep($delay);
        $attempts++;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'User-Agent: AdiviusContentCreator/1.0',
            ],
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

function call_openai($system_prompt, $user_prompt, $api_key, $model = 'gpt-5') {
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

function call_claude($system_prompt, $user_prompt, $api_key, $model = 'claude-sonnet-4-6') {
    if (!$api_key) { http_response_code(400); echo json_encode(['detail' => 'Anthropic API key is required. Please add it in API Keys settings.']); exit; }
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model'      => $model,
            'max_tokens' => 16000,
            'system'     => $system_prompt,
            'messages'   => [['role' => 'user', 'content' => $user_prompt]],
        ]),
        CURLOPT_TIMEOUT => 300,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200) { http_response_code(500); echo json_encode(['detail' => 'Claude API Error: ' . $body]); exit; }
    return json_decode($body, true)['content'][0]['text'] ?? 'Error: empty response.';
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
        CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$full, &$buf) {
            $buf .= $data;
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
    if ($err || $code >= 400) throw new \RuntimeException('OpenAI stream error: ' . ($err ?: 'HTTP ' . $code));
    return $full;
}

function stream_claude(string $system_prompt, string $user_prompt, string $api_key, string $model): string {
    if (!$api_key) throw new \RuntimeException('Anthropic API key is required. Please add it in API Keys settings.');
    $full = '';
    $buf  = '';
    $ch   = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_HTTPHEADER    => [
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS    => json_encode([
            'model'      => $model,
            'max_tokens' => 16000,
            'stream'     => true,
            'system'     => $system_prompt,
            'messages'   => [['role' => 'user', 'content' => $user_prompt]],
        ]),
        CURLOPT_TIMEOUT       => 300,
        CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$full, &$buf) {
            $buf .= $data;
            $lines = explode("\n", $buf);
            $buf   = array_pop($lines);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!str_starts_with($line, 'data: ')) continue;
                $ev = json_decode(substr($line, 6), true);
                if (($ev['type'] ?? '') !== 'content_block_delta') continue;
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
    if ($err || $code >= 400) throw new \RuntimeException('Claude stream error: ' . ($err ?: 'HTTP ' . $code));
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
