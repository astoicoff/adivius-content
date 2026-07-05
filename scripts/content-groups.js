let editingGroupId   = null;
let currentGroupData = null;
let activePanelType  = null;

document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("navGroups").classList.add("active");
    initAuth(async () => {
        await loadGroups();
        const groupId = new URLSearchParams(window.location.search).get('group');
        if (groupId) {
            await openGroupEdit(groupId);
        } else {
            renderGroups(cachedGroups);
        }
    });
});

// ── List ─────────────────────────────────────────────────────────────────────

function showGroupsList() {
    closeRulesPanel();
    history.pushState({}, '', '/content-groups');
    document.getElementById("viewGroupsList").style.display = "";
    document.getElementById("viewGroupEdit").style.display  = "none";
    document.getElementById("topBarTitle").textContent      = "Content Groups";
    document.getElementById("topBarSubtitle").textContent   = "Manage your content group rules";
}

function renderGroups(groups) {
    const list = document.getElementById("groupsList");
    if (!list) return;
    if (!groups.length) {
        list.innerHTML = `<div class="no-groups-prompt">
            <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
            No content groups yet. Create your first one.
        </div>`;
        return;
    }
    list.innerHTML = groups.map(g => {
        const date  = new Date(g.created_at).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
        const count = g.generation_count || 0;
        const countBadge = count > 0
            ? `<span class="badge badge-blue" style="margin-left:8px;">${count} content${count !== 1 ? 's' : ''}</span>`
            : `<span class="badge badge-yellow" style="margin-left:8px;">No content yet</span>`;
        const sharedBadge = g.is_shared
            ? `<span class="badge badge-green" style="margin-left:6px;">${escapeHtml(g.my_role)}</span>`
            : '';
        return `<div class="group-card" onclick="openGroupEdit('${g.id}')">
            <div>
                <div class="group-card-name">${escapeHtml(g.name)} ${countBadge}${sharedBadge}</div>
                <div class="group-card-date">${g.is_shared ? 'Shared with you' : 'Created ' + date}</div>
            </div>
            <div class="group-card-arrow"><svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></div>
        </div>`;
    }).join("");
}

// ── Open group ────────────────────────────────────────────────────────────────

async function openGroupEdit(id) {
    editingGroupId   = id;
    currentGroupData = null;

    document.getElementById("groupEditAlert").className = "alert";
    document.getElementById("viewGroupsList").style.display = "none";
    document.getElementById("viewGroupEdit").style.display  = "";

    if (!id) {
        document.getElementById("groupDetailName").value     = "";
        document.getElementById("topBarTitle").textContent   = "New Content Group";
        document.getElementById("topBarSubtitle").textContent = "Create a new group";
        document.getElementById("groupContentList").innerHTML = `<div class="history-empty">
            <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Save the group first to start generating content.
        </div>`;
        try {
            const res  = await fetch(`${API_URL}/api/groups.php?defaults=1`, { headers: authHeaders() });
            const data = await res.json();
            currentGroupData = { name: '', instructions_rules: data.instructions_rules || '', content_rules: data.content_rules || '', generations: [] };
        } catch (_) {
            currentGroupData = { name: '', instructions_rules: '', content_rules: '', generations: [] };
        }
        openRulesPanel('instructions');
    } else {
        try {
            const res  = await fetch(`${API_URL}/api/groups.php?id=${encodeURIComponent(id)}`, { headers: authHeaders() });
            const data = await res.json();
            if (!res.ok) throw new Error(data.detail || 'Failed to load group.');
            currentGroupData = data;
            document.getElementById("groupDetailName").value      = data.name;
            document.getElementById("topBarTitle").textContent    = toTitleCase(data.name);
            document.getElementById("topBarSubtitle").textContent = "Content group details";

            // Role-based UI gating
            const role   = data.my_role || 'owner';
            const isOwner = role === 'owner';
            const isMod   = role === 'moderator' || isOwner;

            // Settings buttons only for owner
            document.querySelectorAll('#groupSettingsBtns .btn').forEach(btn => {
                if (btn.id === 'btnMembers') return;
                btn.style.display = isOwner ? '' : 'none';
            });
            // Group name editable only for owner
            document.getElementById("groupDetailName").readOnly = !isOwner;

            // Members button visible to owner only
            const btnMem = document.getElementById("btnMembers");
            if (btnMem) btnMem.style.display = isOwner ? '' : 'none';

            renderContentItems(data.generations || [], role);
        } catch (_) {
            document.getElementById("groupEditAlert").className   = "alert alert-error visible";
            document.getElementById("groupEditAlert").textContent = "Failed to load group.";
        }
    }
}

