<?php
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];

$res  = supabase_call('GET',
    '/rest/v1/content_generations?select=id,keyword,status,created_at,updated_at'
    . '&user_id=eq.' . urlencode($user_id)
    . '&order=created_at.desc&limit=50'
);
$data = json_decode($res['body'], true);

echo json_encode(['generations' => $data ?: []]);
