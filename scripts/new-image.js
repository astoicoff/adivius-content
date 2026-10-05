let currentGenerationId = null;
let selectedSize        = '1792x1024';
let selectedQuality     = 'standard';
let contextImageFile    = null;
let inputMode           = 'keyword';   // 'keyword' | 'description'
let referenceMatchedSize = null;       // size auto-picked to match the attached photo

// Output sizes the image model accepts, as width/height ratios. An edit whose
// output shape differs from the photo forces the model to crop or recompose
// the scene, so an attached photo preselects the nearest shape.
const SIZE_RATIOS = {
    '1792x1024': 1792 / 1024, '1536x1024': 1536 / 1024, '1024x1024': 1,
    '1024x1536': 1024 / 1536, '1024x1792': 1024 / 1792,
};
const SIZE_LABELS = {
    '1792x1024': '16:9', '1536x1024': '3:2', '1024x1024': '1:1', '1024x1536': '2:3', '1024x1792': '9:16',
};

function nearestSize(width, height) {
    const r = Math.log(width / height);
    return Object.keys(SIZE_RATIOS).reduce((best, s) =>
        Math.abs(Math.log(SIZE_RATIOS[s]) - r) < Math.abs(Math.log(SIZE_RATIOS[best]) - r) ? s : best);
}

function matchSizeToReference(width, height) {
    const size = nearestSize(width, height);
    referenceMatchedSize = size;
    setSize(size);
    const note = document.getElementById('sizeMatchNote');
    note.textContent   = `Matched to your photo's shape (${SIZE_LABELS[size]}) so the scene isn't cropped or recomposed.`;
    note.style.display = '';
}