async function saveGroupName() {
    if (!editingGroupId) return;
    const name = document.getElementById("groupDetailName").value.trim();
    if (!name || name === currentGroupData?.name) return;
    try {
        await fetch(`${API_URL}/api/groups.php?id=${encodeURIComponent(editingGroupId)}`, {
            method: 'PATCH', headers: authHeaders(), body: JSON.stringify({ name })
        });
        if (currentGroupData) currentGroupData.name = name;
        document.getElementById("topBarTitle").textContent = toTitleCase(name);
        await loadGroups(); renderGroups(cachedGroups);
    } catch (_) {}
}

async function deleteGroup() {
    if (!editingGroupId) return;
    const name  = currentGroupData?.name || 'this group';
    const count = (currentGroupData?.generations || []).length;
    const noun  = count === 1 ? '1 piece of content' : `${count} pieces of content`;
    const msg   = count > 0
        ? `Delete "${name}"?\n\nThis will permanently delete ${noun} inside it. This cannot be undone.`
        : `Delete "${name}"? This cannot be undone.`;
    if (!confirm(msg)) return;

    try {
        const res = await fetch(`${API_URL}/api/groups.php?id=${encodeURIComponent(editingGroupId)}`, {
            method: 'DELETE', headers: authHeaders()
        });
        if (!res.ok) { const d = await res.json(); throw new Error(d.detail || 'Failed to delete group.'); }
        await loadGroups();
        showGroupsList();
        renderGroups(cachedGroups);
    } catch (e) {
        document.getElementById("groupEditAlert").className   = "alert alert-error visible";
        document.getElementById("groupEditAlert").textContent = e.message || 'Failed to delete group.';
    }
}

// ── Content items — simple links to view-content ──────────────────────────────

function renderContentItems(generations) {    renderAnalyticsChart(generations);

    const list = document.getElementById("groupContentList");
    if (!generations.length) {
        list.innerHTML = `<div class="history-empty">
            <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            No content generated in this group yet.
        </div>`;
        return;
    }

    list.innerHTML = generations.map(g => {
        const title  = toTitleCase(g.keyword);
        const date   = new Date(g.created_at).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric", hour: "2-digit", minute: "2-digit" });
        const url    = `/view-content?id=${encodeURIComponent(g.id)}&group=${encodeURIComponent(editingGroupId)}`;
        const wc     = g.content ? wordCount(g.content) : 0;
        const wcMeta = wc > 0 ? `<div class="word-count-meta">${wc.toLocaleString()} words · ${readingTime(wc)}${g.model ? ' · ' + escapeHtml(modelLabel(g.model)) : ''}</div>` : '';

        return `<a class="content-item" href="${url}" style="text-decoration:none;display:block;">
            <div class="content-item-header" style="cursor:pointer;">
                <div>
                    <div class="content-item-title">${escapeHtml(title)}</div>
                    <div class="content-item-date">${date}</div>
                    ${wcMeta}
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    ${statusBadge(g.status)}
                    <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:var(--text-muted);fill:none;stroke-width:2;flex-shrink:0;stroke-linecap:round;stroke-linejoin:round;"><path d="M9 18l6-6-6-6"/></svg>
                </div>
            </div>
        </a>`;
    }).join("");
}

// ── Rules panel ───────────────────────────────────────────────────────────────

