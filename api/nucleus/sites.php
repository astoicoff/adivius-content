<?php
ob_start();
require_once __DIR__ . '/../helpers.php';
set_headers();
ob_clean();

get_authed_user();

if (!NUCLEUS_BASE_URL || !NUCLEUS_SERVICE_TOKEN) {
    echo json_encode(['_error' => 'not_configured']);
    exit;
}

// Forward optional client_id filter: <uuid> for sites of one client, "none"
// for unassigned sites, omit for all. Contract per Nucleus hub-contract.md.
$qs = '';
if (isset($_GET['client_id']) && $_GET['client_id'] !== '') {
    $qs = '?client_id=' . urlencode($_GET['client_id']);
}
$ch = curl_init(rtrim(NUCLEUS_BASE_URL, '/') . '/api/nucleus/sites' . $qs);
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

if ($code === 200) {
    echo $body;
} else {
    echo json_encode(['_error' => 'nucleus_error', 'status' => $code]);
}
