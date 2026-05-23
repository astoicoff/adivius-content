let genData         = null;
let genCleanContent = null;   // raw content with meta lines stripped
let viewMode        = 'html';
let versionsData    = [];
let lastSeoData     = null;
let autoSaveTimer   = null;

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

        loadGroupConfig(data.group_id);
        loadVersions(data.id);

        // Show Send to Nucleus button when piece is complete and not yet handed off
        if (data.status === 'completed' && !data.handed_off_at) {
            document.getElementById("btnNucleus").style.display = "";
        }
        renderWebhookStatus();
    } catch (err) {
        showError(err.message);
    }
}

function showError(msg) {
    document.getElementById("loadingState").style.display = "none";
    document.getElementById("errorState").style.display   = "flex";
    document.getElementById("errorText").textContent      = msg;
}

// ── Table of Contents ─────────────────────────────────────────────────────────

function renderWithTOC(html) {
    if (!html) return '';
    const div = document.createElement('div');
    div.innerHTML = html;
    const headings = [...div.querySelectorAll('h1,h2,h3')];
    if (headings.length < 2) return html;

    headings.forEach((h, i) => { h.id = 'toc-' + i; });

    const items = headings.map((h, i) => {
        const level = h.tagName.toLowerCase();
        return `<li class="toc-item toc-${level}"><a href="#toc-${i}">${escapeHtml(h.textContent.trim())}</a></li>`;
    }).join('');

    const toc = `<nav class="toc-nav"><div class="toc-title">Table of Contents</div><ul class="toc-list">${items}</ul></nav>`;
    return toc + div.innerHTML;
}

// ── View modes ────────────────────────────────────────────────────────────────

function setViewMode(mode) {
    if (viewMode === 'edit' && mode !== 'edit') stopAutoSave();
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

    if (mode === 'clean') document.getElementById("cleanOutput").innerHTML = renderWithTOC(genCleanContent || '');
    if (mode === 'edit')  { document.getElementById("editOutput").value = genData?.content || ''; startAutoSave(); }
}

function startAutoSave() {
    stopAutoSave();
    document.getElementById("editOutput").addEventListener('input', onEditInput);
}

function stopAutoSave() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = null;
    const el = document.getElementById("editOutput");
    if (el) el.removeEventListener('input', onEditInput);
}

function onEditInput() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(performAutoSave, 30000);
}

