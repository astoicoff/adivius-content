<?php
ob_start();
require_once __DIR__ . '/../helpers.php';
set_headers();

get_authed_user(); // validates the session; exits 401 if invalid

// Extract the raw token to forward to Nucleus
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!$auth && function_exists('getallheaders')) {
    $h    = getallheaders();
    $auth = $h['Authorization'] ?? $h['authorization'] ?? '';
}
preg_match('/^Bearer\s+(.+)$/i', $auth, $m);
$token = $m[1] ?? '';

if (!NUCLEUS_BASE_URL) {
    http_response_code(503); ob_end_clean();
    echo json_encode(['detail' => 'Nucleus not configured.']); exit;
}

$ch = curl_init(rtrim(NUCLEUS_BASE_URL, '/') . '/api/nucleus/profile/me');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($err) {
    http_response_code(502); ob_end_clean();
    echo json_encode(['detail' => 'Could not reach Nucleus: ' . $err]); exit;
}

ob_end_clean();
http_response_code($code);
echo $body;
