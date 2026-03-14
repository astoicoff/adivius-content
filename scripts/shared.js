const SUPABASE_URL     = "https://ptonwhknnjtudwvwwdnn.supabase.co";
const SUPABASE_ANON_KEY = "sb_publishable_3Xfgg2eUSJaybbLZTyaGIQ_wQ4tayHL";

const { createClient } = supabase;
const sb      = createClient(SUPABASE_URL, SUPABASE_ANON_KEY);
const API_URL = (window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1")
    ? "http://localhost:8000" : "";

let currentUser    = null;
let currentSession = null;
let cachedGroups   = [];

async function initAuth(onReady) {
    const { data } = await sb.auth.getSession();
    if (!data.session) { window.location.href = "/login"; return; }
    currentSession = data.session;
    currentUser    = data.session.user;
    renderUserInfo();
    loadHistoryBadge();
    if (onReady) await onReady();
}

function renderUserInfo() {
    const email = currentUser.email || "";
    document.getElementById("userEmail").textContent  = email;
    document.getElementById("userAvatar").textContent = email.charAt(0).toUpperCase();
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

function statusBadge(status) {
    const map = {
        pending:                 { cls: "badge-yellow", label: "Pending" },
        generating_instructions: { cls: "badge-blue",   label: "Generating Brief" },
        instructions_ready:      { cls: "badge-blue",   label: "Brief Ready" },
        generating_content:      { cls: "badge-red",    label: "Writing Content" },
        completed:               { cls: "badge-green",  label: "Complete" },
        failed:                  { cls: "badge-red",    label: "Failed" }
    };
    const s = map[status] || { cls: "badge-yellow", label: status };
    return `<span class="badge ${s.cls}"><span class="badge-dot"></span>${s.label}</span>`;
}