async function performAutoSave() {
    autoSaveTimer = null;
    const id      = new URLSearchParams(window.location.search).get("id");
    const content = document.getElementById("editOutput").value;
    try {
        const res = await fetch(`${API_URL}/api/generation.php?id=${encodeURIComponent(id)}`, {
            method: 'PATCH', headers: authHeaders(), body: JSON.stringify({ content })
        });
        if (!res.ok) return;
        genData.content = content;
        const reparsed  = parseContentMeta(content);
        genCleanContent = reparsed.content;
        showMetaPanel(reparsed.meta);
        document.getElementById("htmlOutput").value = genCleanContent;
        const ind = document.getElementById("saveIndicator");
        ind.classList.add("visible");
        setTimeout(() => ind.classList.remove("visible"), 2500);
    } catch (_) {}
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
        showToast(err.message);
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

// ── Export ────────────────────────────────────────────────────────────────────

function slugifyKeyword(kw) {
    return (kw || 'content').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

function exportDocx() {
    if (!genCleanContent) { showToast('No content to export.', 'warning'); return; }
    const btn  = document.getElementById('btnExportDocx');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.textContent = 'Exporting…';
    try {
        const html  = `<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>${genCleanContent}</body></html>`;
        const blob  = htmlDocx.asBlob(html);
        const url   = URL.createObjectURL(blob);
        const a     = document.createElement('a');
        a.href      = url;
        a.download  = slugifyKeyword(genData?.keyword) + '.docx';
        a.click();
        URL.revokeObjectURL(url);
    } catch (err) {
        showToast('DOCX export failed: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

function exportPdf() {
    if (!genCleanContent) { showToast('No content to export.', 'warning'); return; }
    const btn  = document.getElementById('btnExportPdf');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.textContent = 'Exporting…';
    const container = document.createElement('div');
    container.style.cssText = 'font-family:Georgia,serif;font-size:14px;line-height:1.7;color:#111;padding:0;';
    container.innerHTML = genCleanContent;
    html2pdf().set({
        margin:      [15, 15, 15, 15],
        filename:    slugifyKeyword(genData?.keyword) + '.pdf',
        image:       { type: 'jpeg', quality: 0.95 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait' },
    }).from(container).save().then(() => {
        btn.disabled = false;
        btn.innerHTML = orig;
    }).catch(err => {
        showToast('PDF export failed: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = orig;
    });
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
        showToast(err.message);
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
        showToast(err.message);
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

// ── SEO Score ─────────────────────────────────────────────────────────────────

function openSeoModal() {
    document.getElementById("seoModal").classList.add("open");
    document.getElementById("seoOverlay").classList.add("visible");
    loadSeoScore();
}

function closeSeoModal() {
    document.getElementById("seoModal").classList.remove("open");
    document.getElementById("seoOverlay").classList.remove("visible");
}

async function loadSeoScore() {
    const id   = new URLSearchParams(window.location.search).get("id");
    const body = document.getElementById("seoModalBody");
    body.innerHTML = '<div class="loading-bar visible" style="justify-content:center;"><div class="spinner"></div> Analysing…</div>';

    try {
        const res  = await fetch(`${API_URL}/api/neuronwriter.php?action=score&id=${encodeURIComponent(id)}`, {
            method: 'POST', headers: authHeaders()
        });
        const data = await res.json();

        if (!res.ok) { body.innerHTML = `<p style="color:var(--red);font-size:13px;">${escapeHtml(data.detail || 'Error')}</p>`; return; }

        // No project set — show project picker
        if (data.needs_project) {
            const opts = (data.projects || []).map(p =>
                `<li><button class="btn btn-secondary" style="width:100%;text-align:left;" onclick="selectSeoProject('${escapeHtml(p.id)}')">${escapeHtml(p.name)}</button></li>`
            ).join('');
            body.innerHTML = `<p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;text-align:left;">Select your NeuronWriter project:</p>
                <ul class="seo-project-list">${opts || '<li style="font-size:13px;color:var(--text-muted);">No projects found.</li>'}</ul>`;
            return;
        }

        // Query still processing
        if (data.pending) {
            body.innerHTML = `<p style="font-size:13px;color:var(--text-muted);">NeuronWriter is still building the analysis. Try again in ~30 seconds.</p>`;
            return;
        }

        // Score result
        lastSeoData = data;
        body.innerHTML = buildSeoReportHTML(data);
    } catch (err) {
        body.innerHTML = `<p style="color:var(--red);font-size:13px;">${escapeHtml(err.message)}</p>`;
    }
}

function buildSeoReportHTML(data) {
    const score     = data.score ?? 0;
    const scoreColor = score >= 70 ? 'var(--green)' : score >= 40 ? '#e6a817' : 'var(--red)';
    const currentWC  = wordCount(genCleanContent || '');
    const targetWC   = data.word_count_target;

    // Score + word count row
    const scoreBlock = `
        <div style="display:flex;align-items:flex-end;justify-content:center;gap:32px;padding:20px 0 16px;border-bottom:1px solid var(--light-gray);">
            <div style="text-align:center;">
                <div class="seo-score-number" style="color:${scoreColor};">${score}</div>
                <div class="seo-score-label">SEO score / 100</div>
            </div>
            ${targetWC ? `<div style="text-align:center;">
                <div class="seo-score-number" style="font-size:48px;color:${currentWC >= targetWC ? 'var(--green)' : currentWC >= targetWC * 0.8 ? '#e6a817' : 'var(--red)'};">${currentWC.toLocaleString()}</div>
                <div class="seo-score-label">words · target ${targetWC.toLocaleString()}</div>
            </div>` : ''}
            ${data.intent ? `<div style="text-align:center;">
                <div style="font-size:13px;font-weight:700;text-transform:capitalize;background:var(--off-white);border:1px solid var(--light-gray);border-radius:20px;padding:6px 14px;font-family:'Inter',sans-serif;">${escapeHtml(data.intent)}</div>
                <div class="seo-score-label" style="margin-top:6px;">search intent</div>
            </div>` : ''}
        </div>`;

    // Top terms
    const terms = (data.top_terms || []).slice(0, 12);
    const termsBlock = terms.length ? `
        <div class="seo-section">
            <div class="seo-section-title">Top Terms to Include</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                ${terms.map(t => {
                    const phrase = t.t || '';
                    const raw    = t.sugg_usage;
                    const usage  = raw == null ? '' : (typeof raw === 'object' ? (raw.min ?? '') + (raw.max != null ? '–' + raw.max : '') : String(raw));
                    return `<span style="font-size:12px;font-family:'Inter',sans-serif;background:var(--off-white);border:1px solid var(--light-gray);border-radius:6px;padding:4px 8px;">
                        ${escapeHtml(phrase)}${usage ? `<span style="color:var(--text-muted);margin-left:4px;">${escapeHtml(usage)}×</span>` : ''}
                    </span>`;
                }).join('')}
            </div>
        </div>` : '';

    // Questions
    const questions = (data.questions || []).slice(0, 6);
    const qBlock = questions.length ? `
        <div class="seo-section">
            <div class="seo-section-title">Questions to Address</div>
            <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;">
                ${questions.map(q => { const txt = typeof q === 'string' ? q : (q.q || q.question || ''); return txt ? `<li style="font-size:13px;font-family:'Inter',sans-serif;color:var(--dark);padding:6px 10px;background:var(--off-white);border-radius:6px;border-left:3px solid var(--blue);">${escapeHtml(txt)}</li>` : ''; }).join('')}
            </ul>
        </div>` : '';

    // Competitors
    const comps = (data.competitors || []).slice(0, 5);
    const compBlock = comps.length ? `
        <div class="seo-section">
            <div class="seo-section-title">Top Competitors</div>
            <table style="width:100%;border-collapse:collapse;font-size:12px;font-family:'Inter',sans-serif;">
                <thead><tr>
                    <th style="text-align:left;padding:4px 8px;color:var(--text-muted);font-weight:600;border-bottom:1px solid var(--light-gray);">#</th>
                    <th style="text-align:left;padding:4px 8px;color:var(--text-muted);font-weight:600;border-bottom:1px solid var(--light-gray);">Page</th>
                    <th style="text-align:right;padding:4px 8px;color:var(--text-muted);font-weight:600;border-bottom:1px solid var(--light-gray);">Score</th>
                </tr></thead>
                <tbody>${comps.map((c, i) => {
                    const domain = (() => { try { return new URL(c.url).hostname.replace('www.',''); } catch(_) { return c.url; } })();
                    const cs = c.content_score ?? '—';
                    const csColor = cs >= 70 ? 'var(--green)' : cs >= 40 ? '#e6a817' : 'var(--red)';
                    return `<tr>
                        <td style="padding:6px 8px;color:var(--text-muted);">${i+1}</td>
                        <td style="padding:6px 8px;"><a href="${escapeHtml(c.url)}" target="_blank" rel="noopener" style="color:var(--blue);text-decoration:none;">${escapeHtml(domain)}</a></td>
                        <td style="padding:6px 8px;text-align:right;font-weight:600;color:${csColor};">${cs}</td>
                    </tr>`;
                }).join('')}</tbody>
            </table>
        </div>` : '';

    const newNote     = data.is_new ? `<p style="font-size:11px;color:var(--text-muted);text-align:center;margin-top:4px;">Analysis created — future scores are instant.</p>` : '';
    const checklistBlock = buildSeoChecklist(data);
    const link = data.query_url
        ? `<div style="text-align:center;padding:16px 0 4px;">
            <a href="${escapeHtml(data.query_url)}" target="_blank" rel="noopener" class="btn btn-secondary" style="display:inline-flex;gap:6px;">
                View Full Analysis in NeuronWriter
                <svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            </a>
          </div>` : '';

    return scoreBlock + newNote + checklistBlock + termsBlock + qBlock + compBlock + link;
}

async function selectSeoProject(projectId) {
    const body = document.getElementById("seoModalBody");
    body.innerHTML = '<div class="loading-bar visible" style="justify-content:center;"><div class="spinner"></div> Saving…</div>';
    await fetch(`${API_URL}/api/neuronwriter.php?action=set_project`, {
        method: 'POST', headers: authHeaders(), body: JSON.stringify({ project_id: projectId })
    });
    loadSeoScore();
}

function buildSeoChecklist(data) {
    const genId   = new URLSearchParams(window.location.search).get('id') || '';
    const content = (genCleanContent || '').toLowerCase();
    const items   = [];

    // Terms below their suggested minimum — auto-computed from current content
    (data.top_terms || []).forEach(t => {
        const phrase = t.t || '';
        if (!phrase) return;
        const raw    = t.sugg_usage;
        const min    = raw == null ? 0 : (typeof raw === 'object' ? (raw.min ?? 0) : (Number(raw) || 0));
        if (!min) return;
        const pattern = phrase.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const count   = (content.match(new RegExp(pattern, 'gi')) || []).length;
        const needed  = min - count;
        if (needed > 0) items.push({ kind: 'term', phrase, needed, count, min,
            label: `Add "${phrase}" ${needed} more time${needed !== 1 ? 's' : ''} — currently ${count}×, target ${min}×` });
    });

    // Questions — show until AI has fixed them (persisted to localStorage)
    (data.questions || []).forEach(q => {
        const txt = typeof q === 'string' ? q : (q.q || q.question || '');
        if (!txt) return;
        const fixedKey = `seo_fixed_${genId}_${txt.slice(0, 60)}`;
        if (localStorage.getItem(fixedKey) === '1') return; // AI already handled it
        items.push({ kind: 'question', txt, fixedKey, label: `Answer: "${txt}"` });
    });

    if (!items.length) return `
        <div class="seo-section" style="display:flex;align-items:center;gap:8px;padding-bottom:4px;">
            <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:var(--green);fill:none;stroke-width:2.5;flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
            <span style="font-size:13px;font-family:'Inter',sans-serif;color:var(--green);font-weight:600;">All checklist items complete!</span>
        </div>`;

    const rows = items.map((item, idx) => {
        const btnId   = `seoFixBtn_${idx}`;
        const onClick = item.kind === 'term'
            ? `applySeoFix('term','${item.phrase.replace(/'/g,"\\'").replace(/"/g,'&quot;')}',${item.needed},'${btnId}')`
            : `applySeoFix('question','${item.txt.replace(/'/g,"\\'").replace(/"/g,'&quot;')}',1,'${btnId}')`;
        return `<li style="display:flex;align-items:flex-start;gap:8px;padding:6px 0;border-bottom:1px solid var(--light-gray);">
            <span style="flex:1;font-size:13px;font-family:'Inter',sans-serif;line-height:1.45;color:var(--dark);padding-top:2px;">${escapeHtml(item.label)}</span>
            <button id="${btnId}" class="btn btn-secondary" style="flex-shrink:0;padding:3px 10px;font-size:12px;" onclick="${onClick}">Fix</button>
        </li>`;
    }).join('');

    return `
        <div class="seo-section">
            <div class="seo-section-title">What to Fix</div>
            <ul style="margin:0;padding:0;list-style:none;">${rows}</ul>
        </div>`;
}

async function applySeoFix(type, payload, needed, btnId) {
    const genId = new URLSearchParams(window.location.search).get('id');
    const btn   = document.getElementById(btnId);
    if (btn) { btn.disabled = true; btn.innerHTML = '<div class="spinner" style="width:11px;height:11px;border-width:2px;display:inline-block;"></div>'; }

    try {
        const res  = await fetch(`${API_URL}/api/seo-apply.php`, {
            method: 'POST', headers: authHeaders(),
            body: JSON.stringify({ generation_id: genId, type, payload, needed })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.detail || 'Fix failed');

        // Update state from returned full content
        genData.content = data.content;
        const reparsed  = parseContentMeta(data.content);
        genCleanContent = reparsed.content;
        showMetaPanel(reparsed.meta);
        document.getElementById('htmlOutput').value = genCleanContent;
        if (viewMode === 'clean') document.getElementById('cleanOutput').innerHTML = renderWithTOC(genCleanContent);
        if (viewMode === 'edit')  document.getElementById('editOutput').value      = data.content;

        // Mark question as AI-fixed so it leaves the checklist
        if (type === 'question') localStorage.setItem(`seo_fixed_${genId}_${payload.slice(0, 60)}`, '1');

        // Re-render checklist with updated content
        if (lastSeoData) document.getElementById('seoModalBody').innerHTML = buildSeoReportHTML(lastSeoData);
    } catch (err) {
        if (btn) { btn.disabled = false; btn.textContent = 'Fix'; }
        showToast('SEO fix failed: ' + err.message);
    }
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
    renderVersionsList();
    document.getElementById("versionsModal").classList.add("open");
    document.getElementById("versionsOverlay").classList.add("visible");
}

function closeVersionsModal() {
    document.getElementById("versionsModal").classList.remove("open");
    document.getElementById("versionsOverlay").classList.remove("visible");
}

function renderVersionsList() {
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
            <button class="btn btn-secondary" style="padding:5px 12px;font-size:12px;flex-shrink:0;" onclick="viewVersion(${i})">View</button>
        </div>`;
    }).join('');
}

function viewVersion(index) {
    const num    = versionsData.length - index;
    const date   = new Date(versionsData[index].created_at).toLocaleDateString("en-US", {
        month: "short", day: "numeric", year: "numeric", hour: "2-digit", minute: "2-digit"
    });
    const parsed  = parseContentMeta(versionsData[index].content);
    const preview = parsed.content || versionsData[index].content || '';

    const el = document.getElementById("versionsContent");
    el.innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:16px;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
            <div>
                <div style="font-size:14px;font-weight:700;color:var(--dark);font-family:'Inter',sans-serif;">Version ${num}</div>
                <div style="font-size:12px;color:var(--text-muted);font-family:'Inter',sans-serif;margin-top:2px;">${date}</div>
            </div>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-secondary" style="padding:5px 12px;font-size:12px;" onclick="renderVersionsList()">← Back</button>
                <button class="btn btn-green" style="padding:5px 12px;font-size:12px;" onclick="restoreVersion(${index})">Restore</button>
            </div>
        </div>
        <div class="content-rendered">${renderWithTOC(preview)}</div>`;
}

async function restoreVersion(index) {
    if (!confirm('Restore Version ' + (versionsData.length - index) + '? The current content will be saved as a new version first.')) return;
    const id      = new URLSearchParams(window.location.search).get("id");
    const content = versionsData[index].content;
    try {
        // Save current content as a version before overwriting
        if (genData?.content) {
            await fetch(API_URL + '/api/versions.php?generation_id=' + encodeURIComponent(id), {
                method: 'POST', headers: authHeaders(), body: JSON.stringify({ content: genData.content })
            });
        }
        const res = await fetch(API_URL + '/api/generation.php?id=' + encodeURIComponent(id), {
            method: 'PATCH', headers: authHeaders(), body: JSON.stringify({ content })
        });
        if (!res.ok) throw new Error('Restore failed.');
        genData.content = content;
        const reparsed  = parseContentMeta(content);
        genCleanContent = reparsed.content;
        showMetaPanel(reparsed.meta);
        document.getElementById("htmlOutput").value = genCleanContent;
        if (viewMode === 'clean') document.getElementById("cleanOutput").innerHTML = renderWithTOC(genCleanContent);
        closeVersionsModal();
        await loadVersions(id);
    } catch (err) {
        showToast(err.message);
    }
}

// ── WordPress Publish ─────────────────────────────────────────────────────────

let groupConfig = null;

async function loadGroupConfig(groupId) {
    if (!groupId) return;
    try {
        const res  = await fetch(API_URL + '/api/groups.php?id=' + encodeURIComponent(groupId), { headers: authHeaders() });
        const data = await res.json();
        if (!res.ok) return;

        if (data.name) {
            const groupLink = document.getElementById("topBarGroup");
            document.getElementById("topBarGroupName").textContent = data.name;
            groupLink.href = `/content-groups?group=${encodeURIComponent(groupId)}`;
            groupLink.style.display = "flex";
        }

        groupConfig = data;
        renderWebhookStatus();
    } catch (_) {}
}

// ── Webhook status ────────────────────────────────────────────────────────────

function renderWebhookStatus() {
    const badge = document.getElementById("webhookBadge");
    const alert = document.getElementById("webhookAlert");
    if (!badge || !alert) return;

    const hasGroupWebhook = !!(groupConfig && groupConfig.webhook_url);
    const delivered       = genData?.webhook_delivered_at;
    const error           = genData?.webhook_error;

    badge.style.display = "none";
    alert.style.display = "none";

    // No webhook configured on the group — nothing to show.
    if (!hasGroupWebhook && !delivered && !error) return;

    if (delivered) {
        const when = new Date(delivered).toLocaleString("en-US", { month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" });
        badge.className   = "badge badge-green";
        badge.innerHTML   = `<span class="badge-dot"></span>Webhook · ${escapeHtml(when)}`;
        badge.style.display = "";
    } else if (error) {
        document.getElementById("webhookAlertError").textContent = error;
        alert.style.display = "flex";
    }
    // No third state — if a webhook is configured but never fired (e.g. legacy
    // generation, duplicated row), we stay silent. The user can regenerate.
}

async function retryWebhook() {
    const btn = document.getElementById("webhookRetryBtn");
    if (!genData?.id) return;
    btn.disabled = true;
    const orig   = btn.innerHTML;
    btn.innerHTML = '<div class="spinner" style="width:13px;height:13px;border-width:2px;"></div> Retrying...';
    try {
        const res  = await fetch(API_URL + '/api/webhook.php', {
            method: 'POST', headers: authHeaders(),
            body: JSON.stringify({ generation_id: genData.id })
        });
        const data = await res.json();
        if (res.ok && data.ok) {
            genData.webhook_delivered_at = data.delivered_at;
            genData.webhook_error        = null;
        } else {
            genData.webhook_delivered_at = null;
            genData.webhook_error        = data.error || data.detail || 'Retry failed.';
        }
        renderWebhookStatus();
    } catch (err) {
        genData.webhook_error = err.message;
        renderWebhookStatus();
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

async function sendToNucleus() {
    const id  = new URLSearchParams(window.location.search).get("id");
    const btn = document.getElementById("btnNucleus");
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.textContent = 'Sending…';
    try {
        const res  = await fetch(`${API_URL}/api/nucleus/handoff.php`, {
            method: 'POST', headers: authHeaders(),
            body: JSON.stringify({ generation_id: id })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.detail || 'Handoff failed.');
        genData.handed_off_at = new Date().toISOString();
        btn.style.display = "none";
        showToast('Sent to Nucleus — queued for publishing.', 'success');
    } catch (err) {
        showToast(err.message);
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}
