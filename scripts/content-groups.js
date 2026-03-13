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
        return `<div class="group-card" onclick="openGroupEdit('${g.id}')">
            <div>
                <div class="group-card-name">${escapeHtml(g.name)} ${countBadge}</div>
                <div class="group-card-date">Created ${date}</div>
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
            currentGroupData = data;
            document.getElementById("groupDetailName").value      = data.name;
            document.getElementById("topBarTitle").textContent    = toTitleCase(data.name);
            document.getElementById("topBarSubtitle").textContent = "Content group details";
            renderContentItems(data.generations || []);
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

// ── Content items — simple links to view-content ──────────────────────────────

function renderContentItems(generations) {
    const list = document.getElementById("groupContentList");
    if (!generations.length) {
        list.innerHTML = `<div class="history-empty">
            <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            No content generated in this group yet.
        </div>`;
        return;
    }

    list.innerHTML = generations.map(g => {
        const title = toTitleCase(g.keyword);
        const date  = new Date(g.created_at).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric", hour: "2-digit", minute: "2-digit" });
        const url   = `/view-content?id=${encodeURIComponent(g.id)}&group=${encodeURIComponent(editingGroupId)}`;

        return `<a class="content-item" href="${url}" style="text-decoration:none;display:block;">
            <div class="content-item-header" style="cursor:pointer;">
                <div>
                    <div class="content-item-title">${escapeHtml(title)}</div>
                    <div class="content-item-date">${date}</div>
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

function openRulesPanel(type) {
    activePanelType = type;
    const isInst = type === 'instructions';
    document.getElementById("rulesPanelTitle").textContent = isInst ? "Instructions Rules" : "Content Rules";
    document.getElementById("rulesPanelDesc").textContent  = isInst
        ? "Used in Phase 1 to guide the content brief generation."
        : "Used in Phase 2 to guide the final article writing.";
    document.getElementById("rulesTextarea").value = isInst
        ? (currentGroupData?.instructions_rules || '')
        : (currentGroupData?.content_rules || '');

    const isNew = !editingGroupId;
    document.getElementById("groupNameRow").style.display = isNew ? "" : "none";
    if (isNew) document.getElementById("groupNameInput").value = currentGroupData?.name || '';

    document.getElementById("rulesSaveIndicator").classList.remove("visible");
    document.getElementById("rulesPanel").classList.add("open");
    document.getElementById("rulesOverlay").classList.add("visible");
}

function closeRulesPanel() {
    document.getElementById("rulesPanel").classList.remove("open");
    document.getElementById("rulesOverlay").classList.remove("visible");
}

async function saveRules() {
    const text   = document.getElementById("rulesTextarea").value;
    const isNew  = !editingGroupId;
    const isInst = activePanelType === 'instructions';

    if (isNew) {
        const name = document.getElementById("groupNameInput").value.trim();
        if (!name) { document.getElementById("groupNameInput").focus(); return; }
        const payload = {
            name,
            instructions_rules: isInst ? text : (currentGroupData?.instructions_rules || ''),
            content_rules:       isInst ? (currentGroupData?.content_rules || '') : text
        };
        try {
            const res  = await fetch(`${API_URL}/api/groups.php`, { method: 'POST', headers: authHeaders(), body: JSON.stringify(payload) });
            const data = await res.json();
            if (!res.ok) throw new Error(data.detail || 'Failed to create.');
            editingGroupId   = data.id;
            currentGroupData = { ...payload, id: data.id, generations: [] };
            document.getElementById("groupDetailName").value      = name;
            document.getElementById("topBarTitle").textContent    = toTitleCase(name);
            document.getElementById("topBarSubtitle").textContent = "Content group details";
            document.getElementById("groupContentList").innerHTML = `<div class="history-empty">
                <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                No content generated in this group yet.
            </div>`;
            document.getElementById("groupNameRow").style.display = "none";
            await loadGroups(); renderGroups(cachedGroups);
        } catch (err) { alert(err.message); return; }
    } else {
        const payload = isInst ? { instructions_rules: text } : { content_rules: text };
        try {
            const res = await fetch(`${API_URL}/api/groups.php?id=${encodeURIComponent(editingGroupId)}`, {
                method: 'PATCH', headers: authHeaders(), body: JSON.stringify(payload)
            });
            if (!res.ok) throw new Error('Failed to save.');
            if (isInst) currentGroupData.instructions_rules = text;
            else        currentGroupData.content_rules = text;
        } catch (err) { alert(err.message); return; }
    }

    const ind = document.getElementById("rulesSaveIndicator");
    ind.classList.add("visible");
    setTimeout(() => ind.classList.remove("visible"), 2500);
}