function setInputMode(mode) {
    inputMode = mode;
    const isKw = mode === 'keyword';
    document.getElementById('modeKeywordBtn').classList.toggle('btn-view-active', isKw);
    document.getElementById('modeDescriptionBtn').classList.toggle('btn-view-active', !isKw);
    document.getElementById('keywordInput').style.display     = isKw ? '' : 'none';
    document.getElementById('descriptionInput').style.display = isKw ? 'none' : '';
    document.getElementById('inputModeLabel').textContent     = isKw ? 'Keyword / Topic' : 'Image Description';
    (isKw ? document.getElementById('keywordInput') : document.getElementById('descriptionInput')).focus();
}

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
    document.getElementById('size32Btn') .classList.toggle('btn-view-active', size === '1536x1024');
    document.getElementById('size23Btn') .classList.toggle('btn-view-active', size === '1024x1536');
    const note = document.getElementById('sizeMatchNote');
    if (note && size !== referenceMatchedSize) note.style.display = 'none';
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
    document.getElementById('qualityNote').textContent = cost ? cost + ' / image' : '';
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
        catch { onError(res.status === 413 ? 'The image is too large to upload (limit about 4 MB). Try a smaller photo.' : `HTTP ${res.status}`); }
        return;
    }
    const reader = res.body.getReader();
    const dec    = new TextDecoder();
    let buf       = '';
    let completed = false;
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
                if (ev.type === 'done')     { completed = true; onDone(ev); return; }
                if (ev.type === 'error')    { completed = true; onError(ev.message); return; }
            }
        }
    } catch (err) {
        onError(err.message);
        return;
    }
    if (!completed) {
        onError('The request timed out or the connection was interrupted. Please try again.');
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

// ── Image agents ─────────────────────────────────────────────────────────────

let _agents = [];

async function loadAgentsForGroup(groupId) {
    const sel = document.getElementById('agentSelect');
    _agents = [];
    sel.innerHTML = '<option value="">— No agents in this group —</option>';
    if (!groupId) return;
    try {
        const res  = await fetch(`${API_URL}/api/image-agents.php?group_id=${encodeURIComponent(groupId)}`, { headers: authHeaders() });
        const data = await res.json();
        if (!res.ok) return;
        _agents = data.agents || [];
        if (_agents.length) {
            sel.innerHTML = '';
            _agents.forEach((a, i) => {
                const opt = document.createElement('option');
                opt.value       = a.id;
                opt.textContent = a.name;
                if (i === 0) opt.selected = true;
                sel.appendChild(opt);
            });
            applyAgentDefaults(_agents[0]);
        }
    } catch (_) {}
}

function applyAgentDefaults(agent) {
    if (!agent) return;
    // An attached photo's shape wins over the agent's default size.
    if (agent.size && !contextImageFile) setSize(agent.size);
    if (agent.quality) setQuality(agent.quality);
}

document.getElementById('groupSelect').addEventListener('change', e => loadAgentsForGroup(e.target.value));
document.getElementById('agentSelect').addEventListener('change', e => {
    applyAgentDefaults(_agents.find(a => a.id === e.target.value));
});

// ── Phase 1: Generate Prompt ─────────────────────────────────────────────────

document.getElementById('phase1Form').addEventListener('submit', async function(e) {
    e.preventDefault();
    hideAlert();

    const keyword     = document.getElementById('keywordInput').value.trim();
    const description = document.getElementById('descriptionInput').value.trim();
    const group_id    = document.getElementById('groupSelect').value;
    const model       = document.getElementById('modelSelect').value;

    if (!group_id) { showAlert('Please select a content group.'); return; }
    if (inputMode === 'keyword'     && !keyword)     { showAlert('Please enter a keyword or topic.'); return; }
    if (inputMode === 'description' && !description) { showAlert('Please enter an image description.'); return; }

    // The prompt is written now, before any image is sent — so a request that
    // talks about "the attached image" with nothing attached can only produce
    // a from-scratch image. Catch it here instead of after two paid calls.
    const request = inputMode === 'description' ? description : keyword;
    if (!contextImageFile && /\b(attached|attachment|this (photo|image|picture|pic)|the (photo|image|picture) (above|below)|refine|retouch)\b/i.test(request)) {
        showAlert('Your request refers to an attached image, but none is attached. Add it under Reference Image, then generate the prompt.');
        document.getElementById('contextImageInput')?.closest('.form-group')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

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
        { method: 'POST', headers: authHeaders(), body: JSON.stringify({
            ...(inputMode === 'description' ? { description } : { keyword }),
            group_id, model,
            agent_id:      document.getElementById('agentSelect').value || undefined,
            has_reference: !!contextImageFile,
        }) },
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

// ── Context image (optional reference for editing) ───────────────────────────

function onContextImageChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    contextImageFile = file;
    const reader = new FileReader();
    reader.onload = (ev) => {
        document.getElementById('contextImageThumb').src       = ev.target.result;
        document.getElementById('contextImageName').textContent = file.name;
        document.getElementById('contextImagePreview').style.display = 'flex';
        const probe = new Image();
        probe.onload = () => matchSizeToReference(probe.naturalWidth, probe.naturalHeight);
        probe.src = ev.target.result;
    };
    reader.readAsDataURL(file);
}

function clearContextImage(skipConfirm = false) {
    if (contextImageFile && !skipConfirm
        && !confirm('Remove the reference image? The prompt will be written for from-scratch generation instead.')) return;
    contextImageFile = null;
    referenceMatchedSize = null;
    document.getElementById('sizeMatchNote').style.display = 'none';
    document.getElementById('contextImageInput').value          = '';
    document.getElementById('contextImagePreview').style.display = 'none';
}

// ── Phase 2: Generate Image ──────────────────────────────────────────────────

async function generateImage() {
    hideAlert();

    const prompt = document.getElementById('promptEditor').value.trim();
    if (!prompt)              { showAlert('Prompt cannot be empty.'); return; }
    if (!currentGenerationId) { showAlert('Please complete Phase 1 first.'); return; }

    // Shrink before uploading: Vercel 413s request bodies over 4.5 MB, and
    // phone photos are often larger. That rejection used to end the run
    // with nothing generated and the reference never stored.
    let uploadFile = null;
    if (contextImageFile) {
        try { uploadFile = await prepareUploadImage(contextImageFile); }
        catch (err) { showAlert(err.message); return; }
    }

    const btn         = document.getElementById('generateImageBtn');
    const loadingBar  = document.getElementById('phase2Loading');
    const loadingText = document.getElementById('phase2LoadingText');

    btn.disabled = true;
    loadingBar.classList.add('visible');
    loadingText.textContent = 'Generating image…';

    // Show shimmer while waiting
    document.getElementById('imageShimmer').style.display = '';
    document.getElementById('resultImage').style.display  = 'none';
    document.getElementById('revisedPromptNote').style.display = 'none';
    document.getElementById('phase3Section').classList.remove('hidden');
    document.getElementById('phase3Section').scrollIntoView({ behavior: 'smooth', block: 'start' });

    let fetchOptions;
    if (contextImageFile) {
        const fd = new FormData();
        fd.append('generation_id', currentGenerationId);
        fd.append('prompt', prompt);
        fd.append('size', selectedSize);
        fd.append('quality', selectedQuality);
        fd.append('image', uploadFile);
        fetchOptions = { method: 'POST', headers: { Authorization: authHeaders()['Authorization'] }, body: fd };
    } else {
        fetchOptions = { method: 'POST', headers: authHeaders(), body: JSON.stringify({ generation_id: currentGenerationId, prompt, size: selectedSize, quality: selectedQuality }) };
    }

    await readStream(
        `${API_URL}/api/image-phase2.php`,
        fetchOptions,
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

    // Jump straight into refinement on view-image (?refine=1 auto-opens it)
    const refineBtn = document.getElementById('refineResultBtn');
    if (refineBtn && currentGenerationId) {
        const groupId = document.getElementById('groupSelect').value;
        refineBtn.href = `/view-image?id=${encodeURIComponent(currentGenerationId)}`
            + (groupId ? `&group=${encodeURIComponent(groupId)}` : '') + '&refine=1';
        refineBtn.style.display = '';
    }

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
    const refineBtn = document.getElementById('refineResultBtn');
    if (refineBtn) refineBtn.style.display = 'none';
    clearContextImage(true);
    hideAlert();
    setStep(1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('navNewImage').classList.add('active');
    hideAlert();
    updateCostNote();
    initAuth(async () => {
        await loadGroups();
        // Prefill from view-image's "New Prompt" link (?group=&keyword=)
        const params  = new URLSearchParams(window.location.search);
        const groupId = params.get('group');
        const keyword = params.get('keyword');
        if (groupId) {
            document.getElementById('groupSelect').value = groupId;
            await loadAgentsForGroup(groupId);
        }
        if (keyword) document.getElementById('keywordInput').value = keyword;
    });
});
