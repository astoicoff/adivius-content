let allGenerations = [];

document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("navHistory").classList.add("active");
    initAuth(async () => {
        await loadGroups();
        populateGroupFilter();
        await loadHistoryData();
    });
});

function populateGroupFilter() {
    const sel = document.getElementById("historyGroupFilter");
    cachedGroups.forEach(g => {
        const opt = document.createElement('option');
        opt.value = g.id;
        opt.textContent = g.name;
        sel.appendChild(opt);
    });
}

async function loadHistoryData() {
    try {
        const res  = await fetch(`${API_URL}/api/history.php`, { headers: authHeaders() });
        if (!res.ok) return;
        const data = await res.json();
        allGenerations = data.generations || [];
        applyFilters();
    } catch (_) {}
}

function applyFilters() {
    const search  = document.getElementById("historySearch").value.toLowerCase();
    const status  = document.getElementById("historyStatusFilter").value;
    const groupId = document.getElementById("historyGroupFilter").value;

    const filtered = allGenerations.filter(g => {
        if (search  && !g.keyword.toLowerCase().includes(search)) return false;
        if (status  && g.status !== status) return false;
        if (groupId && g.group_id !== groupId) return false;
        return true;
    });
    renderHistory(filtered);
}

function renderHistory(generations) {
    const list  = document.getElementById("historyList");
    const badge = document.getElementById("historyBadge");

    if (!allGenerations.length) {
        list.innerHTML = `<div class="history-empty">
            <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            No generations yet. <a href="/new-content">Create your first content →</a>
        </div>`;
        badge.style.display = "none";
        return;
    }

    badge.style.display = "";
    badge.textContent   = allGenerations.length;

    if (!generations.length) {
        list.innerHTML = `<div class="history-empty">
            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            No results match your filters.
        </div>`;
        return;
    }

    list.innerHTML = generations.map(g => {
        const date       = new Date(g.created_at).toLocaleDateString("en-US", {
            month: "short", day: "numeric", year: "numeric", hour: "2-digit", minute: "2-digit"
        });
        const group      = cachedGroups.find(gr => gr.id === g.group_id);
        const groupBadge = group ? `<span style="font-size:11px;color:var(--text-muted);font-weight:500;">${escapeHtml(group.name)}</span>` : '';
        const isComplete   = g.status === "completed";
        const isResumable  = g.status === "instructions_ready";
        const viewLink     = isComplete
            ? `<a href="/view-content?id=${encodeURIComponent(g.id)}&group=${encodeURIComponent(g.group_id || '')}" class="btn btn-secondary" style="padding:6px 12px;font-size:12px;">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View
               </a>`
            : isResumable
            ? `<a href="/new-content?resume=${encodeURIComponent(g.id)}" class="btn btn-secondary" style="padding:6px 12px;font-size:12px;">
                <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.95"/></svg>
                Continue
               </a>`
            : '';
        const deleteBtn = `<button class="btn btn-red" style="padding:6px 10px;font-size:12px;" onclick="deleteGeneration('${escapeHtml(g.id)}', this)">
                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
            </button>`;
        return `<div class="history-item" id="hitem-${escapeHtml(g.id)}">
            <div>
                <div class="history-keyword">${escapeHtml(toTitleCase(g.keyword))}</div>
                <div class="history-date">${date}</div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                ${groupBadge}
                ${statusBadge(g.status)}
                ${viewLink}
                ${deleteBtn}
            </div>
        </div>`;
    }).join("");
}

async function deleteGeneration(id, btn) {
    if (!confirm('Delete this generation? This cannot be undone.')) return;
    btn.disabled = true;
    try {
        const res = await fetch(API_URL + '/api/generation.php?id=' + encodeURIComponent(id), {
            method: 'DELETE', headers: authHeaders()
        });
        if (!res.ok) { const d = await res.json(); throw new Error(d.detail || 'Delete failed.'); }
        allGenerations = allGenerations.filter(g => g.id !== id);
        applyFilters();
    } catch (err) {
        alert(err.message);
        btn.disabled = false;
    }
}
