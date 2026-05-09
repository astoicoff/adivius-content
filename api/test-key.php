<?php
ob_start();
set_time_limit(30);
require_once __DIR__ . '/helpers.php';
set_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); ob_end_clean();
    echo json_encode(['ok' => false, 'detail' => 'Method not allowed.']); exit;
}

try {
    get_authed_user(); // auth required, but we don't need the user object

    $body     = json_decode(file_get_contents('php://input'), true) ?: [];
    $provider = strtolower(trim($body['provider'] ?? ''));
    $key      = trim($body['api_key']  ?? '');

    if (!$provider) { http_response_code(400); ob_end_clean(); echo json_encode(['ok' => false, 'detail' => 'provider required.']); exit; }
    if (!$key)      { ob_end_clean(); echo json_encode(['ok' => false, 'detail' => 'No key set.']); exit; }

    $result = test_provider($provider, $key);
    ob_end_clean();
    echo json_encode($result);

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['ok' => false, 'detail' => 'Server error: ' . $e->getMessage()]);
}

function http_get($url, $headers = [], $timeout = 12) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'err' => $err];
}

function http_post_json($url, $payload, $headers = [], $timeout = 12) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'err' => $err];
}

function err_msg($r, $fallback) {
    if ($r['err']) return $fallback . ': ' . $r['err'];
    $j = json_decode($r['body'], true);
    $msg = $j['error']['message'] ?? $j['detail'] ?? $j['message'] ?? null;
    return $msg ? "HTTP {$r['code']}: $msg" : "HTTP {$r['code']}";
}

function test_provider($provider, $key) {
    switch ($provider) {

        case 'openai': {
            $r = http_get('https://api.openai.com/v1/models', ['Authorization: Bearer ' . $key]);
            if ($r['code'] === 200) {
                $data   = json_decode($r['body'], true);
                $models = array_column($data['data'] ?? [], 'id');
                $hasGpt5 = in_array('gpt-5', $models, true);
                $count   = count($models);
                return ['ok' => true, 'detail' => "$count models available" . ($hasGpt5 ? ' · gpt-5 ✓' : ' · gpt-5 not available on this key')];
            }
            return ['ok' => false, 'detail' => err_msg($r, 'Connection failed')];
        }

        case 'claude':
        case 'anthropic': {
            $r = http_get('https://api.anthropic.com/v1/models', [
                'x-api-key: ' . $key,
                'anthropic-version: 2023-06-01',
            ]);
            if ($r['code'] === 200) {
                $data  = json_decode($r['body'], true);
                $count = count($data['data'] ?? []);
                return ['ok' => true, 'detail' => "$count models available"];
            }
            return ['ok' => false, 'detail' => err_msg($r, 'Connection failed')];
        }

        case 'gemini':
        case 'google': {
            $r = http_get('https://generativelanguage.googleapis.com/v1beta/models?key=' . urlencode($key));
            if ($r['code'] === 200) {
                $data  = json_decode($r['body'], true);
                $count = count($data['models'] ?? []);
                return ['ok' => true, 'detail' => "$count models available"];
            }
            return ['ok' => false, 'detail' => err_msg($r, 'Connection failed')];
        }

        case 'perplexity': {
            // No free auth-check endpoint — make the cheapest possible chat call (1 token).
            $r = http_post_json('https://api.perplexity.ai/chat/completions', [
                'model'      => 'sonar',
                'messages'   => [['role' => 'user', 'content' => 'hi']],
                'max_tokens' => 1,
            ], ['Authorization: Bearer ' . $key]);
            if ($r['code'] === 200) return ['ok' => true, 'detail' => 'Authenticated (sonar)'];
            return ['ok' => false, 'detail' => err_msg($r, 'Connection failed')];
        }

        case 'serpapi': {
            $r = http_get('https://serpapi.com/account?api_key=' . urlencode($key));
            if ($r['code'] === 200) {
                $data = json_decode($r['body'], true);
                $left = $data['searches_left']      ?? null;
                $plan = $data['plan_name']          ?? ($data['account_email'] ?? null);
                $bits = [];
                if ($plan !== null) $bits[] = $plan;
                if ($left !== null) $bits[] = number_format($left) . ' searches left';
                return ['ok' => true, 'detail' => $bits ? implode(' · ', $bits) : 'Authenticated'];
            }
            return ['ok' => false, 'detail' => err_msg($r, 'Connection failed')];
        }

        default:
            return ['ok' => false, 'detail' => 'Unknown provider: ' . $provider];
    }
}
