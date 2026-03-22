let genData         = null;
let genCleanContent = null;   // raw content with meta lines stripped
let viewMode        = 'html';
let versionsData    = [];

// Parse h1/title/url meta lines from the top of raw content.
// Returns { meta: {h1,title,url} | null, content: string }
function parseContentMeta(raw) {
    if (!raw) return { meta: null, content: raw };
    const lines = raw.split('\n');
    const meta  = {};
    let end     = 0;

    for (let i = 0; i < Math.min(lines.length, 15); i++) {
        const line = lines[i];
        const trim = line.trim();
        if (!trim) {
            if (Object.keys(meta).length) { end = i + 1; break; }
            continue;
        }
        if (trim.startsWith('<')) { end = i; break; }
        const m = trim.match(/^(h1|title|url)\s*:\s*(.+)$/i);
        if (m) { meta[m[1].toLowerCase()] = m[2].trim(); end = i + 1; }
        else break;
    }

    if (!Object.keys(meta).length) return { meta: null, content: raw };
    return { meta, content: lines.slice(end).join('\n').trim() };
}

function showMetaPanel(meta) {
    const panel = document.getElementById("metaPanel");
    if (!meta) { panel.style.display = "none"; return; }

    const fields = [
        { row: "metaRowH1",    el: "metaH1",    val: meta.h1    },
        { row: "metaRowTitle", el: "metaTitle",  val: meta.title },
        { row: "metaRowUrl",   el: "metaUrl",    val: meta.url   },
    ];
    let any = false;
    fields.forEach(({ row, el, val }) => {
        const rowEl = document.getElementById(row);
        if (val) {
            document.getElementById(el).textContent = val;
            rowEl.style.display = "";
            any = true;
        } else {
            rowEl.style.display = "none";
        }
    });
    panel.style.display = any ? "" : "none";
}

document.addEventListener("DOMContentLoaded", () => {
    const params  = new URLSearchParams(window.location.search);
    const id      = params.get("id");
    const groupId = params.get("group");

    if (!id) { showError("No generation ID provided."); return; }

    // Back button: return to the specific group if we came from one
    if (groupId) {
        document.getElementById("backBtn").href           = `/content-groups?group=${encodeURIComponent(groupId)}`;
        document.getElementById("backLabel").textContent  = "Content Group";
    }

    initAuth(async () => { await loadGeneration(id); });
});

async function loadGeneration(id) {
    try {
        const res  = await fetch(`${API_URL}/api/generation.php?id=${encodeURIComponent(id)}`, { headers: authHeaders() });
        const data = await res.json();
        if (!res.ok) { showError(data.detail || "Failed to load content."); return; }
        genData = data;

        const title = toTitleCase(data.keyword);
        document.getElementById("topBarTitle").textContent    = title;
        document.getElementById("topBarSubtitle").textContent = "Generated WordPress HTML";
        document.getElementById("viewKeyword").textContent    = data.keyword.toUpperCase();
        document.getElementById("viewBadge").innerHTML        = statusBadge(data.status);
        document.getElementById("viewDate").textContent       = new Date(data.created_at).toLocaleDateString("en-US", {
            month: "long", day: "numeric", year: "numeric", hour: "2-digit", minute: "2-digit"
        });
        document.getElementById("densityModalTitle").textContent = `Keyword Density — ${title}`;
        // Parse and separate meta section from body content
        const parsed    = parseContentMeta(data.content || '');
        genCleanContent = parsed.content;
        showMetaPanel(parsed.meta);

        document.getElementById("htmlOutput").value = genCleanContent || "(No content stored — generation may not have completed.)";

        if (genCleanContent) {
            const wc   = wordCount(genCleanContent);
            const meta = document.getElementById("viewMeta");
            meta.textContent  = `${wc.toLocaleString()} words · ${readingTime(wc)}`;
            meta.style.display = "";
        }

        document.getElementById("loadingState").style.display = "none";
        document.getElementById("contentArea").style.display  = "";

        loadGroupWpConfig(data.group_id);
        loadVersions(data.id);
    } catch (err) {
        showError(err.message);
    }
}

function showError(msg) {
    document.getElementById("loadingState").style.display = "none";
    document.getElementById("errorState").style.display   = "flex";
    document.getElementById("errorText").textContent      = msg;
}

// ── View modes ────────────────────────────────────────────────────────────────

