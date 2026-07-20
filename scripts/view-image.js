let imgData = null;

// ── SSE stream helper ─────────────────────────────────────────────────────────

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

// ── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    const navEl = document.getElementById('navNewImage');
    if (navEl) navEl.classList.add('active');
    const params  = new URLSearchParams(window.location.search);
    const id      = params.get('id');
    const groupId = params.get('group');

    if (!id) { showError('No image ID provided.'); return; }

    if (groupId) {
        document.getElementById('backBtn').href          = `/content-groups?group=${encodeURIComponent(groupId)}`;
        document.getElementById('backLabel').textContent = 'Content Group';
    }

    initAuth(async () => { await loadImage(id); });
});

// ── Load ──────────────────────────────────────────────────────────────────────

async function loadImage(id) {
    try {
        const res = await fetch(`${API_URL}/api/images.php?id=${encodeURIComponent(id)}`, { headers: authHeaders() });
        if (!res.ok) {
            let detail = 'Failed to load image.';
            try { const e = await res.json(); detail = e.detail || detail; } catch (_) {}
            showError(detail); return;
        }
        imgData = await res.json();
        renderImage(imgData);
    } catch (err) {
        showError(err.message);
    }
}

function renderImage(data) {
    const title = toTitleCase(data.keyword);
    document.getElementById('topBarTitle').textContent    = title;
    document.getElementById('topBarSubtitle').textContent = 'AI-generated image';
    document.getElementById('viewKeyword').textContent    = data.keyword.toUpperCase();
    document.getElementById('viewBadge').innerHTML        = statusBadge(data.status);
    document.getElementById('viewDate').textContent       = new Date(data.created_at).toLocaleDateString('en-US', {
        month: 'long', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });

    // Prompt
    document.getElementById('promptBox').textContent = data.prompt || '(No prompt stored)';
    if (data.revised_prompt && data.revised_prompt !== data.prompt) {
        document.getElementById('revisedText').textContent     = data.revised_prompt;
        document.getElementById('revisedNote').style.display  = '';
    }

    // Metadata
    document.getElementById('metaKeyword').textContent = data.keyword;
    if (data.model) {
        document.getElementById('metaModel').textContent   = modelLabel(data.model);
        document.getElementById('metaModelRow').style.display = '';
    }
    if (data.size) {
        document.getElementById('metaSize').textContent   = data.size;
        document.getElementById('metaSizeRow').style.display = '';
    }
    if (data.quality) {
        document.getElementById('metaQuality').textContent = data.quality.charAt(0).toUpperCase() + data.quality.slice(1);
        document.getElementById('metaQualityRow').style.display = '';
    }

    // Image. Never replace imgWrap's innerHTML — that would destroy
    // imgShimmer/mainImage and crash any later Regenerate.
    if (data.image_url) {
        setMainImage(data.image_url);
    } else {
        const ph = document.getElementById('imgPlaceholder');
        ph.textContent   = data.status === 'failed' ? 'Generation failed — no image was produced.' : 'Image not yet generated.';
        ph.style.display = '';
    }

    // Surface a stored failure — this was the "fails silently" gap: the row
    // carried the API error but the page never showed it.
    if (data.status === 'failed' && data.error) {
        document.getElementById('imgFailedError').textContent = data.error;
        document.getElementById('imgFailedAlert').style.display = 'flex';
    }

    // Download + New Prompt
    document.getElementById('btnDownload').href = data.image_url || '#';
    const newPromptUrl = `/new-image${data.group_id ? `?group=${encodeURIComponent(data.group_id)}&keyword=${encodeURIComponent(data.keyword)}` : ''}`;
    document.getElementById('btnNewPrompt').href = newPromptUrl;

    // Group name (async)
    if (data.group_id) {
        loadGroupName(data.group_id);
    }

    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('contentArea').style.display  = '';
}

function setMainImage(url) {
    document.getElementById('imgShimmer').style.display     = 'none';
    document.getElementById('imgPlaceholder').style.display = 'none';
    const img = document.getElementById('mainImage');
    img.src           = url;
    img.alt           = imgData?.keyword || 'Generated image';
    img.style.display = '';
}

