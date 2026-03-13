document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("navSettings").classList.add("active");
    initAuth(async () => {
        await loadSettingsData();
    });
});

async function loadSettingsData() {
    try {
        const res  = await fetch(`${API_URL}/api/settings.php`, { headers: authHeaders() });
        if (!res.ok) return;
        const data = await res.json();
        if (data.openai_key)     document.getElementById("openaiKeyInput").value     = data.openai_key;
        if (data.perplexity_key) document.getElementById("perplexityKeyInput").value = data.perplexity_key;
        if (data.serpapi_key)    document.getElementById("serpapiKeyInput").value    = data.serpapi_key;
        if (data.claude_key)     document.getElementById("claudeKeyInput").value     = data.claude_key;
        if (data.gemini_key)     document.getElementById("geminiKeyInput").value     = data.gemini_key;
    } catch (_) {}
}

async function saveSettings() {
    const btn       = document.getElementById("saveKeysBtn");
    const indicator = document.getElementById("saveIndicator");
    const alertEl   = document.getElementById("settingsAlert");
    btn.disabled = true; alertEl.className = "alert";

    try {
        const res = await fetch(`${API_URL}/api/settings.php`, {
            method:  "POST",
            headers: authHeaders(),
            body:    JSON.stringify({
                openai_key:     document.getElementById("openaiKeyInput").value.trim(),
                perplexity_key: document.getElementById("perplexityKeyInput").value.trim(),
                serpapi_key:    document.getElementById("serpapiKeyInput").value.trim(),
                claude_key:     document.getElementById("claudeKeyInput").value.trim(),
                gemini_key:     document.getElementById("geminiKeyInput").value.trim(),
            })
        });
        if (!res.ok) {
            let detail = "Failed to save";
            try { const d = await res.json(); detail = d.detail || detail; } catch {}
            throw new Error(detail);
        }
        indicator.classList.add("visible");
        setTimeout(() => indicator.classList.remove("visible"), 3000);
    } catch (err) {
        alertEl.className = "alert alert-error visible";
        alertEl.innerHTML = `<svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> ${err.message}`;
    } finally { btn.disabled = false; }
}
