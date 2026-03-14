let genData  = null;
let viewMode = 'html';

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
        document.getElementById("htmlOutput").value = data.content || "(No content stored — generation may not have completed.)";

        if (data.content) {
            const wc   = wordCount(data.content);
            const meta = document.getElementById("viewMeta");
            meta.textContent  = `${wc.toLocaleString()} words · ${readingTime(wc)}`;
            meta.style.display = "";
        }

        document.getElementById("loadingState").style.display = "none";
        document.getElementById("contentArea").style.display  = "";
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

    if (mode === 'clean') document.getElementById("cleanOutput").innerHTML = genData?.content || '';
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
        document.getElementById("htmlOutput").value = content;
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
    const params  = new URLSearchParams();
    const groupId = new URLSearchParams(window.location.search).get("group") || genData?.group_id || '';
    if (genData?.keyword) params.set('keyword', genData.keyword);
    if (groupId)          params.set('group', groupId);
    window.location.href = '/new-content' + (params.toString() ? '?' + params.toString() : '');
}

function copyContent() {
    navigator.clipboard.writeText(genData?.content || '').then(() => {
        const btn  = document.getElementById("btnCopy");
        const orig = btn.innerHTML;
        btn.innerHTML = `<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"><polyline points="20 6 9 17 4 12"/></svg> Copied!`;
        setTimeout(() => { btn.innerHTML = orig; }, 2000);
    });
}

// ── Keyword Density ───────────────────────────────────────────────────────────

function openDensityModal() {
    document.getElementById("densityContent").innerHTML = buildDensityHTML(genData?.content || '');
    document.getElementById("densityModal").classList.add("open");
    document.getElementById("densityOverlay").classList.add("visible");
    const btn = document.getElementById("btnDensity");
    btn.classList.remove("btn-secondary");
    btn.classList.add("btn-yellow");
}

function closeDensityModal() {
    document.getElementById("densityModal").classList.remove("open");
    document.getElementById("densityOverlay").classList.remove("visible");
    const btn = document.getElementById("btnDensity");
    btn.classList.remove("btn-yellow");
    btn.classList.add("btn-secondary");
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
