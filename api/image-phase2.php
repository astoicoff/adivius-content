<?php
set_time_limit(300);
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];

// Accept both multipart/form-data (with context image) and plain JSON
$is_multipart  = !empty($_FILES['image']['tmp_name']);
if ($is_multipart) {
    $generation_id = trim($_POST['generation_id'] ?? '');
    $prompt        = trim($_POST['prompt']        ?? '');
    $size          = trim($_POST['size']          ?? '1792x1024');
    $quality       = trim($_POST['quality']       ?? 'standard');
} else {
    $body          = json_decode(file_get_contents('php://input'), true);
    $generation_id = trim($body['generation_id'] ?? '');
    $prompt        = trim($body['prompt']        ?? '');
    $size          = trim($body['size']          ?? '1792x1024');
    $quality       = trim($body['quality']       ?? 'standard');
}

if (!$generation_id) { http_response_code(400); echo json_encode(['detail' => 'Generation ID is required.']); exit; }
if (!$prompt)        { http_response_code(400); echo json_encode(['detail' => 'Prompt is required.']); exit; }

if ($is_multipart) {
    $allowed_types = ['image/png', 'image/jpeg', 'image/webp'];
    if (!in_array($_FILES['image']['type'] ?? '', $allowed_types, true)) {
        http_response_code(400); echo json_encode(['detail' => 'Invalid image type. Allowed: PNG, JPEG, WebP.']); exit;
    }
    if (($_FILES['image']['size'] ?? 0) > 20 * 1024 * 1024) {
        http_response_code(400); echo json_encode(['detail' => 'Image file too large (max 20 MB).']); exit;
    }
}

$valid_sizes = ['1792x1024', '1024x1024', '1024x1792', '1536x1024', '1024x1536'];
$valid_quals = ['standard', 'hd', 'low', 'medium', 'high'];
if (!in_array($size,    $valid_sizes, true)) $size    = '1792x1024';
if (!in_array($quality, $valid_quals, true)) $quality = 'standard';

// dall-e-3 was retired by OpenAI. gpt-image-2 accepts any size divisible by
// 16 (all our UI sizes qualify verbatim) but uses low/medium/high/auto for
// quality — map the legacy DALL-E vocabulary that the UI and old rows carry.
$api_quality = [
    'standard' => 'medium',
    'hd'       => 'high',
    'low'      => 'low',
    'medium'   => 'medium',
    'high'     => 'high',
][$quality];

$settings   = get_user_settings($user_id);
$openai_key = $settings['openai_key'] ?? '';
if (!$openai_key) {
    http_response_code(400); echo json_encode(['detail' => 'An OpenAI API key is required for image generation. Add one in API Keys.']); exit;
}

// Verify ownership + fetch existing state for version history archiving
$check = supabase_call('GET',
    '/rest/v1/image_generations?id=eq.' . urlencode($generation_id)
    . '&user_id=eq.' . urlencode($user_id)
    . '&select=id,image_url,image_versions,revised_prompt,updated_at'
);
$rows = json_decode($check['body'], true);
if (empty($rows)) {
    http_response_code(404); echo json_encode(['detail' => 'Generation not found.']); exit;
}

$prev_image_url  = $rows[0]['image_url']      ?? '';
$prev_versions   = is_array($rows[0]['image_versions']) ? $rows[0]['image_versions'] : [];
$prev_revised    = $rows[0]['revised_prompt'] ?? '';
$prev_updated_at = $rows[0]['updated_at']     ?? date('c');

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
    if ($is_multipart) {
        emit_sse(['type' => 'progress', 'message' => 'Editing image with AI…']);
        // /v1/images/edits: multipart, uses the uploaded image as the base
        $ch = curl_init('https://api.openai.com/v1/images/edits');
        curl_setopt_array($ch, [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => [
                'model'         => 'gpt-image-2',
                'image'         => new CURLFile(
                    $_FILES['image']['tmp_name'],
                    $_FILES['image']['type'],
                    $_FILES['image']['name']
                ),
                'prompt'        => $prompt,
                'n'             => '1',
                'size'          => $size,
                'quality'       => $api_quality,
                'output_format' => 'jpeg',
            ],
            // No Content-Type header — curl sets multipart boundary automatically
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $openai_key],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 180,
        ]);
    } else {
        emit_sse(['type' => 'progress', 'message' => 'Generating image…']);
        // /v1/images/generations: standard text-to-image
        $ch = curl_init('https://api.openai.com/v1/images/generations');
        curl_setopt_array($ch, [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model'         => 'gpt-image-2',
                'prompt'        => $prompt,
                'n'             => 1,
                'size'          => $size,
                'quality'       => $api_quality,
                'output_format' => 'jpeg',
            ]),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $openai_key,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 180,
        ]);
    }

    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($resp, true);
    if ($http !== 200) {
        throw new Exception('Image API error: ' . ($data['error']['message'] ?? ('HTTP ' . $http)));
    }

    $temp_url = $data['data'][0]['url']      ?? '';
    $b64      = $data['data'][0]['b64_json'] ?? '';
    // edits endpoint does not revise the prompt; fall back to the input prompt
    $revised_prompt = $data['data'][0]['revised_prompt'] ?? $prompt;
    if (!$temp_url && !$b64) throw new Exception('No image data returned by the image API.');

    emit_sse(['type' => 'progress', 'message' => 'Saving image to storage…']);

    if ($b64) {
        $image_bytes = base64_decode($b64);
        if ($image_bytes === false || $image_bytes === '') throw new Exception('Failed to decode generated image data.');
    } else {
        $ch = curl_init($temp_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $image_bytes = curl_exec($ch);
        curl_close($ch);
        if (!$image_bytes) throw new Exception('Failed to download generated image from OpenAI.');
    }

    // Remove embedded AI-provenance metadata (C2PA, IPTC DigitalSourceType)
    // before the image is hosted — these travel with the file onto client
    // sites and trigger "AI-generated" labeling in Google image search.
    $image_bytes = strip_jpeg_metadata($image_bytes);

    // Each attempt gets a unique path so previous versions remain accessible
    $ts_ms        = (int)(microtime(true) * 1000);
    $storage_path = $user_id . '/' . $generation_id . '_' . $ts_ms . '.jpg';

    $ch = curl_init(SUPABASE_URL . '/storage/v1/object/generated-images/' . $storage_path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $image_bytes,
        CURLOPT_HTTPHEADER     => [
            // Storage rejects a bare Bearer with the new sb_secret_ key format
            // ("Invalid Compact JWS"). apikey header provides authentication.
            'apikey: ' . SUPABASE_SERVICE_KEY,
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

    // Archive the previous image_url into versions before overwriting
    $new_versions = $prev_versions;
    if ($prev_image_url) {
        $new_versions[] = [
            'url'            => $prev_image_url,
            'revised_prompt' => $prev_revised,
            'generated_at'   => $prev_updated_at,
        ];
    }

    supabase_call('PATCH', '/rest/v1/image_generations?id=eq.' . urlencode($generation_id), [
        'image_url'      => $public_url,
        'revised_prompt' => $revised_prompt,
        'image_versions' => $new_versions,
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
