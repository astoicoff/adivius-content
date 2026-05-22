<?php
ob_start();
require_once __DIR__ . '/helpers.php';
set_headers();

$action = $_GET['action'] ?? '';

// accept_info is public (no auth required) — just returns group name + role for the invite page
if ($action === 'accept_info') {
    $token = trim($_GET['token'] ?? '');
    if (!$token) { http_response_code(400); ob_end_clean(); echo json_encode(['detail' => 'token required.']); exit; }

    $res  = supabase_call('GET',
        '/rest/v1/content_group_invites?token=eq.' . urlencode($token)
        . '&select=id,group_id,email,role,expires_at,accepted_at'
    );
    $rows = json_decode($res['body'], true);
    if (empty($rows)) { http_response_code(404); ob_end_clean(); echo json_encode(['detail' => 'Invite not found or already used.']); exit; }
    $inv = $rows[0];

    if ($inv['accepted_at']) { http_response_code(410); ob_end_clean(); echo json_encode(['detail' => 'This invite has already been accepted.']); exit; }
    if (strtotime($inv['expires_at']) < time()) { http_response_code(410); ob_end_clean(); echo json_encode(['detail' => 'This invite has expired.']); exit; }

    $grpRes = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($inv['group_id']) . '&select=name');
    $grpData = json_decode($grpRes['body'], true);
    $groupName = $grpData[0]['name'] ?? 'Unknown Group';

    ob_end_clean();
    echo json_encode([
        'group_id'   => $inv['group_id'],
        'group_name' => $groupName,
        'role'       => $inv['role'],
        'email'      => $inv['email'],
    ]);
    exit;
}

// All other actions require auth
$user    = get_authed_user();
$user_id = $user['id'];

// ── Accept invite ──────────────────────────────────────────────────────────────
if ($action === 'accept' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_GET['token'] ?? '');
    if (!$token) { http_response_code(400); ob_end_clean(); echo json_encode(['detail' => 'token required.']); exit; }

    $res  = supabase_call('GET',
        '/rest/v1/content_group_invites?token=eq.' . urlencode($token)
        . '&select=id,group_id,role,expires_at,accepted_at'
    );
    $rows = json_decode($res['body'], true);
    if (empty($rows)) { http_response_code(404); ob_end_clean(); echo json_encode(['detail' => 'Invite not found.']); exit; }
    $inv = $rows[0];

    if ($inv['accepted_at']) { http_response_code(410); ob_end_clean(); echo json_encode(['detail' => 'Already accepted.']); exit; }
    if (strtotime($inv['expires_at']) < time()) { http_response_code(410); ob_end_clean(); echo json_encode(['detail' => 'Invite has expired.']); exit; }

    // Don't let the owner accept their own invite
    $ownCheck = supabase_call('GET', '/rest/v1/content_groups?id=eq.' . urlencode($inv['group_id']) . '&user_id=eq.' . urlencode($user_id) . '&select=id');
    if (!empty(json_decode($ownCheck['body'], true))) {
        http_response_code(400); ob_end_clean(); echo json_encode(['detail' => 'You are already the owner of this group.']); exit;
    }

    // Upsert member row
    $memberRes = supabase_call('POST', '/rest/v1/content_group_members', [
        'group_id'   => $inv['group_id'],
        'user_id'    => $user_id,
        'role'       => $inv['role'],
    ], ['Prefer: resolution=merge-duplicates,return=minimal']);

    // Mark invite as accepted
    supabase_call('PATCH',
        '/rest/v1/content_group_invites?id=eq.' . urlencode($inv['id']),
        ['accepted_at' => date('c')]
    );

    ob_end_clean();
    echo json_encode(['success' => true, 'group_id' => $inv['group_id'], 'role' => $inv['role']]);
    exit;
}

// ── List members + pending invites ─────────────────────────────────────────────
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $group_id = trim($_GET['group_id'] ?? '');
    if (!$group_id) { http_response_code(400); ob_end_clean(); echo json_encode(['detail' => 'group_id required.']); exit; }

    if (check_group_access($user_id, $group_id, 'owner') !== 'owner') {
        http_response_code(403); ob_end_clean(); echo json_encode(['detail' => 'Only the owner can view members.']); exit;
    }

    // Fetch members
    $memRes = supabase_call('GET',
        '/rest/v1/content_group_members?group_id=eq.' . urlencode($group_id)
        . '&select=id,user_id,role,joined_at'
    );
    $members = json_decode($memRes['body'], true) ?: [];

    // Enrich with display_name from user_settings
    foreach ($members as &$m) {
        $settRes = supabase_call('GET', '/rest/v1/user_settings?user_id=eq.' . urlencode($m['user_id']) . '&select=display_name');
        $sett    = json_decode($settRes['body'], true);
        $m['display_name'] = $sett[0]['display_name'] ?? null;
        unset($m['user_id']);
    }

    // Fetch pending invites (not yet accepted, not expired)
    $invRes = supabase_call('GET',
        '/rest/v1/content_group_invites?group_id=eq.' . urlencode($group_id)
        . '&accepted_at=is.null'
        . '&select=id,email,role,token,created_at,expires_at'
    );
    $invites = json_decode($invRes['body'], true) ?: [];
    // Filter out expired
    $now     = time();
    $invites = array_values(array_filter($invites, fn($i) => strtotime($i['expires_at']) > $now));

    ob_end_clean();
    echo json_encode(['members' => $members, 'invites' => $invites]);
    exit;
}