async function openRulesPanel(type) {
    activePanelType = type;
    const isWP      = type === 'wordpress';
    const isWH      = type === 'webhook';
    const isNucleus = type === 'nucleus';
    const isInst    = type === 'instructions';
    const isRulesText = !isWP && !isWH && !isNucleus;

    const titles = {
        instructions: 'Instructions Rules', content: 'Content Rules',
        wordpress: 'WordPress Integration', webhook: 'Webhook on Completion',
        nucleus: 'Nucleus Integration',
    };
    const descs = {
        instructions: 'Used in Phase 1 to guide the content brief generation.',
        content:      'Used in Phase 2 to guide the final article writing.',
        wordpress:    'Connect this group to a WordPress site for one-click publishing.',
        webhook:      'POST every completed generation to a custom URL (Zapier, Make, your own endpoint).',
        nucleus:      'Link this group to a Nucleus client for direct handoff and publishing.',
    };
    document.getElementById("rulesPanelTitle").textContent = titles[type] || type;
    document.getElementById("rulesPanelDesc").textContent  = descs[type]  || '';

    document.getElementById("rulesTextarea").style.display        = isRulesText ? "" : "none";
    document.getElementById("wpFieldsSection").style.display      = isWP      ? "flex" : "none";
    document.getElementById("webhookFieldsSection").style.display = isWH      ? "flex" : "none";
    document.getElementById("nucleusFieldsSection").style.display = isNucleus ? "flex" : "none";

    if (isRulesText) {
        document.getElementById("rulesTextarea").value = isInst
            ? (currentGroupData?.instructions_rules || '')
            : (currentGroupData?.content_rules || '');
    } else if (isWP) {
        document.getElementById("wpSiteUrl").value     = currentGroupData?.wp_site_url || '';
        document.getElementById("wpUsername").value    = currentGroupData?.wp_username  || '';
        document.getElementById("wpAppPassword").value = '';
    } else if (isWH) {
        document.getElementById("webhookUrl").value = currentGroupData?.webhook_url || '';
        renderWebhookHeaders(currentGroupData?.webhook_headers);
    } else if (isNucleus) {
        await loadNucleusPanel(currentGroupData?.site_id || '');
    }

    const isNew = !editingGroupId;
    document.getElementById("groupNameRow").style.display = (isNew && isRulesText) ? "" : "none";
    if (isNew && isRulesText) document.getElementById("groupNameInput").value = currentGroupData?.name || '';

    document.getElementById("rulesSaveIndicator").classList.remove("visible");
    document.getElementById("rulesPanel").classList.add("open");
    document.getElementById("rulesOverlay").classList.add("visible");
}

// ── Webhook headers ──────────────────────────────────────────────────────────

const HEADER_NAME_RE = /^[A-Za-z0-9\-_]+$/;
const RESERVED_HEADER_NAMES = new Set(['content-type', 'user-agent']);

function renderWebhookHeaders(headers) {
    const list = document.getElementById("webhookHeadersList");
    list.innerHTML = '';
    const entries = (headers && typeof headers === 'object') ? Object.entries(headers) : [];
    if (entries.length === 0) {
        addWebhookHeaderRow();   // one empty row to hint at the UI
        return;
    }
    entries.forEach(([k, v]) => addWebhookHeaderRow(k, v));
}

function addWebhookHeaderRow(name = '', value = '') {
    const list = document.getElementById("webhookHeadersList");
    const row  = document.createElement("div");
    row.className = "webhook-header-row";
    row.style.cssText = "display:flex;gap:6px;align-items:stretch;";
    row.innerHTML = `
        <input type="text" class="form-input" placeholder="Header name"
            style="flex:0 0 40%;font-family:'Inter',monospace;font-size:12.5px;">
        <input type="text" class="form-input" placeholder="Value"
            style="flex:1;font-family:'Inter',monospace;font-size:12.5px;">
        <button type="button" class="btn btn-secondary" title="Remove"
            style="padding:0 10px;flex-shrink:0;"
            onclick="this.parentElement.remove()">
            <svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>`;
    const [nameEl, valueEl] = row.querySelectorAll('input');
    nameEl.value  = name;
    valueEl.value = value;
    list.appendChild(row);
}

