let currentGenerationId = null;
let currentGroupId      = null;
let rawSerpapiText      = "";

// ── Mode toggle ────────────────────────────────────────────────────────────────

function setMode(mode) {
    document.getElementById("singleMode").style.display = mode === 'single' ? "" : "none";
    document.getElementById("bulkMode").style.display   = mode === 'bulk'   ? "" : "none";
    document.getElementById("modeSingleBtn").classList.toggle("btn-view-active", mode === 'single');
    document.getElementById("modeBulkBtn").classList.toggle("btn-view-active",   mode === 'bulk');
    // Hide downstream sections when switching mode
    document.getElementById("phase2Section").classList.add("hidden");
    document.getElementById("phase3Section").classList.add("hidden");
}

// ── Bulk keyword generation ────────────────────────────────────────────────────

const BULK_LIMIT = 10;
let bulkQueue = []; // [{ keyword, status: 'pending'|'running'|'done'|'failed', id: null, error: null }]

function parseBulkKeywords() {
    const raw    = document.getElementById("bulkKeywordsInput").value;
    const lines  = [...new Set(raw.split('\n').map(l => l.trim()).filter(l => l.length))];
    const note   = document.getElementById("bulkNote");

    if (!lines.length) return;

    if (lines.length > BULK_LIMIT) {
        note.textContent = `Only the first ${BULK_LIMIT} keywords will be used (${lines.length} entered).`;
        note.style.display = "";
    } else {
        note.style.display = "none";
    }

    bulkQueue = lines.slice(0, BULK_LIMIT).map(kw => ({ keyword: kw, status: 'pending', id: null, error: null }));
    renderChips();
    renderBulkProgress();
    updateBulkBtn();
}

function removeChip(index) {
    bulkQueue.splice(index, 1);
    renderChips();
    renderBulkProgress();
    updateBulkBtn();
}

function renderChips() {
    const container = document.getElementById("bulkChips");
    if (!bulkQueue.length) { container.style.display = "none"; container.innerHTML = ""; return; }
    container.style.display = "";
    container.innerHTML = bulkQueue.map((item, i) =>
        `<div class="kw-chip">
            <span>${escapeHtml(item.keyword)}</span>
            <button class="kw-chip-remove" onclick="removeChip(${i})" aria-label="Remove">×</button>
        </div>`
    ).join("");
}

function updateBulkBtn() {
    const btn   = document.getElementById("bulkGenerateBtn");
    const label = document.getElementById("bulkGenerateLabel");
    const n     = bulkQueue.filter(i => i.status === 'pending').length;
    if (n > 0) {
        label.textContent = `Generate All (${bulkQueue.length})`;
        btn.style.display = "";
    } else {
        btn.style.display = "none";
    }
}

function renderBulkProgress() {
    const container = document.getElementById("bulkProgress");
    if (!bulkQueue.length) { container.style.display = "none"; return; }

    const hasStarted = bulkQueue.some(i => i.status !== 'pending');
    if (!hasStarted) { container.style.display = "none"; return; }

    container.style.display = "";
    container.innerHTML = bulkQueue.map(item => {
        let cls = "";
        let right = "";
        if (item.status === 'running') {
            cls   = "running";
            right = `<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div><span style="font-size:12px;color:var(--blue);font-family:'Inter',sans-serif;">Generating...</span>`;
        } else if (item.status === 'done') {
            cls   = "done";
            const viewHref = item.id ? `/view-content?id=${encodeURIComponent(item.id)}` : '#';
            right = `<span style="font-size:12px;color:#2a7a1a;font-family:'Inter',sans-serif;">Done</span>
                     <a href="${viewHref}" class="btn btn-secondary" style="padding:4px 10px;font-size:12px;">View</a>`;
        } else if (item.status === 'failed') {
            cls   = "failed";
            right = `<span style="font-size:12px;color:var(--red);font-family:'Inter',sans-serif;" title="${escapeHtml(item.error || '')}">Failed</span>`;
        } else {
            right = `<span style="font-size:12px;color:var(--text-muted);font-family:'Inter',sans-serif;">Pending</span>`;
        }
        return `<div class="bulk-item ${cls}">
            <span class="bulk-item-kw">${escapeHtml(toTitleCase(item.keyword))}</span>
            <div class="bulk-item-right">${right}</div>
        </div>`;
    }).join("");
}

