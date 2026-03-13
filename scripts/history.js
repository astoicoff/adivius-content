document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("navHistory").classList.add("active");
    initAuth(async () => {
        await loadHistoryData();
    });
});

async function loadHistoryData() {
    try {
        const res  = await fetch(`${API_URL}/api/history.php`, { headers: authHeaders() });
        if (!res.ok) return;
        const data = await res.json();
        renderHistory(data.generations || []);
    } catch (_) {}
}

function renderHistory(generations) {
    const list  = document.getElementById("historyList");
    const badge = document.getElementById("historyBadge");

    if (!generations.length) {
        list.innerHTML = `<div class="history-empty">
            <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            No generations yet. <a href="/new-content">Create your first content →</a>
        </div>`;
        badge.style.display = "none";
        return;
    }

    badge.style.display = "";
    badge.textContent   = generations.length;

    list.innerHTML = generations.map(g => {
        const date       = new Date(g.created_at).toLocaleDateString("en-US", {
            month: "short", day: "numeric", year: "numeric", hour: "2-digit", minute: "2-digit"
        });
        const isComplete = g.status === "completed";
        const viewLink   = isComplete
            ? `<a href="/view-content?id=${g.id}" class="btn btn-secondary" style="padding:6px 12px;font-size:12px;">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View
               </a>`
            : '';
        return `<div class="history-item">
            <div>
                <div class="history-keyword">${escapeHtml(g.keyword)}</div>
                <div class="history-date">${date}</div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                ${statusBadge(g.status)}
                ${viewLink}
            </div>
        </div>`;
    }).join("");
}