// Returns {name: value} object, or null if a validation error was shown.
function collectWebhookHeaders() {
    const rows = document.querySelectorAll("#webhookHeadersList .webhook-header-row");
    const out  = {};
    for (const row of rows) {
        const [nameEl, valueEl] = row.querySelectorAll('input');
        const name  = (nameEl?.value  || '').trim();
        const value = (valueEl?.value || '').trim();
        if (!name && !value) continue;   // silently drop fully-empty rows
        if (!name) { showToast('Header value has no name.', 'warning'); return null; }
        if (!HEADER_NAME_RE.test(name)) { showToast(`Invalid header name: "${name}". Use letters, digits, -, _.`, 'warning'); return null; }
        if (RESERVED_HEADER_NAMES.has(name.toLowerCase())) { showToast(`"${name}" is managed automatically and cannot be set.`, 'warning'); return null; }
        if (!value) { showToast(`Header "${name}" needs a value.`, 'warning'); return null; }
        out[name] = value;
    }
    return out;
}

// Fetch clients + sites in parallel, then render the site dropdown. Sites
// are grouped by their client via <optgroup>, using client_id Nucleus puts
// on each site (contract v1). _nucleusSitesById caches the sites so save
// can look up the parent client_id from the chosen site.
let _nucleusSitesById = {};

async function loadNucleusPanel(selectedSiteId) {
    const siteSel = document.getElementById("nucleusSiteSelect");
    siteSel.innerHTML = '<option value="">Loading…</option>';

    try {
        const [clientsRes, sitesRes] = await Promise.all([
            fetch(`${API_URL}/api/nucleus/clients`, { headers: authHeaders() }),
            fetch(`${API_URL}/api/nucleus/sites`,   { headers: authHeaders() }),
        ]);
        const clients = await clientsRes.json();
        const sites   = await sitesRes.json();

        _nucleusSitesById = Array.isArray(sites)
            ? Object.fromEntries(sites.map(s => [s.id, s]))
            : {};

        siteSel.innerHTML = renderSiteOptions(sites, clients, selectedSiteId);
    } catch (_) {
        siteSel.innerHTML = '<option value="">— Could not reach Nucleus —</option>';
    }
    onNucleusSiteChange();
}

// Warn when the picked site has no client on Nucleus — Nucleus's inbound
// endpoint requires client_id, so Send-to-Nucleus will fail for these.
function onNucleusSiteChange() {
    const siteSel = document.getElementById("nucleusSiteSelect");
    const warning = document.getElementById("nucleusSiteWarning");
    if (!siteSel || !warning) return;
    const siteId = siteSel.value;
    const site   = siteId ? _nucleusSitesById[siteId] : null;
    warning.style.display = (site && !site.client_id) ? "" : "none";
}

function renderSiteOptions(sites, clients, selectedId) {
    if (!Array.isArray(sites)) {
        const msg = sites?._error === 'not_configured' ? '— Nucleus not configured —' : `— Error ${sites?.status || ''} —`;
        return `<option value="">${msg}</option>`;
    }
    const clientsById = Array.isArray(clients)
        ? Object.fromEntries(clients.map(c => [c.id, c.name]))
        : {};

    // Group sites by client name for readability. "Unassigned" comes last.
    const buckets = {};
    for (const s of sites) {
        const key = s.client_id ? (clientsById[s.client_id] || 'Unknown client') : 'Unassigned';
        (buckets[key] = buckets[key] || []).push(s);
    }
    const orderedKeys = Object.keys(buckets).sort((a, b) => {
        if (a === 'Unassigned') return 1;
        if (b === 'Unassigned') return -1;
        return a.localeCompare(b);
    });

    const groups = orderedKeys.map(key => {
        const opts = buckets[key].map(s => {
            const domain = s.domain ? ' — ' + escapeHtml(s.domain) : '';
            return `<option value="${escapeHtml(s.id)}"${s.id === selectedId ? ' selected' : ''}>${escapeHtml(s.name)}${domain}</option>`;
        }).join('');
        return `<optgroup label="${escapeHtml(key)}">${opts}</optgroup>`;
    }).join('');

    return '<option value="">— Not linked —</option>' + groups;
}
function closeRulesPanel() {
    document.getElementById("rulesPanel").classList.remove("open");
    document.getElementById("rulesOverlay").classList.remove("visible");
}

