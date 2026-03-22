<?php
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];
$gen_id  = $_GET['generation_id'] ?? '';

if (!$gen_id) { http_response_code(400); echo json_encode(['detail' => 'generation_id required.']); exit; }

$res  = supabase_call('GET',
    '/rest/v1/content_generation_versions?generation_id=eq.' . urlencode($gen_id)
    . '&user_id=eq.' . urlencode($user_id)
    . '&order=created_at.desc'
    . '&select=id,content,created_at'
);
$data = json_decode($res['body'], true);
echo json_encode(['versions' => $data ?: []]);
