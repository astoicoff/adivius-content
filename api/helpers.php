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
    $ch = curl_init(SUPABASE_URL . '/auth/v1/user');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $m[1],
            'apikey: ' . SUPABASE_ANON_KEY,
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

function upsert_user_settings($user_id, $openai_key, $perplexity_key, $serpapi_key) {
    return supabase_call('POST', '/rest/v1/user_settings?on_conflict=user_id', [
        'user_id'        => $user_id,
        'openai_key'     => $openai_key,
        'perplexity_key' => $perplexity_key,
        'serpapi_key'    => $serpapi_key,
    ], ['Prefer: resolution=merge-duplicates,return=representation']);
}

function create_generation_row($user_id, $keyword, $group_id = null) {
    $row = ['user_id' => $user_id, 'keyword' => $keyword, 'status' => 'generating_instructions'];
    if ($group_id) $row['group_id'] = $group_id;
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
    if ($status !== 200) { http_response_code(400); echo json_encode(['detail' => 'SerpApi HTTP Error: ' . $body]); exit; }
    $results = json_decode($body, true);
    if (isset($results['error'])) { http_response_code(400); echo json_encode(['detail' => 'SerpApi Error: ' . $results['error']]); exit; }
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
    if (empty($urls)) { http_response_code(404); echo json_encode(['detail' => 'SerpApi returned no valid organic URLs.']); exit; }
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
    if ($status !== 200) { http_response_code($status); echo json_encode(['detail' => 'Perplexity API Error: ' . $body]); exit; }
    return json_decode($body, true)['choices'][0]['message']['content'];
}

function call_openai($system_prompt, $user_prompt, $api_key) {
    if (!$api_key) { http_response_code(400); echo json_encode(['detail' => 'OpenAI API key is required. Please add it in API Keys settings.']); exit; }
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'model'    => 'gpt-5',
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