async function saveRules() {
    const isWP   = activePanelType === 'wordpress';
    const isWH   = activePanelType === 'webhook';
    const text   = document.getElementById("rulesTextarea").value;
    const isNew  = !editingGroupId;
    const isInst = activePanelType === 'instructions';

    if (isWP) {
        if (!editingGroupId) { showToast('Save the group first before configuring WordPress.', 'warning'); return; }
        const payload = {
            wp_site_url:  document.getElementById("wpSiteUrl").value.trim(),
            wp_username:  document.getElementById("wpUsername").value.trim(),
        };
        const pwd = document.getElementById("wpAppPassword").value.trim();
        if (pwd) payload.wp_app_password = pwd;
        try {
            const res = await fetch(API_URL + '/api/groups.php?id=' + encodeURIComponent(editingGroupId), {
                method: 'PATCH', headers: authHeaders(), body: JSON.stringify(payload)
            });
            if (!res.ok) throw new Error('Failed to save WordPress settings.');
            currentGroupData.wp_site_url = payload.wp_site_url;
            currentGroupData.wp_username = payload.wp_username;
            const ind = document.getElementById("rulesSaveIndicator");
            ind.classList.add("visible");
            setTimeout(() => ind.classList.remove("visible"), 2500);
        } catch (err) { showToast(err.message); }
        return;
    }

    if (isWH) {
        if (!editingGroupId) { showToast('Save the group first before configuring a webhook.', 'warning'); return; }
        const url = document.getElementById("webhookUrl").value.trim();
        if (url && !/^https?:\/\//i.test(url)) { showToast('Webhook URL must start with http:// or https://', 'warning'); return; }
        const headers = collectWebhookHeaders();
        if (headers === null) return;   // showToast already fired inside for invalid name
        try {
            const res = await fetch(API_URL + '/api/groups.php?id=' + encodeURIComponent(editingGroupId), {
                method: 'PATCH', headers: authHeaders(),
                body: JSON.stringify({ webhook_url: url, webhook_headers: headers })
            });
            if (!res.ok) throw new Error('Failed to save webhook.');
            currentGroupData.webhook_url     = url;
            currentGroupData.webhook_headers = headers;
            const ind = document.getElementById("rulesSaveIndicator");
            ind.classList.add("visible");
            setTimeout(() => ind.classList.remove("visible"), 2500);
        } catch (err) { showToast(err.message); }
        return;
    }

    if (activePanelType === 'nucleus') {
        if (!editingGroupId) { showToast('Save the group first before configuring Nucleus.', 'warning'); return; }
        const siteId = document.getElementById("nucleusSiteSelect").value || null;
        // Derive client_id from the site's parent — Nucleus's inbound endpoint
        // requires client_id even when site_id is provided.
        const clientId = siteId ? (_nucleusSitesById[siteId]?.client_id || null) : null;
        try {
            const res = await fetch(API_URL + '/api/groups.php?id=' + encodeURIComponent(editingGroupId), {
                method: 'PATCH', headers: authHeaders(),
                body: JSON.stringify({ site_id: siteId, client_id: clientId })
            });
            if (!res.ok) throw new Error('Failed to save Nucleus settings.');
            if (currentGroupData) {
                currentGroupData.site_id   = siteId;
                currentGroupData.client_id = clientId;
            }
            const ind = document.getElementById("rulesSaveIndicator");
            ind.classList.add("visible");
            setTimeout(() => ind.classList.remove("visible"), 2500);
        } catch (err) { showToast(err.message); }
        return;
    }

    if (isNew) {
        const name = document.getElementById("groupNameInput").value.trim();
        if (!name) { document.getElementById("groupNameInput").focus(); return; }
        const payload = {
            name,
            instructions_rules: isInst ? text : (currentGroupData?.instructions_rules || ''),
            content_rules:       isInst ? (currentGroupData?.content_rules || '') : text
        };
        try {
            const res  = await fetch(API_URL + '/api/groups.php', { method: 'POST', headers: authHeaders(), body: JSON.stringify(payload) });
            const data = await res.json();
            if (!res.ok) throw new Error(data.detail || 'Failed to create.');
            editingGroupId   = data.id;
            currentGroupData = { ...payload, id: data.id, generations: [] };
            document.getElementById("groupDetailName").value      = name;
            document.getElementById("topBarTitle").textContent    = toTitleCase(name);
            document.getElementById("topBarSubtitle").textContent = "Content group details";
            document.getElementById("groupContentList").innerHTML = '<div class="history-empty"><svg viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>No content generated in this group yet.</div>';
            document.getElementById("groupNameRow").style.display = "none";
            await loadGroups(); renderGroups(cachedGroups);
        } catch (err) { showToast(err.message); return; }
    } else {
        const payload = isInst ? { instructions_rules: text } : { content_rules: text };
        try {
            const res = await fetch(API_URL + '/api/groups.php?id=' + encodeURIComponent(editingGroupId), {
                method: 'PATCH', headers: authHeaders(), body: JSON.stringify(payload)
            });
            if (!res.ok) throw new Error('Failed to save.');
            if (isInst) currentGroupData.instructions_rules = text;
            else        currentGroupData.content_rules = text;
        } catch (err) { showToast(err.message); return; }
    }

    const ind = document.getElementById("rulesSaveIndicator");
    ind.classList.add("visible");
    setTimeout(() => ind.classList.remove("visible"), 2500);
}
// ── Group-level analytics chart ───────────────────────────────────────────────

let analyticsChartInstance = null;
let analyticsChartMode     = 'week';

function getISOWeek(date) {
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const day = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - day);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
}