function setViewMode(mode) {
    viewMode = mode;
    document.getElementById("htmlOutput").style.display  = mode === 'html'  ? "" : "none";
    document.getElementById("cleanOutput").style.display = mode === 'clean' ? "" : "none";
    document.getElementById("editOutput").style.display  = mode === 'edit'  ? "" : "none";
    document.getElementById("saveRow").style.display     = mode === 'edit'  ? "flex" : "none";

    document.getElementById("btnHtml").classList.toggle("btn-view-active",  mode === 'html');
    document.getElementById("btnClean").classList.toggle("btn-view-active", mode === 'clean');
    document.getElementById("btnEdit").classList.toggle("btn-view-active",  mode === 'edit');

    document.getElementById("copyHtmlRow").style.display  = mode === 'html'  ? "" : "none";
    document.getElementById("copyCleanRow").style.display = mode === 'clean' ? "" : "none";

    if (mode === 'clean') document.getElementById("cleanOutput").innerHTML = genCleanContent || '';
    if (mode === 'edit')  document.getElementById("editOutput").value      = genData?.content || '';
}

async function saveContent() {
    const id      = new URLSearchParams(window.location.search).get("id");
    const content = document.getElementById("editOutput").value;
    const saveBtn = document.getElementById("saveBtn");
    saveBtn.disabled = true;
    try {
        const res = await fetch(`${API_URL}/api/generation.php?id=${encodeURIComponent(id)}`, {
            method: 'PATCH', headers: authHeaders(), body: JSON.stringify({ content })
        });
        if (!res.ok) throw new Error('Failed to save.');
        genData.content = content;
        const reparsed  = parseContentMeta(content);
        genCleanContent = reparsed.content;
        showMetaPanel(reparsed.meta);
        document.getElementById("htmlOutput").value = genCleanContent;
        setViewMode('html');
        const ind = document.getElementById("saveIndicator");
        ind.classList.add("visible");
        setTimeout(() => ind.classList.remove("visible"), 2500);
    } catch (err) {
        alert(err.message);
    } finally {
        saveBtn.disabled = false;
    }
}

function regenerate() {
    if (!genData?.id) return;
    window.location.href = '/new-content?resume=' + encodeURIComponent(genData.id);
}

function copyHtml() {
    navigator.clipboard.writeText(genCleanContent || '').then(() => flashCopyIcon('copyHtmlIcon'));
}

function copyClean() {
    const tmp = document.createElement('div');
    tmp.innerHTML = genCleanContent || '';
    navigator.clipboard.writeText(tmp.textContent || tmp.innerText || '').then(() => flashCopyIcon('copyCleanIcon'));
}

function flashCopyIcon(btnId) {
    const btn  = document.getElementById(btnId);
    const orig = btn.innerHTML;
    btn.innerHTML = '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
    setTimeout(() => { btn.innerHTML = orig; }, 1800);
}

// ── Keyword Density ───────────────────────────────────────────────────────────

function openDensityModal() {
    document.getElementById("densityContent").innerHTML = buildDensityHTML(genData?.content || '');
    document.getElementById("densityModal").classList.add("open");
    document.getElementById("densityOverlay").classList.add("visible");
}

function closeDensityModal() {
    document.getElementById("densityModal").classList.remove("open");
    document.getElementById("densityOverlay").classList.remove("visible");
}

