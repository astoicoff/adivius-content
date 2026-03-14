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
        const groupLabel = group ? ` · ${escapeHtml(group.name)}` : '';
        const isComplete = g.status === "completed";
        const viewLink   = isComplete
            ? `<a href="/view-content?id=${encodeURIComponent(g.id)}&group=${encodeURIComponent(g.group_id || '')}" class="btn btn-secondary" style="padding:6px 12px;font-size:12px;">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View
               </a>`
            : '';
        return `<div class="history-item">
            <div>
                <div class="history-keyword">${escapeHtml(toTitleCase(g.keyword))}</div>
                <div class="history-date">${date}${groupLabel}</div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                ${statusBadge(g.status)}
                ${viewLink}
            </div>
        </div>`;
    }).join("");
}