async function runBulkGeneration() {
    const groupId = document.getElementById("groupSelect").value;
    if (!groupId)        { showAlert("Please select a content group before generating."); return; }
    if (!bulkQueue.length) { return; }

    const btn = document.getElementById("bulkGenerateBtn");
    btn.disabled = true;

    for (let i = 0; i < bulkQueue.length; i++) {
        if (bulkQueue[i].status !== 'pending') continue;
        bulkQueue[i].status = 'running';
        renderBulkProgress();

        try {
            // Phase 1
            const p1res = await fetch(`${API_URL}/api/phase1.php`, {
                method: 'POST', headers: authHeaders(),
                body: JSON.stringify({ keyword: bulkQueue[i].keyword, group_id: groupId })
            });
            let p1data;
            try { p1data = await p1res.json(); } catch { throw new Error("Server returned unexpected response"); }
            if (!p1res.ok) throw new Error(p1data.detail || "Phase 1 failed");

            // Phase 2 (auto-proceed with generated brief)
            const p2res = await fetch(`${API_URL}/api/phase2.php`, {
                method: 'POST', headers: authHeaders(),
                body: JSON.stringify({
                    keyword:       bulkQueue[i].keyword,
                    edited_brief:  p1data.brief,
                    serpapi_text:  p1data.serpapi_raw,
                    generation_id: p1data.generation_id,
                    group_id:      groupId
                })
            });
            let p2data;
            try { p2data = await p2res.json(); } catch { throw new Error("Server returned unexpected response"); }
            if (!p2res.ok) throw new Error(p2data.detail || "Phase 2 failed");

            bulkQueue[i].status = 'done';
            bulkQueue[i].id     = p1data.generation_id;
        } catch (err) {
            bulkQueue[i].status = 'failed';
            bulkQueue[i].error  = err.message;
        }
        renderBulkProgress();
    }

    btn.disabled = false;
    updateBulkBtn();
}

document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("navGenerate").classList.add("active");
    initAuth(async () => {
        await loadGroups();
        populateGroupSelector();
        const params    = new URLSearchParams(window.location.search);
        const resumeId  = params.get('resume');
        const kw        = params.get('keyword');
        const gp        = params.get('group');
        if (resumeId) {
            await resumeGeneration(resumeId);
        } else {
            if (kw) document.getElementById("keywordInput").value = kw;
            if (gp) document.getElementById("groupSelect").value  = gp;
        }
    });
    document.getElementById("phase1Form").addEventListener("submit", handlePhase1);
    document.getElementById("proceedToPhase2Btn").addEventListener("click", handlePhase2);
});

function populateGroupSelector() {
    const select  = document.getElementById("groupSelect");
    const warning = document.getElementById("noGroupsWarning");

    if (!cachedGroups.length) {
        select.innerHTML      = `<option value="">— No groups yet —</option>`;
        select.style.display  = "none";
        warning.style.display = "flex";
        return;
    }

    warning.style.display = "none";
    select.style.display  = "";
    const current = select.value;
    select.innerHTML = `<option value="">— Select a content group —</option>` +
        cachedGroups.map(g =>
            `<option value="${g.id}"${g.id === current ? " selected" : ""}>${escapeHtml(g.name)}</option>`
        ).join("");
}

function showAlert(msg, type = "error") {
    const el = document.getElementById("globalAlert");
    el.className = `alert alert-${type} visible`;
    document.getElementById("globalAlertText").innerHTML = msg;
    window.scrollTo({ top: 0, behavior: "smooth" });
}
function clearAlert() { document.getElementById("globalAlert").classList.remove("visible"); }

function setStep(step) {
    const dots   = [1,2,3].map(i => document.getElementById(`step${i}dot`));
    const labels = [1,2,3].map(i => document.getElementById(`step${i}label`));
    const lines  = [1,2].map(i   => document.getElementById(`line${i}`));
    dots.forEach((dot, i) => {
        dot.className = "step-dot"; labels[i].className = "step-label";
        if      (i + 1 < step)  { dot.classList.add("done");   dot.innerHTML = "✓"; labels[i].classList.add("done"); }
        else if (i + 1 === step) { dot.classList.add("active"); dot.innerHTML = i+1; labels[i].classList.add("active"); }
        else                     { dot.innerHTML = i + 1; }
    });
    lines.forEach((line, i) => { line.className = "step-line"; if (i + 1 < step) line.classList.add("done"); });
}

async function handlePhase1(e) {
    e.preventDefault();
    const kw      = document.getElementById("keywordInput").value.trim();
    const groupId = document.getElementById("groupSelect").value;
    if (!kw) return;
    if (!groupId) { showAlert("Please select a content group before generating."); return; }

    clearAlert();
    document.getElementById("phase2Section").classList.add("hidden");
    document.getElementById("phase3Section").classList.add("hidden");
    document.getElementById("phase1Loading").classList.add("visible");
    document.getElementById("generateBriefBtn").disabled = true;
    setStep(1);

    try {
        const res = await fetch(`${API_URL}/api/phase1.php`, {
            method:  "POST",
            headers: authHeaders(),
            body:    JSON.stringify({ keyword: kw, group_id: groupId })
        });
        let data;
        try { data = await res.json(); } catch { throw new Error("Server returned an unexpected response."); }
        if (!res.ok) throw new Error(data.detail || "Phase 1 Error");

        document.getElementById("briefEditor").value = data.brief;
        rawSerpapiText      = data.serpapi_raw;
        currentGenerationId = data.generation_id;
        currentGroupId      = groupId;

        document.getElementById("phase1Loading").classList.remove("visible");
        document.getElementById("phase2Section").classList.remove("hidden");
        setStep(2);
    } catch (err) {
        showAlert(`Phase 1 Failed: ${err.message}`);
        document.getElementById("phase1Loading").classList.remove("visible");
        setStep(1);
    } finally {
        document.getElementById("generateBriefBtn").disabled = false;
    }
}

