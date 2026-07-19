const SUPABASE_URL     = "https://glmharhknuicnszauurc.supabase.co";
const SUPABASE_ANON_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImdsbWhhcmhrbnVpY25zemF1dXJjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3Nzk0MjkzMDcsImV4cCI6MjA5NTAwNTMwN30.a5HhfKGcLebnGFb7zmDoJt-78AsXmFcbxt72DzA1nfc";

const { createClient } = supabase;
const sb      = createClient(SUPABASE_URL, SUPABASE_ANON_KEY);
const API_URL = (window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1")
    ? "http://localhost:8000" : "";

let currentUser    = null;
let currentSession = null;
let cachedGroups   = [];

let _nucleusProfile   = null;
let _nucleusProfileAt = 0;


async function initAuth(onReady) {
    const { data } = await sb.auth.getSession();
    if (!data.session) { window.location.href = "/login"; return; }
    currentSession = data.session;
    currentUser    = data.session.user;
    renderUserInfo();
    loadHistoryBadge();
    if (onReady) await onReady();
}

async function renderUserInfo() {
    const email = currentUser.email || "";
    document.getElementById("userEmail").textContent  = email;
    document.getElementById("userAvatar").textContent = email.charAt(0).toUpperCase();

    const now = Date.now();
    if (_nucleusProfile && (now - _nucleusProfileAt) < 30000) {
        applyProfile(_nucleusProfile);
        return;
    }

    try {
        const res = await fetch(`${API_URL}/api/nucleus/profile`, {
            headers: { "Authorization": `Bearer ${currentSession.access_token}` }
        });
        if (!res.ok) return;
        const profile = await res.json();
        _nucleusProfile   = profile;
        _nucleusProfileAt = now;
        applyProfile(profile);
    } catch (_) {}
}

function applyProfile(profile) {
    const nameEl = document.getElementById("userDisplayName");
    if (nameEl) {
        if (profile.display_name) {
            nameEl.textContent    = profile.display_name;
            nameEl.style.display  = "";
        } else {
            nameEl.style.display  = "none";
        }
    }
    const initial  = (profile.display_name || currentUser.email || "?").charAt(0).toUpperCase();
    const avatarEl = document.getElementById("userAvatar");
    if (profile.avatar_url) {
        const img = document.createElement("img");
        img.src = profile.avatar_url;
        img.alt = "";
        img.style.cssText = "width:100%;height:100%;border-radius:50%;object-fit:cover;";
        avatarEl.textContent = "";
        avatarEl.appendChild(img);
    } else {
        avatarEl.textContent = initial;
    }
}

async function handleLogout() {
    await sb.auth.signOut();
    window.location.href = "/login";
}

function authHeaders() {
    return {
        "Content-Type":  "application/json",
        "Authorization": `Bearer ${currentSession.access_token}`
    };
}

async function loadGroups() {
    try {
        const res  = await fetch(`${API_URL}/api/groups.php`, { headers: authHeaders() });
        if (!res.ok) return;
        const data = await res.json();
        cachedGroups = data.groups || [];
    } catch (_) {}
}

async function loadHistoryBadge() {
    try {
        const res   = await fetch(`${API_URL}/api/history`, { headers: authHeaders() });
        if (!res.ok) return;
        const data  = await res.json();
        const count = (data.generations || []).length;
        const badge = document.getElementById("historyBadge");
        if (badge && count > 0) { badge.style.display = ""; badge.textContent = count; }
    } catch (_) {}
}

function wordCount(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html || '';
    const text = (tmp.textContent || tmp.innerText || '').trim();
    return text ? (text.match(/\S+/g) || []).length : 0;
}

function readingTime(words) {
    const mins = Math.ceil(words / 200);
    return mins === 1 ? '1 min read' : `${mins} min read`;
}

function toTitleCase(str) {
    return (str || '').replace(/\w\S*/g, w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase());
}

function escapeHtml(str) {
    return (str || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

// Put a button into a busy state (disabled + spinner + label) and return a
// restore function. Usage: const done = btnBusy(btn); try {...} finally { done(); }
function btnBusy(btn, label = 'Saving…') {
    if (!btn) return () => {};
    const orig = btn.innerHTML;
    btn.disabled  = true;
    btn.innerHTML = '<span style="width:12px;height:12px;border:2px solid transparent;border-top-color:currentColor;border-right-color:currentColor;border-radius:50%;display:inline-block;animation:spin 0.7s linear infinite;flex-shrink:0;"></span> ' + escapeHtml(label);
    return () => { btn.disabled = false; btn.innerHTML = orig; };
}

function modelLabel(model) {
    const map = {
        'gpt-5':             'GPT-5',
        'gpt-5.5':           'GPT-5.5',
        'claude-opus-4-7':   'Opus 4.7',
        'claude-sonnet-4-6': 'Sonnet 4.6',
        'gemini-2.5-pro':    'Gemini 2.5',
    };
    return map[model] || (model || '');
}

function showToast(msg, type = 'error') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }
    const icons = {
        error:   '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        success: '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
        warning: '<svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    };
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.innerHTML = (icons[type] || icons.error) + '<span>' + (msg || '').replace(/</g, '&lt;') + '</span>';
    container.appendChild(t);
    setTimeout(() => {
        t.classList.add('out');
        setTimeout(() => t.remove(), 200);
    }, 3500);
}

function statusBadge(status) {
    const map = {
        pending:                 { cls: "badge-yellow", label: "Pending" },
        generating_instructions: { cls: "badge-blue",   label: "Generating Brief" },
        instructions_ready:      { cls: "badge-blue",   label: "Brief Ready" },
        generating_content:      { cls: "badge-red",    label: "Writing Content" },
        completed:               { cls: "badge-green",  label: "Complete" },
        published:               { cls: "badge-green",  label: "Published" },
        failed:                  { cls: "badge-red",    label: "Failed" }
    };
    const s = map[status] || { cls: "badge-yellow", label: status };
    return `<span class="badge ${s.cls}"><span class="badge-dot"></span>${s.label}</span>`;
}