function computeChartData(generations, mode) {
    const labels = [];
    const counts = {};
    const now    = new Date();

    if (mode === 'week') {
        for (let i = 7; i >= 0; i--) {
            const d   = new Date(now);
            d.setDate(d.getDate() - i * 7);
            const key = 'Wk ' + String(getISOWeek(d)).padStart(2, '0');
            if (!counts.hasOwnProperty(key)) { labels.push(key); counts[key] = 0; }
        }
        generations.forEach(g => {
            const d   = new Date(g.created_at);
            const key = 'Wk ' + String(getISOWeek(d)).padStart(2, '0');
            if (key in counts) counts[key]++;
        });
    } else {
        for (let i = 5; i >= 0; i--) {
            const d   = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const key = d.toLocaleString('en-US', { month: 'short', year: '2-digit' });
            labels.push(key);
            counts[key] = 0;
        }
        generations.forEach(g => {
            const d   = new Date(g.created_at);
            const key = d.toLocaleString('en-US', { month: 'short', year: '2-digit' });
            if (key in counts) counts[key]++;
        });
    }

    return { labels, data: labels.map(l => counts[l]) };
}

function renderAnalyticsChart(generations) {
    const card = document.getElementById("analyticsCard");
    if (!generations || !generations.length) { card.style.display = "none"; return; }
    card.style.display = "";

    const { labels, data } = computeChartData(generations, analyticsChartMode);
    const ctx = document.getElementById("analyticsChart").getContext("2d");

    if (analyticsChartInstance) { analyticsChartInstance.destroy(); analyticsChartInstance = null; }

    analyticsChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Generated',
                data,
                backgroundColor: 'rgba(52,152,219,0.55)',
                borderColor:     'rgba(52,152,219,1)',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { color: '#f0f0f0' } },
                x: { grid: { display: false } }
            }
        }
    });
}

function setChartMode(mode) {
    analyticsChartMode = mode;
    document.getElementById("chartWeekBtn").classList.toggle("btn-view-active", mode === 'week');
    document.getElementById("chartMonthBtn").classList.toggle("btn-view-active", mode === 'month');
    renderAnalyticsChart(currentGroupData?.generations || []);
}

// ── Members panel ─────────────────────────────────────────────────────────────

function openMembersPanel() {
    document.getElementById("membersPanel").classList.add("open");
    document.getElementById("membersOverlay").classList.add("visible");
    loadMembers();
}

function closeMembersPanel() {
    document.getElementById("membersPanel").classList.remove("open");
    document.getElementById("membersOverlay").classList.remove("visible");
}