// ── Create invite ──────────────────────────────────────────────────────────────
if ($action === 'invite' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true) ?: [];
    $group_id = trim($body['group_id'] ?? '');
    $email    = strtolower(trim($body['email']    ?? ''));
    $role     = trim($body['role']     ?? 'viewer');

    if (!$group_id || !$email || !in_array($role, ['moderator', 'viewer'], true)) {
        http_response_code(400); ob_end_clean(); echo json_encode(['detail' => 'group_id, email, and role (moderator|viewer) are required.']); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400); ob_end_clean(); echo json_encode(['detail' => 'Invalid email address.']); exit;
    }
    if (check_group_access($user_id, $group_id, 'owner') !== 'owner') {
        http_response_code(403); ob_end_clean(); echo json_encode(['detail' => 'Only the owner can invite members.']); exit;
    }

    // Generate a secure token and create invite (upsert so re-inviting same email refreshes it)
    $token = bin2hex(random_bytes(32));
    $res   = supabase_call('POST', '/rest/v1/content_group_invites', [
        'group_id'    => $group_id,
        'email'       => $email,
        'role'        => $role,
        'token'       => $token,
        'invited_by'  => $user_id,
        'accepted_at' => null,
        'expires_at'  => date('c', strtotime('+7 days')),
    ], ['Prefer: resolution=merge-duplicates,return=representation']);

    $created = json_decode($res['body'], true);
    if ($res['status'] >= 400 || empty($created)) {
        http_response_code(500); ob_end_clean(); echo json_encode(['detail' => 'Failed to create invite: ' . $res['body']]); exit;
    }
    $savedToken = $created[0]['token'] ?? $token;

    // Build invite URL from current host
    $host       = $_SERVER['HTTP_HOST'] ?? 'app.adivius.com';
    $scheme     = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $invite_url = $scheme . '://' . $host . '/accept-invite?token=' . urlencode($savedToken);

    ob_end_clean();
    echo json_encode(['success' => true, 'invite_url' => $invite_url, 'token' => $savedToken]);
    exit;
}

// ── Update member role ─────────────────────────────────────────────────────────
if ($action === 'update_role' && $_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $body      = json_decode(file_get_contents('php://input'), true) ?: [];
    $member_id = trim($_GET['id'] ?? '');
    $new_role  = trim($body['role'] ?? '');

    if (!$member_id || !in_array($new_role, ['moderator', 'viewer'], true)) {
        http_response_code(400); ob_end_clean(); echo json_encode(['detail' => 'id and role required.']); exit;
    }

    // Fetch member to get group_id
    $memRes = supabase_call('GET', '/rest/v1/content_group_members?id=eq.' . urlencode($member_id) . '&select=group_id');
    $memData = json_decode($memRes['body'], true);
    if (empty($memData)) { http_response_code(404); ob_end_clean(); echo json_encode(['detail' => 'Member not found.']); exit; }
    $group_id = $memData[0]['group_id'];

    if (check_group_access($user_id, $group_id, 'owner') !== 'owner') {
        http_response_code(403); ob_end_clean(); echo json_encode(['detail' => 'Only the owner can change roles.']); exit;
    }

    supabase_call('PATCH', '/rest/v1/content_group_members?id=eq.' . urlencode($member_id), ['role' => $new_role]);
    ob_end_clean();
    echo json_encode(['success' => true]);
    exit;
}

// ── Remove member or cancel invite ────────────────────────────────────────────
if ($action === 'remove' && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id   = trim($_GET['id']   ?? '');
    $type = trim($_GET['type'] ?? 'member');  // 'member' or 'invite'

    if (!$id) { http_response_code(400); ob_end_clean(); echo json_encode(['detail' => 'id required.']); exit; }

    if ($type === 'invite') {
        $invRes  = supabase_call('GET', '/rest/v1/content_group_invites?id=eq.' . urlencode($id) . '&select=group_id');
        $invData = json_decode($invRes['body'], true);
        if (empty($invData)) { http_response_code(404); ob_end_clean(); echo json_encode(['detail' => 'Invite not found.']); exit; }
        if (check_group_access($user_id, $invData[0]['group_id'], 'owner') !== 'owner') {
            http_response_code(403); ob_end_clean(); echo json_encode(['detail' => 'Only the owner can cancel invites.']); exit;
        }
        supabase_call('DELETE', '/rest/v1/content_group_invites?id=eq.' . urlencode($id));
    } else {
        $memRes  = supabase_call('GET', '/rest/v1/content_group_members?id=eq.' . urlencode($id) . '&select=group_id,user_id');
        $memData = json_decode($memRes['body'], true);
        if (empty($memData)) { http_response_code(404); ob_end_clean(); echo json_encode(['detail' => 'Member not found.']); exit; }
        $mem      = $memData[0];
        // Owner can remove anyone; member can remove themselves
        $is_owner = check_group_access($user_id, $mem['group_id'], 'owner') === 'owner';
        $is_self  = $mem['user_id'] === $user_id;
        if (!$is_owner && !$is_self) {
            http_response_code(403); ob_end_clean(); echo json_encode(['detail' => 'You cannot remove this member.']); exit;
        }
        supabase_call('DELETE', '/rest/v1/content_group_members?id=eq.' . urlencode($id));
    }

    ob_end_clean();
    echo json_encode(['success' => true]);
    exit;
}

ob_end_clean();
http_response_code(400);
echo json_encode(['detail' => 'Unknown action or method.']);
