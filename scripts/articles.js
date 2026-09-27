// Articles — every generation the user can see, one section per stage.
// Sort (?sort=title|updated|created, ?dir=asc|desc) and the group filter
// (?group=<id>) live in the URL, like Nucleus's /tasks, so a view is linkable.

let allArticles = [];
let groupNames  = {};

const STAGES = [
    { key: 'progress',  label: 'In progress' },
    { key: 'brief',     label: 'Brief ready' },
    { key: 'ready',     label: 'Ready' },
    { key: 'nucleus',   label: 'In Nucleus' },
    { key: 'published', label: 'Published' },
    { key: 'failed',    label: 'Failed' },
];

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('navArticles').classList.add('active');
    initAuth(loadArticles);
});

async function loadArticles() {
    try {
        const res  = await fetch(`${API_URL}/api/articles`, { headers: authHeaders() });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.detail || 'Failed to load articles.');
        allArticles = data.articles || [];
        groupNames  = data.groups   || {};
        render();
    } catch (err) {
        document.getElementById('errorText').textContent = err.message;
        document.getElementById('errorState').classList.add('visible');
    } finally {
        document.getElementById('loadingState').classList.remove('visible');
    }
}

function stageOf(a) {
    if (a.status === 'failed')                          return 'failed';
    if (a.status === 'published' || a.published_at)    return 'published';
    if (a.handed_off_at)                                return 'nucleus';
    if (a.status === 'completed')                       return 'ready';
    if (a.status === 'instructions_ready')              return 'brief';
    return 'progress';
}

function params() {
    const p = new URLSearchParams(location.search);
    const sort = ['title', 'updated', 'created'].includes(p.get('sort')) ? p.get('sort') : 'created';
    return { sort, dir: p.get('dir') === 'asc' ? 'asc' : 'desc', group: p.get('group') || '' };
}

function setParam(key, value) {
    const p = new URLSearchParams(location.search);
    value ? p.set(key, value) : p.delete(key);
    history.replaceState(null, '', location.pathname + (p.toString() ? '?' + p : ''));
    render();
}

function toggleSort(key) {
    const { sort, dir } = params();
    const p = new URLSearchParams(location.search);
    p.set('sort', key);
    p.set('dir', sort === key && dir === 'desc' ? 'asc' : 'desc');
    history.replaceState(null, '', location.pathname + '?' + p);
    render();
}

function compare(a, b, sort, dir) {
    const sign = dir === 'asc' ? 1 : -1;
    if (sort === 'title') return sign * (a.keyword || '').localeCompare(b.keyword || '');
    const field = sort === 'updated' ? 'updated_at' : 'created_at';
    return sign * (new Date(a[field] || 0) - new Date(b[field] || 0));
}

function render() {
    const { sort, dir, group } = params();

    const chip = document.getElementById('filterChip');
    chip.innerHTML = group
        ? `<span class="art-filter">${escapeHtml(groupNames[group] || 'Content group')}
             <a href="#" onclick="setParam('group','');return false;" aria-label="Clear group filter">×</a></span>`
        : '';

    const rows     = allArticles.filter(a => !group || a.group_id === group);
    const sections = document.getElementById('sections');

    if (!rows.length) {
        sections.innerHTML = `<div class="art-empty">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
            <h3>${group ? 'No articles in this group' : 'No articles yet'}</h3>
            <p>Type a keyword above to start your first one.</p>
        </div>`;
        return;
    }

    sections.innerHTML = STAGES.map(stage => {
        const items = rows.filter(a => stageOf(a) === stage.key).sort((a, b) => compare(a, b, sort, dir));
        if (!items.length) return '';
        return `<section>
            <div class="art-group-head">
                <span class="art-pill st-${stage.key}"><span class="art-dot"></span>${stage.label}</span>
                <span class="art-count">${items.length}</span>
            </div>
            <div class="art-table-wrap">
                <table class="art-table">
                    <colgroup><col><col class="c-status"><col class="c-group"><col class="c-model"><col class="c-date"><col class="c-date"></colgroup>
                    <thead><tr>
                        ${th('Article', 'title', sort, dir)}
                        <th>Status</th>
                        <th>Group</th>
                        <th>Model</th>
                        ${th('Updated', 'updated', sort, dir)}
                        ${th('Created', 'created', sort, dir)}
                    </tr></thead>
                    <tbody>${items.map(row).join('')}</tbody>
                </table>
            </div>
        </section>`;
    }).join('');
}

function th(label, key, sort, dir) {
    const arrow = sort === key ? `<span class="arrow">${dir === 'asc' ? '▲' : '▼'}</span>` : '';
    return `<th class="sortable" onclick="toggleSort('${key}')">${label}${arrow}</th>`;
}

function row(a) {
    const stage = stageOf(a);
    const label = STAGES.find(s => s.key === stage).label;
    const title = escapeHtml(toTitleCase(a.keyword));

    // Where the title goes depends on what can be done with the piece now.
    let href = null;
    if (stage === 'brief')       href = `/new-content?resume=${encodeURIComponent(a.id)}`;
    else if (stage === 'failed') href = `/new-content?keyword=${encodeURIComponent(a.keyword || '')}`;
    else if (stage !== 'progress') {
        href = `/view-content?id=${encodeURIComponent(a.id)}` + (a.group_id ? `&group=${encodeURIComponent(a.group_id)}` : '');
    }

    let sub = '';
    if (a.nucleus_publish_error)  sub = `<div class="art-sub error" title="${escAttr(a.nucleus_publish_error)}">Publish failed: ${escapeHtml(a.nucleus_publish_error)}</div>`;
    else if (stage === 'failed')  sub = `<div class="art-sub">Open to retry with the same keyword</div>`;
    else if (stage === 'brief')   sub = `<div class="art-sub">Open to continue writing</div>`;
    else if (a.wp_post_url)       sub = `<div class="art-sub">${escapeHtml(a.wp_post_url)}</div>`;

    const titleEl = href
        ? `<a class="art-title" href="${escAttr(href)}" title="${escAttr(toTitleCase(a.keyword))}">${title}</a>`
        : `<span class="art-title" title="${escAttr(toTitleCase(a.keyword))}">${title}</span>`;

    const groupName = a.group_id ? groupNames[a.group_id] : '';
    const groupEl   = groupName
        ? `<a class="art-chip" href="#" onclick="setParam('group','${escAttr(a.group_id)}');return false;" title="Show only ${escAttr(groupName)}">${escapeHtml(groupName)}</a>`
        : '<span class="art-muted">—</span>';

    return `<tr>
        <td>${titleEl}${sub}</td>
        <td><span class="art-pill art-pill-sm st-${stage}"><span class="art-dot"></span>${label}</span></td>
        <td>${groupEl}</td>
        <td class="art-muted">${escapeHtml(modelLabel(a.model)) || '—'}</td>
        <td class="art-muted">${fmtDate(a.updated_at)}</td>
        <td class="art-muted">${fmtDate(a.created_at)}</td>
    </tr>`;
}

function fmtDate(iso) {
    return iso ? new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
}

function escAttr(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function quickAdd(e) {
    e.preventDefault();
    const kw = document.getElementById('quickAddInput').value.trim();
    if (!kw) return false;
    const { group } = params();
    location.href = '/new-content?keyword=' + encodeURIComponent(kw) + (group ? '&group=' + encodeURIComponent(group) : '');
    return false;
}