async function loadGroupName(groupId) {
    try {
        const res  = await fetch(`${API_URL}/api/groups.php?id=${encodeURIComponent(groupId)}`, { headers: authHeaders() });
        const data = await res.json();
        if (data.name) {
            const link = document.getElementById('topBarGroup');
            document.getElementById('topBarGroupName').textContent = data.name;
            link.href         = `/content-groups?group=${encodeURIComponent(groupId)}`;
            link.style.display = 'flex';

            document.getElementById('metaGroupVal').textContent   = data.name;
            document.getElementById('metaGroup').style.display    = '';
        }
    } catch (_) {}
}

// ── Regenerate ────────────────────────────────────────────────────────────────

async function regenerateImage() {
    if (!imgData?.id || !imgData?.prompt) {
        showToast('No prompt stored — use New Prompt to regenerate from scratch.');
        return;
    }

    const btn = document.getElementById('btnRegenerate');
    btn.disabled = true;

    document.getElementById('regenLoading').classList.add('visible');
    document.getElementById('imgFailedAlert').style.display = 'none';
    document.getElementById('imgPlaceholder').style.display = 'none';
    document.getElementById('imgShimmer').style.display  = '';
    document.getElementById('mainImage').style.display   = 'none';

    await readStream(
        `${API_URL}/api/image-phase2.php`,
        { method: 'POST', headers: authHeaders(), body: JSON.stringify({
            generation_id: imgData.id,
            prompt:        imgData.prompt,
            size:          imgData.size    || '1792x1024',
            quality:       imgData.quality || 'standard',
        })},
        () => {},
        (msg) => { document.getElementById('regenLoadingText').textContent = msg; },
        (ev)  => {
            btn.disabled = false;
            document.getElementById('regenLoading').classList.remove('visible');
            imgData.image_url = ev.image_url;
            if (ev.revised_prompt) {
                imgData.revised_prompt = ev.revised_prompt;
                if (ev.revised_prompt !== imgData.prompt) {
                    document.getElementById('revisedText').textContent    = ev.revised_prompt;
                    document.getElementById('revisedNote').style.display  = '';
                }
            }
            setMainImage(ev.image_url);
            document.getElementById('btnDownload').href = ev.image_url;
            document.getElementById('viewBadge').innerHTML = statusBadge('completed');
        },
        (msg) => {
            btn.disabled = false;
            document.getElementById('regenLoading').classList.remove('visible');
            document.getElementById('imgShimmer').style.display = 'none';
            if (imgData?.image_url) {
                setMainImage(imgData.image_url);
            } else {
                const ph = document.getElementById('imgPlaceholder');
                ph.textContent = 'Generation failed — no image was produced.';
                ph.style.display = '';
            }
            document.getElementById('imgFailedError').textContent   = msg || 'Regeneration failed.';
            document.getElementById('imgFailedAlert').style.display = 'flex';
        }
    );
}

// ── Refine ────────────────────────────────────────────────────────────────────

