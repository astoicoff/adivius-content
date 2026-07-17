let currentGenerationId = null;
let selectedSize        = '1792x1024';
let selectedQuality     = 'standard';

const COST_TABLE = {
    standard: { '1024x1024': '$0.04', '1792x1024': '$0.08', '1024x1792': '$0.08' },
    hd:       { '1024x1024': '$0.08', '1792x1024': '$0.12', '1024x1792': '$0.12' },
};

// ── Size / quality pickers ────────────────────────────────────────────────────

function setSize(size) {
    selectedSize = size;
    document.getElementById('size169Btn').classList.toggle('btn-view-active', size === '1792x1024');
    document.getElementById('size11Btn') .classList.toggle('btn-view-active', size === '1024x1024');
    document.getElementById('size916Btn').classList.toggle('btn-view-active', size === '1024x1792');
    updateCostNote();
}

function setQuality(q) {
    selectedQuality = q;
    document.getElementById('qualStdBtn').classList.toggle('btn-view-active', q === 'standard');
    document.getElementById('qualHdBtn') .classList.toggle('btn-view-active', q === 'hd');
    updateCostNote();
}

function updateCostNote() {
    const cost = COST_TABLE[selectedQuality]?.[selectedSize] ?? '';
    document.getElementById('qualityNote').textContent = cost + ' / image';
}

// ── Alert ────────────────────────────────────────────────────────────────────

function showAlert(msg) {
    document.getElementById('globalAlertText').textContent = msg;
    document.getElementById('globalAlert').style.display = '';
    document.getElementById('globalAlert').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function hideAlert() {
    document.getElementById('globalAlert').style.display = 'none';
}

// ── Progress steps ────────────────────────────────────────────────────────────

function setStep(n) {
    for (let i = 1; i <= 2; i++) {
        document.getElementById(`step${i}dot`).classList.toggle('active', i <= n);
        document.getElementById(`step${i}label`).classList.toggle('active', i <= n);
    }
    document.getElementById('line1').classList.toggle('active', n >= 2);
}

// ── SSE stream helper ────────────────────────────────────────────────────────

async function readStream(url, options, onToken, onProgress, onDone, onError) {
    let res;
    try {
        res = await fetch(url, options);
    } catch (err) {
        onError(err.message);
        return;
    }
    const ct = res.headers.get('Content-Type') || '';
    if (!ct.includes('text/event-stream')) {
        try { const d = await res.json(); onError(d.detail || `HTTP ${res.status}`); }
        catch { onError(`HTTP ${res.status}`); }
        return;
    }
    const reader = res.body.getReader();
    const dec    = new TextDecoder();
    let buf      = '';
    try {
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buf += dec.decode(value, { stream: true });
            const lines = buf.split('\n');
            buf = lines.pop();
            for (const line of lines) {
                if (!line.startsWith('data: ')) continue;
                const raw = line.slice(6).trim();
                if (!raw) continue;
                let ev;
                try { ev = JSON.parse(raw); } catch { continue; }
                if (ev.type === 'token')    onToken(ev.text);
                if (ev.type === 'progress') onProgress(ev.message);
                if (ev.type === 'done')     { onDone(ev); return; }
                if (ev.type === 'error')    { onError(ev.message); return; }
            }
        }
    } catch (err) {
        onError(err.message);
    }
}

// ── Groups ───────────────────────────────────────────────────────────────────

async function loadGroups() {
    try {
        const res    = await fetch(`${API_URL}/api/groups.php`, { headers: authHeaders() });
        const data   = await res.json();
        const groups = data.groups || [];
        const sel    = document.getElementById('groupSelect');
        sel.innerHTML = '<option value="">— Select a content group —</option>';
        if (!groups.length) {
            document.getElementById('noGroupsWarning').style.display = '';
        }
        groups.forEach(g => {
            const opt = document.createElement('option');
            opt.value       = g.id;
            opt.textContent = g.name;
            sel.appendChild(opt);
        });
    } catch (e) {
        showAlert('Failed to load content groups.');
    }
}

// ── Phase 1: Generate Prompt ─────────────────────────────────────────────────

