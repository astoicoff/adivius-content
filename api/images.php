<?php
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];
$method  = $_SERVER['REQUEST_METHOD'];

// Extracts the storage-relative path from a public URL and issues a DELETE.
// Non-fatal by design: Storage cleanup must never block the DB operation.
function storage_delete_by_url($url) {
    $prefix = SUPABASE_URL . '/storage/v1/object/public/generated-images/';
    if (!$url || strpos($url, $prefix) !== 0) return;
    $path = substr($url, strlen($prefix));
    $ch   = curl_init(SUPABASE_URL . '/storage/v1/object/generated-images/' . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

if ($method === 'GET') {

    if (isset($_GET['id'])) {
        $id   = $_GET['id'];
        $res  = supabase_call('GET', '/rest/v1/image_generations?id=eq.' . urlencode($id) . '&user_id=eq.' . urlencode($user_id) . '&select=*');
        $data = json_decode($res['body'], true);
        if (empty($data)) { http_response_code(404); echo json_encode(['detail' => 'Image not found.']); exit; }
        echo json_encode($data[0]);

    } elseif (isset($_GET['group_id'])) {
        $group_id = $_GET['group_id'];
        if (!check_group_access($user_id, $group_id)) {
            http_response_code(403); echo json_encode(['detail' => 'Group not found or insufficient permissions.']); exit;
        }
        $res  = supabase_call('GET',
            '/rest/v1/image_generations?group_id=eq.' . urlencode($group_id)
            . '&select=id,keyword,image_url,size,quality,model,status,created_at,updated_at'
            . '&order=created_at.desc'
        );
        $data = json_decode($res['body'], true);
        echo json_encode(['images' => $data ?: []]);

    } else {
        $limit = min(200, max(1, intval($_GET['limit'] ?? 100)));
        $res  = supabase_call('GET',
            '/rest/v1/image_generations?user_id=eq.' . urlencode($user_id)
            . '&select=id,keyword,image_url,size,quality,model,status,group_id,created_at,updated_at'
            . '&order=created_at.desc&limit=' . $limit
        );
        $data = json_decode($res['body'], true);
        echo json_encode(['images' => $data ?: [], 'limit' => $limit]);
    }

} elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) { http_response_code(400); echo json_encode(['detail' => 'Image ID is required.']); exit; }

    // Fetch full row so we can delete the main image + all versions + the
    // reference image from Storage
    $check = supabase_call('GET',
        '/rest/v1/image_generations?id=eq.' . urlencode($id)
        . '&user_id=eq.' . urlencode($user_id)
        . '&select=id,image_url,image_versions,reference_image_url'
    );
    $rows = json_decode($check['body'], true);
    if (empty($rows)) {
        http_response_code(404); echo json_encode(['detail' => 'Image not found.']); exit;
    }
    $row = $rows[0];

    storage_delete_by_url($row['image_url'] ?? '');
    storage_delete_by_url($row['reference_image_url'] ?? '');
    $versions = is_array($row['image_versions']) ? $row['image_versions'] : [];
    foreach ($versions as $v) {
        storage_delete_by_url($v['url'] ?? '');
    }

    supabase_call('DELETE', '/rest/v1/image_generations?id=eq.' . urlencode($id));
    echo json_encode(['status' => 'deleted']);

} elseif ($method === 'PATCH') {
    $id = $_GET['id'] ?? '';
    if (!$id) { http_response_code(400); echo json_encode(['detail' => 'Image ID is required.']); exit; }

    $body                = json_decode(file_get_contents('php://input'), true) ?: [];
    $prompt              = trim($body['prompt'] ?? '');
    $remove_reference    = !empty($body['remove_reference']);
    $delete_version_url  = trim($body['delete_version_url']  ?? '');
    $restore_version_url = trim($body['restore_version_url'] ?? '');

    if (!$prompt && !$remove_reference && !$delete_version_url && !$restore_version_url) {
        http_response_code(400); echo json_encode(['detail' => 'Nothing to update — send prompt, remove_reference, delete_version_url, or restore_version_url.']); exit;
    }

    $check = supabase_call('GET', '/rest/v1/image_generations?id=eq.' . urlencode($id) . '&user_id=eq.' . urlencode($user_id) . '&select=id,image_url,revised_prompt,updated_at,reference_image_url,image_versions');
    $rows  = json_decode($check['body'], true);
    if (empty($rows)) {
        http_response_code(404); echo json_encode(['detail' => 'Image not found.']); exit;
    }
    $row = $rows[0];

    if ($remove_reference) {
        storage_delete_by_url($row['reference_image_url'] ?? '');
        supabase_call('PATCH', '/rest/v1/image_generations?id=eq.' . urlencode($id), [
            'reference_image_url' => null,
            'updated_at'          => date('c'),
        ]);
        echo json_encode(['status' => 'reference_removed']);
        exit;
    }

    if ($restore_version_url) {
        // Swap semantics: the chosen version becomes the current image, and
        // the current image is archived in its place. No file is ever
        // orphaned or aliased (a URL never appears as both current and a
        // version), so per-version delete stays safe.
        $versions = is_array($row['image_versions']) ? $row['image_versions'] : [];
        $restored = null;
        $kept     = [];
        foreach ($versions as $v) {
            if ($restored === null && ($v['url'] ?? '') === $restore_version_url) { $restored = $v; continue; }
            $kept[] = $v;
        }
        if ($restored === null) {
            http_response_code(404); echo json_encode(['detail' => 'Version not found on this image.']); exit;
        }
        if (!empty($row['image_url'])) {
            $kept[] = [
                'url'            => $row['image_url'],
                'revised_prompt' => $row['revised_prompt'] ?? '',
                'generated_at'   => $row['updated_at'] ?? date('c'),
            ];
        }
        $patch = [
            'image_url'      => $restored['url'],
            'revised_prompt' => $restored['revised_prompt'] ?? '',
            'image_versions' => $kept,
            'status'         => 'completed',
            'updated_at'     => date('c'),
        ];
        supabase_call('PATCH', '/rest/v1/image_generations?id=eq.' . urlencode($id), $patch);
        echo json_encode([
            'status'         => 'restored',
            'image_url'      => $restored['url'],
            'revised_prompt' => $restored['revised_prompt'] ?? '',
            'image_versions' => $kept,
        ]);
        exit;
    }

    if ($delete_version_url) {
        $versions = is_array($row['image_versions']) ? $row['image_versions'] : [];
        $kept     = array_values(array_filter($versions, fn($v) => ($v['url'] ?? '') !== $delete_version_url));
        if (count($kept) === count($versions)) {
            http_response_code(404); echo json_encode(['detail' => 'Version not found on this image.']); exit;
        }
        // Only delete the file after confirming the URL belongs to this row —
        // otherwise a crafted URL could delete another user's storage object.
        storage_delete_by_url($delete_version_url);
        supabase_call('PATCH', '/rest/v1/image_generations?id=eq.' . urlencode($id), [
            'image_versions' => $kept,
            'updated_at'     => date('c'),
        ]);
        echo json_encode(['status' => 'version_deleted', 'image_versions' => $kept]);
        exit;
    }

    supabase_call('PATCH', '/rest/v1/image_generations?id=eq.' . urlencode($id), [
        'prompt'     => $prompt,
        'updated_at' => date('c'),
    ]);

    echo json_encode(['status' => 'saved', 'prompt' => $prompt]);

} else {
    http_response_code(405); echo json_encode(['detail' => 'Method not allowed.']);
}