function openRefinePanel() {
    document.getElementById('refinePromptTextarea').value = imgData?.prompt || '';
    document.getElementById('refineInstruction').value    = '';
    document.getElementById('refineLoading').classList.remove('visible');
    document.getElementById('refineGenerateBtn').disabled = false;
    document.getElementById('refinePanel').style.display  = '';
    document.getElementById('refinePanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
    setTimeout(() => document.getElementById('refineInstruction').focus(), 300);
}

function closeRefinePanel() {
    document.getElementById('refinePanel').style.display = 'none';
}

async function refineAndGenerate() {
    const instruction = document.getElementById('refineInstruction').value.trim();
    const promptVal   = document.getElementById('refinePromptTextarea').value.trim();

    if (!instruction) { showToast('Enter a modification instruction.'); return; }
    if (!promptVal)   { showToast('No prompt to refine.'); return; }

    const btn         = document.getElementById('refineGenerateBtn');
    const loadingBar  = document.getElementById('refineLoading');
    const loadingText = document.getElementById('refineLoadingText');

    btn.disabled            = true;
    loadingText.textContent = 'Refining prompt…';
    loadingBar.classList.add('visible');

    let refinedPrompt = '';

    await readStream(
        `${API_URL}/api/image-refine.php`,
        { method: 'POST', headers: authHeaders(), body: JSON.stringify({
            generation_id: imgData.id,
            instruction,
        })},
        (token) => {
            refinedPrompt += token;
            document.getElementById('refinePromptTextarea').value = refinedPrompt;
        },
        (msg) => { loadingText.textContent = msg; },
        async (ev) => {
            refinedPrompt  = ev.prompt || refinedPrompt;
            imgData.prompt = refinedPrompt;
            document.getElementById('refinePromptTextarea').value = refinedPrompt;
            document.getElementById('promptBox').textContent      = refinedPrompt || '';

            loadingText.textContent = 'Generating image…';
            document.getElementById('imgFailedAlert').style.display  = 'none';
            document.getElementById('imgPlaceholder').style.display  = 'none';
            document.getElementById('imgShimmer').style.display      = '';
            document.getElementById('mainImage').style.display       = 'none';

            await readStream(
                `${API_URL}/api/image-phase2.php`,
                { method: 'POST', headers: authHeaders(), body: JSON.stringify({
                    generation_id: imgData.id,
                    prompt:        refinedPrompt,
                    size:          imgData.size    || '1792x1024',
                    quality:       imgData.quality || 'standard',
                })},
                () => {},
                (msg2) => { loadingText.textContent = msg2; },
                (ev2) => {
                    btn.disabled = false;
                    loadingBar.classList.remove('visible');
                    imgData.image_url      = ev2.image_url;
                    imgData.revised_prompt = ev2.revised_prompt;
                    if (ev2.revised_prompt && ev2.revised_prompt !== refinedPrompt) {
                        document.getElementById('revisedText').textContent   = ev2.revised_prompt;
                        document.getElementById('revisedNote').style.display = '';
                    }
                    setMainImage(ev2.image_url);
                    document.getElementById('btnDownload').href    = ev2.image_url;
                    document.getElementById('viewBadge').innerHTML = statusBadge('completed');
                    closeRefinePanel();
                },
                (msg2) => {
                    btn.disabled = false;
                    loadingBar.classList.remove('visible');
                    document.getElementById('imgShimmer').style.display = 'none';
                    if (imgData?.image_url) {
                        setMainImage(imgData.image_url);
                    } else {
                        document.getElementById('imgPlaceholder').textContent   = 'Generation failed — no image was produced.';
                        document.getElementById('imgPlaceholder').style.display = '';
                    }
                    document.getElementById('imgFailedError').textContent   = msg2 || 'Image generation failed.';
                    document.getElementById('imgFailedAlert').style.display = 'flex';
                }
            );
        },
        (msg) => {
            btn.disabled = false;
            loadingBar.classList.remove('visible');
            showToast(msg || 'Prompt refinement failed.');
        }
    );
}

// ── Delete ────────────────────────────────────────────────────────────────────

async function deleteImage() {
    if (!confirm('Delete this image? This cannot be undone.')) return;
    const id      = new URLSearchParams(window.location.search).get('id');
    const groupId = new URLSearchParams(window.location.search).get('group') || imgData?.group_id;
    const btn     = document.getElementById('btnDelete');
    btn.disabled  = true;
    try {
        const res = await fetch(`${API_URL}/api/images.php?id=${encodeURIComponent(id)}`, {
            method: 'DELETE', headers: authHeaders()
        });
        if (!res.ok) throw new Error('Failed to delete image.');
        window.location.href = groupId ? `/content-groups?group=${encodeURIComponent(groupId)}` : '/content-groups';
    } catch (err) {
        showToast(err.message);
        btn.disabled = false;
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function showError(msg) {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('errorState').style.display   = 'flex';
    document.getElementById('errorText').textContent      = msg;
}