async function duplicateContent() {
    const id      = new URLSearchParams(window.location.search).get('id');
    const groupId = new URLSearchParams(window.location.search).get('group');
    const btn     = event.target.closest('.btn');
    const orig    = btn.innerHTML;
    btn.disabled  = true;
    btn.textContent = 'Duplicating...';
    try {
        const res  = await fetch(API_URL + '/api/generation.php?id=' + encodeURIComponent(id), {
            method: 'POST', headers: authHeaders()
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.detail || 'Failed to duplicate.');
        const dest = '/view-content?id=' + encodeURIComponent(data.id) + (groupId ? '&group=' + encodeURIComponent(groupId) : '');
        window.location.href = dest;
    } catch (err) {
        alert(err.message);
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

async function deleteContent() {
    if (!confirm("Delete this content? This cannot be undone.")) return;
    const id      = new URLSearchParams(window.location.search).get("id");
    const groupId = new URLSearchParams(window.location.search).get("group");
    const btn     = document.getElementById("btnDelete");
    btn.disabled  = true;
    try {
        const res = await fetch(`${API_URL}/api/generation.php?id=${encodeURIComponent(id)}`, {
            method: 'DELETE', headers: authHeaders()
        });
        if (!res.ok) throw new Error('Failed to delete.');
        window.location.href = groupId ? `/content-groups?group=${encodeURIComponent(groupId)}` : '/content-groups';
    } catch (err) {
        alert(err.message);
        btn.disabled = false;
    }
}

function buildDensityHTML(html) {
    const tmp   = document.createElement('div');
    tmp.innerHTML = html;
    const text  = (tmp.textContent || tmp.innerText || '').toLowerCase();
    const words = text.match(/[a-z']+/g)?.map(w => w.replace(/^'+|'+$/g, '')).filter(w => w.length > 1) || [];
    const total = words.length;

    if (!total) return `<p style="color:var(--text-muted);font-size:13px;">No text content found.</p>`;

    // Articles, conjunctions, prepositions, pronouns, aux verbs, adverbs, determiners
    const stopWords = new Set([
        // Articles
        'a','an','the',
        // Conjunctions
        'and','or','but','nor','so','yet','for','either','neither','both','whether',
        'although','because','since','unless','until','while','whereas','if','though',
        'even','than','as','that','which','who','whom','whose','what','when','where','how',
        // Prepositions
        'in','on','at','to','of','with','by','from','into','through','over','under',
        'above','below','between','among','around','about','against','along','across',
        'behind','before','after','during','within','without','throughout','upon','per',
        'near','off','out','up','down','via','beyond','beside','besides','despite',
        'except','inside','outside','towards','onto','regarding','concerning',
        // Pronouns
        'i','me','my','myself','we','us','our','ours','ourselves',
        'you','your','yours','yourself','yourselves',
        'he','him','his','himself','she','her','hers','herself',
        'it','its','itself','they','them','their','theirs','themselves',
        'this','that','these','those','who','whom','whose','which','what',
        // Auxiliary & common verbs
        'is','am','are','was','were','be','been','being',
        'have','has','had','having','do','does','did','doing',
        'will','would','shall','should','may','might','must','can','could',
        'get','got','make','made','use','used','let','go','come','take','give',
        // Common adverbs / determiners
        'not','no','nor','never','always','often','also','just','only','even',
        'very','too','so','more','most','less','least','quite','rather','still',
        'already','again','once','twice','then','now','here','there','where',
        'when','why','how','all','each','both','few','some','any','such',
        'same','other','another','many','much','more','several','own','every',
        'enough','up','out','about','than','well','back','away',
    ]);

    function ngrams(n) {
        const map = {};
        for (let i = 0; i <= words.length - n; i++) {
            const key = words.slice(i, i + n).join(' ');
            map[key] = (map[key] || 0) + 1;
        }
        return Object.entries(map).sort((a, b) => b[1] - a[1]);
    }

    const uni = ngrams(1).filter(([w]) => !stopWords.has(w)).slice(0, 20);
    const bi  = ngrams(2).filter(([w]) => !w.split(' ').some(t => stopWords.has(t))).slice(0, 20);
    const tri = ngrams(3).filter(([w]) => !w.split(' ').some(t => stopWords.has(t))).slice(0, 20);

    function table(rows) {
        if (!rows.length) return `<p style="color:var(--text-muted);font-size:12px;padding:4px 0;">Not enough data.</p>`;
        const maxCount = rows[0][1];
        return `<table class="density-table">
            <thead><tr><th>Keyword</th><th>Count</th><th>Density</th><th style="width:120px;"></th></tr></thead>
            <tbody>${rows.map(([kw, cnt]) => {
                const pct  = ((cnt / total) * 100).toFixed(2);
                const fill = Math.round((cnt / maxCount) * 100);
                return `<tr>
                    <td style="font-weight:500;">${escapeHtml(kw)}</td>
                    <td>${cnt}</td>
                    <td style="color:var(--text-muted);">${pct}%</td>
                    <td><div class="density-bar-wrap"><div class="density-bar-fill" style="width:${fill}px;"></div></div></td>
                </tr>`;
            }).join('')}</tbody>
        </table>`;
    }

    return `<p style="font-size:12px;color:var(--text-muted);margin-bottom:16px;">Total words: <strong>${total}</strong></p>
        <div class="density-section-title">Single-Word Keywords</div>${table(uni)}
        <div class="density-section-title">Two-Word Keywords</div>${table(bi)}
        <div class="density-section-title">Three-Word Keywords</div>${table(tri)}`;
}

// ── Version History ───────────────────────────────────────────────────────────

async function loadVersions(generationId) {
    try {
        const res  = await fetch(API_URL + '/api/versions.php?generation_id=' + encodeURIComponent(generationId), { headers: authHeaders() });
        const data = await res.json();
        versionsData = data.versions || [];
        if (versionsData.length) {
            document.getElementById("versionsCount").textContent = '(' + versionsData.length + ')';
            document.getElementById("btnVersions").style.display = "";
        }
    } catch (_) {}
}

function openVersionsModal() {
    const el = document.getElementById("versionsContent");
    el.innerHTML = versionsData.map((v, i) => {
        const num  = versionsData.length - i;
        const date = new Date(v.created_at).toLocaleDateString("en-US", {
            month: "short", day: "numeric", year: "numeric", hour: "2-digit", minute: "2-digit"
        });
        return `<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid var(--light-gray);">
            <div>
                <div style="font-size:13px;font-weight:600;color:var(--dark);font-family:'Inter',sans-serif;">Version ${num}</div>
                <div style="font-size:12px;color:var(--text-muted);font-family:'Inter',sans-serif;margin-top:2px;">${date}</div>
            </div>
            <button class="btn btn-secondary" style="padding:5px 12px;font-size:12px;flex-shrink:0;" onclick="restoreVersion(${i})">Restore</button>
        </div>`;
    }).join('');
    document.getElementById("versionsModal").classList.add("open");
    document.getElementById("versionsOverlay").classList.add("visible");
}

function closeVersionsModal() {
    document.getElementById("versionsModal").classList.remove("open");
    document.getElementById("versionsOverlay").classList.remove("visible");
}

async function restoreVersion(index) {
    if (!confirm('Restore Version ' + (versionsData.length - index) + '? The current content will be overwritten.')) return;
    const id      = new URLSearchParams(window.location.search).get("id");
    const content = versionsData[index].content;
    try {
        const res = await fetch(API_URL + '/api/generation.php?id=' + encodeURIComponent(id), {
            method: 'PATCH', headers: authHeaders(), body: JSON.stringify({ content })
        });
        if (!res.ok) throw new Error('Restore failed.');
        genData.content = content;
        const reparsed  = parseContentMeta(content);
        genCleanContent = reparsed.content;
        showMetaPanel(reparsed.meta);
        document.getElementById("htmlOutput").value = genCleanContent;
        if (viewMode === 'clean') document.getElementById("cleanOutput").innerHTML = genCleanContent;
        closeVersionsModal();
    } catch (err) {
        alert(err.message);
    }
}

// ── WordPress Publish ─────────────────────────────────────────────────────────

let groupWpConfig = null;  // { wp_configured, wp_site_url }

async function loadGroupWpConfig(groupId) {
    if (!groupId) return;
    try {
        const res  = await fetch(API_URL + '/api/groups.php?id=' + encodeURIComponent(groupId), { headers: authHeaders() });
        const data = await res.json();
        if (!res.ok || !data.wp_configured) return;
        groupWpConfig = data;
        const btn = document.getElementById("btnPublish");
        if (btn) btn.style.display = "";
    } catch (_) {}
}

function openPublishModal() {
    const siteLabel = document.getElementById("publishSiteLabel");
    siteLabel.textContent = groupWpConfig?.wp_site_url
        ? 'Publishing to: ' + groupWpConfig.wp_site_url
        : '';

    const postUrlEl   = document.getElementById("publishPostUrl");
    const postLinkEl  = document.getElementById("publishPostLink");
    if (genData?.wp_post_url) {
        postLinkEl.href        = genData.wp_post_url;
        postLinkEl.textContent = genData.wp_post_url;
        postUrlEl.style.display = "";
    } else {
        postUrlEl.style.display = "none";
    }

    document.getElementById("publishResult").style.display = "none";
    document.getElementById("publishModal").classList.add("open");
    document.getElementById("publishOverlay").classList.add("visible");
}

function closePublishModal() {
    document.getElementById("publishModal").classList.remove("open");
    document.getElementById("publishOverlay").classList.remove("visible");
}

async function publishContent(postStatus) {
    const id       = new URLSearchParams(window.location.search).get("id");
    const draftBtn = document.getElementById("btnPublishDraft");
    const liveBtn  = document.getElementById("btnPublishLive");
    const resultEl = document.getElementById("publishResult");

    draftBtn.disabled = true;
    liveBtn.disabled  = true;
    resultEl.style.display = "none";

    try {
        const res  = await fetch(API_URL + '/api/publish.php', {
            method: 'POST',
            headers: authHeaders(),
            body: JSON.stringify({ generation_id: id, post_status: postStatus })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.detail || 'Publish failed.');

        genData.wp_post_url = data.post_url;

        const label = postStatus === 'publish' ? 'Published live' : 'Saved as draft';
        resultEl.innerHTML = '<div style="font-size:13px;color:var(--dark);font-family:\'Inter\',sans-serif;">'
            + label + ': <a href="' + escapeHtml(data.post_url) + '" target="_blank" rel="noopener" style="color:var(--blue);word-break:break-all;">'
            + escapeHtml(data.post_url) + '</a></div>';
        resultEl.style.display = "";

        const postUrlEl  = document.getElementById("publishPostUrl");
        const postLinkEl = document.getElementById("publishPostLink");
        postLinkEl.href        = data.post_url;
        postLinkEl.textContent = data.post_url;
        postUrlEl.style.display = "";
    } catch (err) {
        resultEl.innerHTML = '<div style="font-size:13px;color:var(--red);font-family:\'Inter\',sans-serif;">' + escapeHtml(err.message) + '</div>';
        resultEl.style.display = "";
    } finally {
        draftBtn.disabled = false;
        liveBtn.disabled  = false;
    }
}
