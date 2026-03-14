let currentGenerationId = null;
let currentGroupId      = null;
let rawSerpapiText      = "";

document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("navGenerate").classList.add("active");
    initAuth(async () => {
        await loadGroups();
        populateGroupSelector();
        const params = new URLSearchParams(window.location.search);
        const kw = params.get('keyword');
        const gp = params.get('group');
        if (kw) document.getElementById("keywordInput").value = kw;
        if (gp) document.getElementById("groupSelect").value  = gp;
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