async function saveBrief() {
    if (!currentGenerationId) return;
    const btn  = document.getElementById("saveBriefBtn");
    const instructions = document.getElementById("briefEditor").value.trim();
    btn.disabled = true;
    try {
        const res = await fetch(API_URL + '/api/generation.php?id=' + encodeURIComponent(currentGenerationId), {
            method: 'PATCH', headers: authHeaders(), body: JSON.stringify({ instructions })
        });
        if (!res.ok) { const d = await res.json(); throw new Error(d.detail || 'Save failed.'); }
        const ind = document.getElementById("briefSaveIndicator");
        ind.classList.add("visible");
        setTimeout(() => ind.classList.remove("visible"), 2000);
    } catch (err) {
        showAlert('Could not save brief: ' + err.message);
    } finally {
        btn.disabled = false;
    }
}

async function handlePhase2() {
    const kw    = document.getElementById("keywordInput").value.trim();
    const brief = document.getElementById("briefEditor").value.trim();
    clearAlert();
    document.getElementById("phase3Section").classList.add("hidden");
    document.getElementById("phase2Loading").classList.add("visible");
    document.getElementById("proceedToPhase2Btn").disabled = true;
    setStep(2);

    try {
        const res = await fetch(`${API_URL}/api/phase2.php`, {
            method:  "POST",
            headers: authHeaders(),
            body:    JSON.stringify({
                keyword:       kw,
                edited_brief:  brief,
                serpapi_text:  rawSerpapiText,
                generation_id: currentGenerationId,
                group_id:      currentGroupId
            })
        });
        let data;
        try { data = await res.json(); } catch { throw new Error("Server returned an unexpected response."); }
        if (!res.ok) throw new Error(data.detail || "Phase 2 Error");

        document.getElementById("htmlEditor").value = data.html_content;
        document.getElementById("phase2Loading").classList.remove("visible");
        document.getElementById("phase3Section").classList.remove("hidden");
        setStep(3);
    } catch (err) {
        showAlert(`Phase 2 Failed: ${err.message}`);
        document.getElementById("phase2Loading").classList.remove("visible");
        setStep(2);
    } finally {
        document.getElementById("proceedToPhase2Btn").disabled = false;
    }
}

function copyContent() {
    navigator.clipboard.writeText(document.getElementById("htmlEditor").value).then(() => {
        const btn      = event.target.closest(".btn");
        const original = btn.innerHTML;
        btn.innerHTML  = `<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"><polyline points="20 6 9 17 4 12"/></svg> Copied!`;
        setTimeout(() => { btn.innerHTML = original; }, 2000);
    });
}

function resetToNew() {
    document.getElementById("keywordInput").value = "";
    document.getElementById("briefEditor").value  = "";
    document.getElementById("htmlEditor").value   = "";
    document.getElementById("phase2Section").classList.add("hidden");
    document.getElementById("phase3Section").classList.add("hidden");
    currentGenerationId = null;
    currentGroupId      = null;
    rawSerpapiText      = "";
    clearAlert();
    setStep(1);
}

async function resumeGeneration(id) {
    try {
        const res  = await fetch(API_URL + '/api/generation.php?id=' + encodeURIComponent(id), { headers: authHeaders() });
        const data = await res.json();
        if (!res.ok) { showAlert(data.detail || 'Failed to load generation.'); return; }
        if (data.status !== 'instructions_ready') { showAlert('This generation cannot be resumed (status: ' + data.status + ').'); return; }

        currentGenerationId = data.id;
        currentGroupId      = data.group_id;
        rawSerpapiText      = data.serpapi_raw || '';

        document.getElementById("keywordInput").value = data.keyword || '';
        if (data.group_id) document.getElementById("groupSelect").value = data.group_id;
        document.getElementById("briefEditor").value  = data.instructions || '';

        if (!data.instructions) showAlert('Brief data not found — the server may need to be updated. You can re-generate the brief manually.', 'error');

        document.getElementById("phase2Section").classList.remove("hidden");
        setStep(2);
        document.getElementById("phase2Section").scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (err) {
        showAlert('Failed to resume: ' + err.message);
    }
}
