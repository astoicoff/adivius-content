let allImages      = [];
let activeGroupId  = null;
let groupMap       = {};   // id → name

// ── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('navImages').classList.add('active');
    initAuth(async () => {
        await Promise.all([loadGroups(), loadImages(null)]);
    });
});

// ── Groups ────────────────────────────────────────────────────────────────────

async function loadGroups() {
    try {
        const res  = await fetch(`${API_URL}/api/groups.php`, { headers: authHeaders() });
        const data = await res.json();
        const groups = data.groups || [];
        groups.forEach(g => { groupMap[g.id] = g.name; });

        const bar = document.getElementById('filterBar');
        // Insert chips after the "All" chip, before the status select
        const sel = document.getElementById('statusFilter');
        groups.forEach(g => {
            const btn = document.createElement('button');
            btn.className     = 'gallery-chip';
            btn.dataset.group = g.id;
            btn.innerHTML     = `<span class="gallery-chip-dot"></span>${g.name}`;
            btn.onclick       = () => setGroupFilter(g.id);
            bar.insertBefore(btn, sel);
        });
    } catch (_) {}
}

// ── Load Images ───────────────────────────────────────────────────────────────

async function loadImages(groupId) {
    document.getElementById('loadingState').classList.add('visible');
    document.getElementById('contentArea').style.display = 'none';
    document.getElementById('errorState').style.display  = 'none';

    try {
        const url = groupId
            ? `${API_URL}/api/images.php?group_id=${encodeURIComponent(groupId)}`
            : `${API_URL}/api/images.php?limit=200`;
        const res  = await fetch(url, { headers: authHeaders() });
        if (!res.ok) throw new Error('Failed to load images.');
        const data = await res.json();
        allImages  = data.images || [];
        document.getElementById('loadingState').classList.remove('visible');
        document.getElementById('contentArea').style.display = '';
        applyFilters();
    } catch (err) {
        document.getElementById('loadingState').classList.remove('visible');
        document.getElementById('errorState').style.display  = 'flex';
        document.getElementById('errorText').textContent     = err.message;
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────

function setGroupFilter(groupId) {
    activeGroupId = groupId;

    // Update chip active states
    document.getElementById('chipAll').classList.toggle('active', groupId === null);
    document.querySelectorAll('.gallery-chip[data-group]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.group === groupId);
    });

    loadImages(groupId);
}

function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const filtered = status
        ? allImages.filter(img => img.status === status)
        : allImages;

    renderGrid(filtered);
}

// ── Render ────────────────────────────────────────────────────────────────────

function ratioClass(size) {
    if (size === '1024x1024') return 'square';
    if (size === '1024x1792') return 'portrait';
    return 'landscape';
}

function renderGrid(images) {
    const grid  = document.getElementById('galleryGrid');
    const empty = document.getElementById('emptyState');
    const count = document.getElementById('galleryCount');

    count.textContent = images.length;

    if (!images.length) {
        grid.innerHTML     = '';
        empty.style.display = '';
        return;
    }
    empty.style.display = 'none';

    grid.innerHTML = images.map(img => {
        const ratio     = ratioClass(img.size);
        const groupName = groupMap[img.group_id] || '';
        const date      = new Date(img.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        const href      = `/view-image?id=${encodeURIComponent(img.id)}${img.group_id ? '&group=' + encodeURIComponent(img.group_id) : ''}`;
        const hasImage  = !!(img.image_url && img.status === 'completed');

        let inner;
        if (hasImage) {
            const chips = [
                img.agent_name ? `<span class="gallery-chip-meta" title="${escHtml(img.agent_name)}">${escHtml(img.agent_name)}</span>` : '',
                groupName      ? `<span class="gallery-chip-meta" title="${escHtml(groupName)}">${escHtml(groupName)}</span>` : '',
            ].filter(Boolean).join('');
            inner = `<img class="gallery-img" src="${escHtml(img.image_url)}" alt="${escHtml(img.keyword)}" loading="lazy">`
                + `<div class="gallery-overlay">`
                +   `<div class="gallery-overlay-title">${escHtml(img.keyword)}</div>`
                +   `<div class="gallery-overlay-meta">`
                +     `<div style="display:flex;gap:5px;align-items:center;min-width:0;overflow:hidden;">${chips}</div>`
                +     `<div class="gallery-overlay-date">${escHtml(date)}</div>`
                +   `</div>`
                + `</div>`;
        } else if (img.status === 'generating_image' || img.status === 'generating_prompt') {
            inner = `<div class="gallery-shimmer"></div>`;
        } else {
            const icon = img.status === 'failed'
                ? `<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`
                : `<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>`;
            inner = `<div class="gallery-noimg">${icon}`
                + `<div class="gallery-noimg-title">${escHtml(img.keyword)}</div>`
                + `<div class="gallery-noimg-label">${escHtml(groupName || '')}${groupName ? ' · ' : ''}${escHtml(date)}</div>`
                + `</div>`;
        }
        if (img.status !== 'completed') {
            inner += `<div class="gallery-status-pin">${statusBadge(img.status)}</div>`;
        }

        return `<a class="gallery-card ${ratio}" href="${escHtml(href)}">${inner}</a>`;
    }).join('');
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
