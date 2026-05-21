<?php $pageTitle = 'Accept Invite | Content Creator'; ?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
    .invite-card     { max-width:440px; margin:60px auto 0; }
    .invite-role     { display:inline-block;text-transform:capitalize;font-weight:700;color:var(--blue); }
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">
    <div class="top-bar">
        <div>
            <div class="top-bar-title">Team Invite</div>
            <div class="top-bar-subtitle">You've been invited to a content group</div>
        </div>
    </div>
    <div class="content-area">
        <div class="invite-card">
            <div id="loadingState" class="loading-bar visible"><div class="spinner"></div> Loading invite…</div>
            <div id="errorState" class="alert alert-error" style="display:none;">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span id="errorText"></span>
            </div>
            <div id="inviteCard" class="card" style="display:none;">
                <div class="card-accent blue"></div>
                <div class="card-body" style="gap:16px;">
                    <p style="font-size:15px;color:var(--dark);font-family:'Inter',sans-serif;line-height:1.6;margin:0;">
                        You've been invited to join <strong id="groupName"></strong> as a <span class="invite-role" id="inviteRole"></span>.
                    </p>
                    <p id="roleDesc" style="font-size:13px;color:var(--text-muted);font-family:'Inter',sans-serif;margin:0;"></p>
                    <!-- Shown when not logged in -->
                    <a id="signInBtn" class="btn btn-blue" style="display:none;" href="/login">
                        <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        Sign in to Accept
                    </a>
                    <!-- Shown when logged in -->
                    <button id="acceptBtn" class="btn btn-green" onclick="acceptInvite()" style="display:none;">
                        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        Accept &amp; Join
                    </button>
                </div>
            </div>
            <div id="successState" style="display:none;">
                <div class="alert alert-success" style="display:flex;align-items:center;gap:10px;">
                    <svg viewBox="0 0 24 24" style="flex-shrink:0;width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;"><polyline points="20 6 9 17 4 12"/></svg>
                    You've joined the group! Redirecting…
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.js"></script>
<script src="/scripts/shared.js"></script>
<script>
const params    = new URLSearchParams(window.location.search);
const invToken  = params.get('token') || '';
let   inviteGroupId = null;
let   isAuthed      = false;

const roleDescs = {
    moderator: 'As a moderator you can generate content, edit it, and publish to WordPress.',
    viewer:    'As a viewer you can read all generated content but cannot generate or edit.',
};

async function init() {
    if (!invToken) { showError('No invite token provided.'); return; }

    // Check auth silently (no redirect)
    const { data } = await sb.auth.getSession();
    if (data.session) {
        isAuthed      = true;
        currentSession = data.session;
        currentUser    = data.session.user;
        renderUserInfo();
    }

    // Load invite info (public endpoint — no auth required)
    try {
        const res  = await fetch(`${API_URL}/api/members.php?action=accept_info&token=${encodeURIComponent(invToken)}`);
        const data = await res.json();
        if (!res.ok) { showError(data.detail || 'Invite not found or expired.'); return; }

        inviteGroupId = data.group_id;
        document.getElementById("groupName").textContent  = data.group_name;
        document.getElementById("inviteRole").textContent = data.role;
        document.getElementById("roleDesc").textContent   = roleDescs[data.role] || '';

        document.getElementById("loadingState").style.display = "none";
        document.getElementById("inviteCard").style.display   = "";

        if (isAuthed) {
            document.getElementById("acceptBtn").style.display  = "";
        } else {
            const returnUrl = encodeURIComponent('/accept-invite?token=' + encodeURIComponent(invToken));
            document.getElementById("signInBtn").href         = `/login?redirect=${returnUrl}`;
            document.getElementById("signInBtn").style.display = "";
        }
    } catch (err) {
        showError(err.message);
    }
}

async function acceptInvite() {
    const btn = document.getElementById("acceptBtn");
    btn.disabled    = true;
    btn.textContent = 'Joining…';
    try {
        const res  = await fetch(`${API_URL}/api/members.php?action=accept&token=${encodeURIComponent(invToken)}`, {
            method: 'POST', headers: authHeaders()
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.detail || 'Failed to accept invite.');

        document.getElementById("inviteCard").style.display   = "none";
        document.getElementById("successState").style.display = "";
        setTimeout(() => {
            window.location.href = '/content-groups' + (data.group_id ? '?group=' + encodeURIComponent(data.group_id) : '');
        }, 1800);
    } catch (err) {
        showError(err.message);
        btn.disabled    = false;
        btn.textContent = 'Accept & Join';
    }
}

function showError(msg) {
    document.getElementById("loadingState").style.display = "none";
    document.getElementById("errorState").style.display  = "flex";
    document.getElementById("errorText").textContent     = msg;
}

document.addEventListener("DOMContentLoaded", init);
</script>
</body>
</html>
