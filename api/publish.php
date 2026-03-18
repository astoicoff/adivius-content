<?php
ob_start();
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); ob_end_clean();
        echo json_encode(['detail' => 'Method not allowed.']); exit;
    }

    $body      = json_decode(file_get_contents('php://input'), true) ?: [];
    $gen_id    = $body['generation_id'] ?? '';
    $wp_status = in_array($body['post_status'] ?? '', ['draft','publish']) ? $body['post_status'] : 'draft';

    if (!$gen_id) {
        http_response_code(400); ob_end_clean();
        echo json_encode(['detail' => 'generation_id is required.']); exit;
    }

    // Load generation
    $genRes = supabase_call('GET',
        '/rest/v1/content_generations?id=eq.' . urlencode($gen_id)
        . '&user_id=eq.' . urlencode($user_id)
        . '&select=id,keyword,content,group_id'
    );
    $genData = json_decode($genRes['body'], true);
    if (empty($genData)) {
        http_response_code(404); ob_end_clean();
        echo json_encode(['detail' => 'Generation not found.']); exit;
    }
    $gen = $genData[0];

    if (empty($gen['group_id'])) {
        http_response_code(400); ob_end_clean();
        echo json_encode(['detail' => 'This content has no group. Assign it to a group with WordPress configured.']); exit;
    }

    // Load group WP credentials
    $grpRes = supabase_call('GET',
        '/rest/v1/content_groups?id=eq.' . urlencode($gen['group_id'])
        . '&user_id=eq.' . urlencode($user_id)
        . '&select=wp_site_url,wp_username,wp_app_password'
    );
    $grpData = json_decode($grpRes['body'], true);
    if (empty($grpData)) {
        http_response_code(404); ob_end_clean();
        echo json_encode(['detail' => 'Group not found.']); exit;
    }
    $grp = $grpData[0];

    if (empty($grp['wp_site_url']) || empty($grp['wp_username']) || empty($grp['wp_app_password'])) {
        http_response_code(400); ob_end_clean();
        echo json_encode(['detail' => 'WordPress is not configured for this group. Add credentials in Content Groups settings.']); exit;
    }

    // Parse meta from content
    $raw   = $gen['content'] ?? '';
    $lines = explode("\n", $raw);
    $meta  = [];
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
    $postContent = trim(implode("\n", array_slice($lines, $bodyStart)));
    $postTitle   = $meta['title'] ?? $meta['h1'] ?? $gen['keyword'];
    $postSlug    = $meta['url']   ?? '';

    // Build WP REST API request
    $siteUrl  = rtrim($grp['wp_site_url'], '/');
    $endpoint = $siteUrl . '/wp-json/wp/v2/posts';
    $authB64  = base64_encode($grp['wp_username'] . ':' . $grp['wp_app_password']);

    $wpPayload = ['title' => $postTitle, 'content' => $postContent, 'status' => $wp_status];
    if ($postSlug) $wpPayload['slug'] = $postSlug;

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($wpPayload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Basic ' . $authB64,
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $wpBody   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        http_response_code(502); ob_end_clean();
        echo json_encode(['detail' => 'Could not reach WordPress site: ' . $curlErr]); exit;
    }

    $wpResult = json_decode($wpBody, true);
    if ($httpCode >= 400) {
        $msg = $wpResult['message'] ?? ('WordPress returned HTTP ' . $httpCode);
        http_response_code(502); ob_end_clean();
        echo json_encode(['detail' => $msg]); exit;
    }

    $postUrl = $wpResult['link'] ?? ($siteUrl . '/?p=' . ($wpResult['id'] ?? ''));
    $postId  = $wpResult['id']   ?? null;

    // Save wp_post_url back to the generation
    if ($postUrl) {
        supabase_call('PATCH',
            '/rest/v1/content_generations?id=eq.' . urlencode($gen_id) . '&user_id=eq.' . urlencode($user_id),
            ['wp_post_url' => $postUrl]
        );
    }

    ob_end_clean();
    echo json_encode(['post_id' => $postId, 'post_url' => $postUrl, 'status' => $wp_status]);

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['detail' => 'Server error: ' . $e->getMessage()]);
}
