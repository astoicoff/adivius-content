<?php
set_time_limit(300);
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];
$body    = json_decode(file_get_contents('php://input'), true);

$generation_id = trim($body['generation_id'] ?? '');
$prompt        = trim($body['prompt']        ?? '');
$size          = trim($body['size']          ?? '1792x1024');
$quality       = trim($body['quality']       ?? 'standard');

if (!$generation_id) { http_response_code(400); echo json_encode(['detail' => 'Generation ID is required.']); exit; }
if (!$prompt)        { http_response_code(400); echo json_encode(['detail' => 'Prompt is required.']); exit; }

$valid_sizes = ['1792x1024', '1024x1024', '1024x1792'];
$valid_quals = ['standard', 'hd'];
if (!in_array($size,    $valid_sizes, true)) $size    = '1792x1024';
if (!in_array($quality, $valid_quals, true)) $quality = 'standard';

$settings   = get_user_settings($user_id);
$openai_key = $settings['openai_key'] ?? '';
if (!$openai_key) {
    http_response_code(400); echo json_encode(['detail' => 'An OpenAI API key is required for image generation. Add one in API Keys.']); exit;
}

// Verify ownership
$check = supabase_call('GET', '/rest/v1/image_generations?id=eq.' . urlencode($generation_id) . '&user_id=eq.' . urlencode($user_id) . '&select=id');
if (empty(json_decode($check['body'], true))) {
    http_response_code(404); echo json_encode(['detail' => 'Generation not found.']); exit;
}

supabase_call('PATCH', '/rest/v1/image_generations?id=eq.' . urlencode($generation_id), [
    'status'     => 'generating_image',
    'size'       => $size,
    'quality'    => $quality,
    'prompt'     => $prompt,
    'updated_at' => date('c'),
]);

// All validation passed — switch to SSE
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

try {
    emit_sse(['type' => 'progress', 'message' => 'Generating image with DALL-E 3…']);

    // Call DALL-E 3
    $ch = curl_init('https://api.openai.com/v1/images/generations');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'model'           => 'dall-e-3',
            'prompt'          => $prompt,
            'n'               => 1,
            'size'            => $size,
            'quality'         => $quality,
            'response_format' => 'url',
        ]),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $openai_key,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($resp, true);
    if ($http !== 200) {
        throw new Exception('DALL-E 3 error: ' . ($data['error']['message'] ?? ('HTTP ' . $http)));
    }

    $temp_url       = $data['data'][0]['url']              ?? '';
    $revised_prompt = $data['data'][0]['revised_prompt']   ?? $prompt;
    if (!$temp_url) throw new Exception('No image URL returned by DALL-E 3.');

    emit_sse(['type' => 'progress', 'message' => 'Saving image to storage…']);

    // Download image bytes from temporary OpenAI URL
    $ch = curl_init($temp_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $image_bytes = curl_exec($ch);
    curl_close($ch);
    if (!$image_bytes) throw new Exception('Failed to download generated image from OpenAI.');

    // Upload to Supabase Storage (bucket: generated-images)
    $storage_path = $user_id . '/' . $generation_id . '.jpg';
    $ch = curl_init(SUPABASE_URL . '/storage/v1/object/generated-images/' . $storage_path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $image_bytes,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: image/jpeg',
            'x-upsert: true',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $upload_resp = curl_exec($ch);
    $upload_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($upload_http < 200 || $upload_http >= 300) {
        throw new Exception('Storage upload failed (HTTP ' . $upload_http . '): ' . $upload_resp);
    }

    $public_url = SUPABASE_URL . '/storage/v1/object/public/generated-images/' . $storage_path;

    supabase_call('PATCH', '/rest/v1/image_generations?id=eq.' . urlencode($generation_id), [
        'image_url'      => $public_url,
        'revised_prompt' => $revised_prompt,
        'status'         => 'completed',
        'updated_at'     => date('c'),
    ]);

    emit_sse(['type' => 'done', 'generation_id' => $generation_id, 'image_url' => $public_url, 'revised_prompt' => $revised_prompt]);

} catch (Throwable $e) {
    supabase_call('PATCH', '/rest/v1/image_generations?id=eq.' . urlencode($generation_id), [
        'status'     => 'failed',
        'error'      => substr($e->getMessage(), 0, 500),
        'updated_at' => date('c'),
    ]);
    emit_sse(['type' => 'error', 'message' => $e->getMessage()]);
}