document.getElementById('phase1Form').addEventListener('submit', async function(e) {
    e.preventDefault();
    hideAlert();

    const keyword  = document.getElementById('keywordInput').value.trim();
    const group_id = document.getElementById('groupSelect').value;
    const model    = document.getElementById('modelSelect').value;

    if (!group_id) { showAlert('Please select a content group.'); return; }
    if (!keyword)  { showAlert('Please enter a keyword or topic.'); return; }

    const btn          = document.getElementById('generatePromptBtn');
    const loadingBar   = document.getElementById('phase1Loading');
    const loadingText  = document.getElementById('phase1LoadingText');
    const promptEditor = document.getElementById('promptEditor');

    btn.disabled = true;
    loadingBar.classList.add('visible');
    loadingText.textContent = 'Generating image prompt…';
    promptEditor.value = '';

    await readStream(
        `${API_URL}/api/image-phase1.php`,
        { method: 'POST', headers: authHeaders(), body: JSON.stringify({ keyword, group_id, model }) },
        (token) => { promptEditor.value += token; },
        (msg)   => { loadingText.textContent = msg; },
        (ev)    => {
            btn.disabled = false;
            loadingBar.classList.remove('visible');
            currentGenerationId = ev.generation_id;
            if (ev.prompt) promptEditor.value = ev.prompt;
            document.getElementById('phase2Section').classList.remove('hidden');
            setStep(2);
            document.getElementById('phase2Section').scrollIntoView({ behavior: 'smooth', block: 'start' });
        },
        (msg)   => {
            btn.disabled = false;
            loadingBar.classList.remove('visible');
            showAlert(msg || 'Failed to generate prompt.');
        }
    );
});

// ── Phase 2: Generate Image ──────────────────────────────────────────────────

async function generateImage() {
    hideAlert();

    const prompt = document.getElementById('promptEditor').value.trim();
    if (!prompt)              { showAlert('Prompt cannot be empty.'); return; }
    if (!currentGenerationId) { showAlert('Please complete Phase 1 first.'); return; }

    const btn         = document.getElementById('generateImageBtn');
    const loadingBar  = document.getElementById('phase2Loading');
    const loadingText = document.getElementById('phase2LoadingText');

    btn.disabled = true;
    loadingBar.classList.add('visible');
    loadingText.textContent = 'Generating image with DALL-E 3…';

    // Show shimmer while waiting
    document.getElementById('imageShimmer').style.display = '';
    document.getElementById('resultImage').style.display  = 'none';
    document.getElementById('revisedPromptNote').style.display = 'none';
    document.getElementById('phase3Section').classList.remove('hidden');
    document.getElementById('phase3Section').scrollIntoView({ behavior: 'smooth', block: 'start' });

    await readStream(
        `${API_URL}/api/image-phase2.php`,
        { method: 'POST', headers: authHeaders(), body: JSON.stringify({ generation_id: currentGenerationId, prompt, size: selectedSize, quality: selectedQuality }) },
        () => {},
        (msg)  => { loadingText.textContent = msg; },
        (ev)   => {
            btn.disabled = false;
            loadingBar.classList.remove('visible');
            showResult(ev.image_url, ev.revised_prompt);
        },
        (msg)  => {
            btn.disabled = false;
            loadingBar.classList.remove('visible');
            document.getElementById('imageShimmer').style.display = 'none';
            showAlert(msg || 'Failed to generate image.');
        }
    );
}

function showResult(imageUrl, revisedPrompt) {
    document.getElementById('imageShimmer').style.display = 'none';
    const img = document.getElementById('resultImage');
    img.src          = imageUrl;
    img.style.display = '';

    document.getElementById('downloadBtn').href = imageUrl;

    if (revisedPrompt) {
        document.getElementById('revisedPromptText').textContent = revisedPrompt;
        document.getElementById('revisedPromptNote').style.display = '';
    }
}

// ── Reset ────────────────────────────────────────────────────────────────────

function resetToNew() {
    currentGenerationId = null;
    document.getElementById('keywordInput').value  = '';
    document.getElementById('promptEditor').value  = '';
    document.getElementById('resultImage').src     = '';
    document.getElementById('resultImage').style.display       = 'none';
    document.getElementById('imageShimmer').style.display      = 'none';
    document.getElementById('revisedPromptNote').style.display = 'none';
    document.getElementById('phase2Section').classList.add('hidden');
    document.getElementById('phase3Section').classList.add('hidden');
    hideAlert();
    setStep(1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    hideAlert();
    updateCostNote();
    loadGroups();
});
