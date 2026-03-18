<?php
ob_start();
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); ob_end_clean(); echo json_encode(['detail' => 'Method not allowed.']); exit;
    }

    $body          = json_decode(file_get_contents('php://input'), true) ?: [];
    $keyword       = trim($body['keyword']       ?? '');
    $group_id      = trim($body['group_id']      ?? '');
    $scheduled_for = trim($body['scheduled_for'] ?? '');

    if (!$keyword)       { http_response_code(400); ob_end_clean(); echo json_encode(['detail' => 'Keyword is required.']);        exit; }
    if (!$group_id)      { http_response_code(400); ob_end_clean(); echo json_encode(['detail' => 'Content group is required.']); exit; }
    if (!$scheduled_for) { http_response_code(400); ob_end_clean(); echo json_encode(['detail' => 'Scheduled time is required.']); exit; }

    // Verify group belongs to user
    $grp = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($group_id) . '&user_id=eq.' . urlencode($user_id) . '&select=id');
    if (empty(json_decode($grp['body'], true))) {
        http_response_code(404); ob_end_clean(); echo json_encode(['detail' => 'Content group not found.']); exit;
    }

    // Parse datetime-local string to ISO 8601 (treat as UTC)
    $dt  = new DateTime($scheduled_for, new DateTimeZone('UTC'));
    $iso = $dt->format('c');

    $res  = supabase_call('POST', '/rest/v1/content_generations', [
        'user_id'       => $user_id,
        'keyword'       => $keyword,
        'group_id'      => $group_id,
        'status'        => 'scheduled',
        'scheduled_for' => $iso,
    ], ['Prefer: return=representation']);

    if ($res['status'] >= 400) {
        http_response_code(500); ob_end_clean(); echo $res['body']; exit;
    }

    $data = json_decode($res['body'], true);
    ob_end_clean();
    echo json_encode(['id' => $data[0]['id'], 'scheduled_for' => $iso]);

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['detail' => 'Server error: ' . $e->getMessage()]);
}
