document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("navDashboard").classList.add("active");
    initAuth(async () => {
        await Promise.all([loadStats(), loadGroups()]);
    });
});

async function loadStats() {
    try {
        const res  = await fetch(`${API_URL}/api/stats.php`, { headers: authHeaders() });
        if (!res.ok) return;
        const data = await res.json();

        document.getElementById("statTotal").textContent  = data.total  ?? '—';
        document.getElementById("statMonth").textContent  = data.this_month ?? '—';
        document.getElementById("statGroups").textContent = data.groups ?? '—';

        if (data.most_active_group) {
            document.getElementById("statTopGroup").textContent    = data.most_active_group;
            document.getElementById("statTopGroupSub").textContent = `Most active group with ${data.most_active_count} generation${data.most_active_count !== 1 ? 's' : ''}`;
        } else {
            document.getElementById("statTopGroup").textContent = '—';
        }

        renderRecent(data.recent || []);
    } catch (_) {}
}

function renderRecent(items) {
    const list = document.getElementById("recentList");
    if (!items.length) {
        list.innerHTML = `<div class="history-empty">
            <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            No generations yet. <a href="/new-content">Create your first →</a>
        </div>`;
        return;
    }

    list.innerHTML = items.map(g => {
        const group    = cachedGroups.find(gr => gr.id === g.group_id);
        const groupTxt = group ? ` · ${escapeHtml(group.name)}` : '';
        const date     = new Date(g.created_at).toLocaleDateString("en-US", {
            month: "short", day: "numeric", year: "numeric"
        });
        const isComplete = g.status === "completed";
        const viewLink = isComplete
            ? `<a href="/view-content?id=${encodeURIComponent(g.id)}&group=${encodeURIComponent(g.group_id || '')}" class="btn btn-secondary" style="padding:5px 10px;font-size:12px;">View</a>`
            : '';
        return `<div class="activity-item">
            <div>
                <div class="activity-kw">${escapeHtml(toTitleCase(g.keyword))}</div>
                <div class="activity-meta">${date}${groupTxt}</div>
            </div>
            <div class="activity-right">
                ${statusBadge(g.status)}
                ${viewLink}
            </div>
        </div>`;
    }).join("");
}