async function loadMembers() {
    const body = document.getElementById("membersPanelBody");
    body.innerHTML = '<div class="loading-bar visible"><div class="spinner"></div> Loading…</div>';
    try {
        const res  = await fetch(`${API_URL}/api/members.php?action=list&group_id=${encodeURIComponent(editingGroupId)}`, { headers: authHeaders() });
        const data = await res.json();
        if (!res.ok) throw new Error(data.detail || 'Failed to load members.');
        renderMembersPanel(data.members || [], data.invites || []);
    } catch (err) {
        body.innerHTML = `<p style="color:var(--red);font-size:13px;padding:12px 0;">${escapeHtml(err.message)}</p>`;
    }
}

function _memberAvatar(name, bg) {
    const initial = (name || '?').charAt(0).toUpperCase();
    return `<div style="width:32px;height:32px;border-radius:50%;background:${bg};display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:700;flex-shrink:0;">${initial}</div>`;
}

function _memberAvatarBg(name) {
    const palette = ['#EE2D24','#008FD6','#6BBD45','#8B5CF6','#EC4899','#F59E0B'];
    let h = 0;
    for (const c of (name || '')) h = ((h * 31) + c.charCodeAt(0)) & 0xffff;
    return palette[h % palette.length];
}

function renderMembersPanel(members, invites) {
    const body = document.getElementById("membersPanelBody");
    let html = '';

    // Owner row — use the logged-in user's Nucleus profile
    const ownerName    = _nucleusProfile?.display_name || currentUser?.email || 'You';
    const ownerInitial = ownerName.charAt(0).toUpperCase();
    const ownerAvatarHtml = _nucleusProfile?.avatar_url
        ? `<img src="${escapeHtml(_nucleusProfile.avatar_url)}" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0;">`
        : `<div style="width:32px;height:32px;border-radius:50%;background:var(--blue);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:700;flex-shrink:0;">${ownerInitial}</div>`;

    html += `<div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--light-gray);">
        ${ownerAvatarHtml}
        <div style="flex:1;min-width:0;">
            <div style="font-size:13px;font-weight:600;color:var(--dark);font-family:'Inter',sans-serif;">${escapeHtml(ownerName)} <span style="color:var(--text-muted);font-weight:400;">(you)</span></div>
        </div>
        <span class="badge badge-blue">owner</span>
    </div>`;

    members.forEach(m => {
        const name  = m.display_name || 'Member';
        const label = escapeHtml(name);
        const roleColors = { moderator: 'badge-yellow', viewer: 'badge-green' };
        html += `<div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--light-gray);">
            ${_memberAvatar(name, _memberAvatarBg(name))}
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:600;color:var(--dark);font-family:'Inter',sans-serif;">${label}</div>
            </div>
            <select style="font-size:11px;padding:3px 6px;border:1px solid var(--light-gray);border-radius:4px;background:var(--off-white);color:var(--dark);font-family:'Inter',sans-serif;" onchange="changeMemberRole('${m.id}', this.value)">
                <option value="moderator" ${m.role==='moderator'?'selected':''}>Moderator</option>
                <option value="viewer" ${m.role==='viewer'?'selected':''}>Viewer</option>
            </select>
            <button style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:18px;line-height:1;" onclick="removeMember('${m.id}', 'member')" title="Remove">×</button>
        </div>`;
    });

    if (invites.length) {
        html += `<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--text-muted);margin:14px 0 6px;">Pending Invites</div>`;
        invites.forEach(inv => {
            const inviteUrl = inv.token ? window.location.origin + '/accept-invite?token=' + encodeURIComponent(inv.token) : '';
            html += `<div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--light-gray);">
                <div style="flex:1;min-width:0;">
                    <div style="font-size:12px;font-family:'Inter',sans-serif;color:var(--dark);">${escapeHtml(inv.email)}</div>
                    <div style="font-size:11px;color:var(--text-muted);">${escapeHtml(inv.role)}</div>
                </div>
                <span class="badge badge-yellow">pending</span>
                ${inviteUrl ? `<button class="btn btn-secondary" style="padding:3px 10px;font-size:11px;flex-shrink:0;" onclick="copyInviteUrl('${escapeHtml(inviteUrl)}', this)">Copy link</button>` : ''}
                <button style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:18px;line-height:1;" onclick="removeMember('${inv.id}', 'invite')" title="Cancel">×</button>
            </div>`;
        });
    }

    if (!members.length && !invites.length) {
        html += `<p style="font-size:13px;color:var(--text-muted);padding:16px 0;font-family:'Inter',sans-serif;">No team members yet. Invite someone below.</p>`;
    }

    body.innerHTML = html;
}

