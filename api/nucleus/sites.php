<?php
ob_start();
require_once __DIR__ . '/../helpers.php';
set_headers();
ob_clean();

get_authed_user();

if (!NUCLEUS_BASE_URL || !NUCLEUS_SERVICE_TOKEN) {
    echo json_encode([]);
    exit;
}

$ch = curl_init(rtrim(NUCLEUS_BASE_URL, '/') . '/api/nucleus/sites');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . NUCLEUS_SERVICE_TOKEN,
        'X-Nucleus-Tool: ' . NUCLEUS_TOOL_SLUG,
    ],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo $code === 200 ? $body : json_encode([]);
