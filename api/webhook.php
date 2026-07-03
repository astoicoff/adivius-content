<?php
ob_start();
set_time_limit(60);
require_once __DIR__ . '/helpers.php';
set_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); ob_end_clean();
    echo json_encode(['detail' => 'Method not allowed.']); exit;
}

try {
    $user    = get_authed_user();
    $user_id = $user['id'];
    $body    = json_decode(file_get_contents('php://input'), true) ?: [];
    $gen_id  = trim($body['generation_id'] ?? '');
    if (!$gen_id) { http_response_code(400); ob_end_clean(); echo json_encode(['detail' => 'generation_id required.']); exit; }

    $genRes = supabase_call('GET',
        '/rest/v1/content_generations?id=eq.' . urlencode($gen_id)
        . '&user_id=eq.' . urlencode($user_id)
        . '&select=id,keyword,group_id,content,created_at'
    );
    $gen = json_decode($genRes['body'], true)[0] ?? null;
    if (!$gen)              { http_response_code(404); ob_end_clean(); echo json_encode(['detail' => 'Generation not found.']);    exit; }
    if (!$gen['group_id'])  { http_response_code(400); ob_end_clean(); echo json_encode(['detail' => 'Generation has no group.']); exit; }

    $grpRes = supabase_call('GET',
        '/rest/v1/content_groups?id=eq.' . urlencode($gen['group_id'])
        . '&user_id=eq.' . urlencode($user_id)
        . '&select=name,webhook_url,webhook_headers'
    );
    $grp = json_decode($grpRes['body'], true)[0] ?? null;
    if (!$grp || empty($grp['webhook_url'])) {
        http_response_code(400); ob_end_clean();
        echo json_encode(['detail' => 'No webhook URL configured for this group.']); exit;
    }

    $parsed  = parse_content_meta($gen['content']);
    $payload = [
        'event'         => 'content.completed',
        'generation_id' => $gen['id'],
        'keyword'       => $gen['keyword'],
        'group_id'      => $gen['group_id'],
        'group_name'    => $grp['name'],
        'completed_at'  => date('c'),
        'meta'          => $parsed['meta'],
        'content'       => $gen['content'],
        'html'          => $parsed['body'],
    ];
    $webhook_headers = $grp['webhook_headers'] ?? null;
    if (is_string($webhook_headers)) $webhook_headers = json_decode($webhook_headers, true);
    $result = fire_webhook($grp['webhook_url'], $payload, is_array($webhook_headers) ? $webhook_headers : []);

    if ($result['ok']) {
        $now = date('c');
        update_generation_row($gen['id'], ['webhook_delivered_at' => $now, 'webhook_error' => null]);
        ob_end_clean();
        echo json_encode(['ok' => true, 'delivered_at' => $now, 'attempts' => $result['attempts']]);
    } else {
        $err = substr($result['error'] ?? 'unknown error', 0, 500);
        update_generation_row($gen['id'], ['webhook_delivered_at' => null, 'webhook_error' => $err]);
        http_response_code(502); ob_end_clean();
        echo json_encode(['ok' => false, 'error' => $err, 'attempts' => $result['attempts']]);
    }

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['detail' => 'Server error: ' . $e->getMessage()]);
}