async function changeMemberRole(memberId, newRole) {
    try {
        const res = await fetch(`${API_URL}/api/members.php?action=update_role&id=${encodeURIComponent(memberId)}`, {
            method: 'PATCH', headers: authHeaders(), body: JSON.stringify({ role: newRole })
        });
        if (!res.ok) { const d = await res.json(); throw new Error(d.detail || 'Failed.'); }
    } catch (err) {
        showToast(err.message);
        loadMembers();
    }
}

async function removeMember(id, type) {
    const label = type === 'invite' ? 'Cancel this invite?' : 'Remove this member?';
    if (!confirm(label)) return;
    try {
        const res = await fetch(`${API_URL}/api/members.php?action=remove&id=${encodeURIComponent(id)}&type=${type}`, {
            method: 'DELETE', headers: authHeaders()
        });
        if (!res.ok) { const d = await res.json(); throw new Error(d.detail || 'Failed.'); }
        loadMembers();
    } catch (err) {
        showToast(err.message);
    }
}

// ── Invite modal ──────────────────────────────────────────────────────────────

function openInviteForm() {
    document.getElementById("inviteEmail").value  = '';
    document.getElementById("inviteRole").value   = 'moderator';
    document.getElementById("inviteResult").style.display = 'none';
    document.getElementById("inviteModal").classList.add("open");
    document.getElementById("inviteOverlay").classList.add("visible");
}

function closeInviteModal() {
    document.getElementById("inviteModal").classList.remove("open");
    document.getElementById("inviteOverlay").classList.remove("visible");
}

async function sendInvite() {
    const email   = document.getElementById("inviteEmail").value.trim();
    const role    = document.getElementById("inviteRole").value;
    const btn     = document.getElementById("inviteBtn");
    const result  = document.getElementById("inviteResult");
    const origTxt = btn.innerHTML;

    if (!email) { document.getElementById("inviteEmail").focus(); return; }

    btn.disabled  = true;
    btn.textContent = 'Generating…';
    result.style.display = 'none';

    try {
        const res  = await fetch(`${API_URL}/api/members.php?action=invite`, {
            method: 'POST', headers: authHeaders(),
            body: JSON.stringify({ group_id: editingGroupId, email, role })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.detail || 'Failed to create invite.');

        result.style.display = '';
        result.innerHTML = `
            <div style="font-size:13px;font-weight:600;color:var(--dark);margin-bottom:8px;font-family:'Inter',sans-serif;">Invite link ready — copy and share it:</div>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" class="form-input" readonly value="${escapeHtml(data.invite_url)}" style="font-size:11px;font-family:monospace;" id="inviteLinkInput">
                <button class="btn btn-secondary" style="flex-shrink:0;" onclick="copyInviteLink()">Copy</button>
            </div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:6px;font-family:'Inter',sans-serif;">Expires in 7 days.</div>`;

        // Refresh members list in background
        loadMembers();
    } catch (err) {
        result.style.display = '';
        result.innerHTML = `<div style="font-size:13px;color:var(--red);font-family:'Inter',sans-serif;">${escapeHtml(err.message)}</div>`;
    } finally {
        btn.disabled  = false;
        btn.innerHTML = origTxt;
    }
}

function copyInviteUrl(url, btn) {
    navigator.clipboard.writeText(url).then(() => {
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => { btn.textContent = orig; }, 1800);
    });
}

function copyInviteLink() {
    const inp = document.getElementById("inviteLinkInput");
    if (!inp) return;
    navigator.clipboard.writeText(inp.value).then(() => {
        const btn = inp.nextElementSibling;
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => { btn.textContent = orig; }, 1800);
    });
}
