const PROVIDERS = [
    { id: 'openai',     input: 'openaiKeyInput' },
    { id: 'claude',     input: 'claudeKeyInput' },
    { id: 'gemini',     input: 'geminiKeyInput' },
    { id: 'perplexity', input: 'perplexityKeyInput' },
    { id: 'serpapi',    input: 'serpapiKeyInput' },
];

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
        alertEl.innerHTML = `<svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> ${escapeHtml(err.message)}`;
    } finally { btn.disabled = false; }
}

// ── Show / hide password ─────────────────────────────────────────────────────

function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    btn.innerHTML = showing
        ? '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
        : '<svg viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
}

// ── Test individual key ──────────────────────────────────────────────────────

function setStatus(provider, kind, text) {
    const el = document.getElementById('status-' + provider);
    if (!el) return;
    el.className = 'key-status ' + kind;

    let icon = '';
    if (kind === 'ok')        icon = '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
    else if (kind === 'fail') icon = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
    else if (kind === 'testing') icon = '<svg viewBox="0 0 24 24" style="animation:spin 0.8s linear infinite;transform-origin:center;"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg>';
    else                      icon = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>';

    el.innerHTML = icon + escapeHtml(text);
}

async function testOne(provider) {
    const cfg   = PROVIDERS.find(p => p.id === provider);
    if (!cfg) return;
    const input = document.getElementById(cfg.input);
    const key   = input.value.trim();

    if (!key) {
        setStatus(provider, 'fail', 'No key entered');
        return;
    }

    setStatus(provider, 'testing', 'Testing…');

    try {
        const res  = await fetch(`${API_URL}/api/test-key.php`, {
            method:  'POST',
            headers: authHeaders(),
            body:    JSON.stringify({ provider, api_key: key })
        });
        const data = await res.json();
        if (data.ok) {
            setStatus(provider, 'ok', 'Connected · ' + (data.detail || ''));
        } else {
            setStatus(provider, 'fail', data.detail || 'Failed');
        }
    } catch (err) {
        setStatus(provider, 'fail', err.message);
    }
}

async function testAll() {
    const btn = document.getElementById('testAllBtn');
    btn.disabled = true;
    try {
        await Promise.all(PROVIDERS.map(p => {
            const key = document.getElementById(p.input).value.trim();
            if (!key) {
                setStatus(p.id, 'untested', 'No key set');
                return Promise.resolve();
            }
            return testOne(p.id);
        }));
    } finally {
        btn.disabled = false;
    }
}
