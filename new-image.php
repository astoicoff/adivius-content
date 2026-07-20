<?php $pageTitle = 'New Image | Content Creator'; ?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
    .size-picker            { display: inline-flex; border: 1px solid var(--light-gray); border-radius: 8px; overflow: hidden; }
    .size-picker .btn       { border-radius: 0; border: none; padding: 7px 16px; font-size: 13px; display: flex; align-items: center; gap: 6px; }
    .size-picker .btn + .btn { border-left: 1px solid var(--light-gray); }

    .quality-toggle            { display: inline-flex; border: 1px solid var(--light-gray); border-radius: 8px; overflow: hidden; }
    .quality-toggle .btn       { border-radius: 0; border: none; padding: 7px 18px; font-size: 13px; }
    .quality-toggle .btn + .btn { border-left: 1px solid var(--light-gray); }

    .image-result-wrap { position: relative; border-radius: 10px; overflow: hidden; border: 1px solid var(--light-gray); background: var(--off-white); min-height: 260px; display: flex; align-items: center; justify-content: center; }
    .image-result-wrap img { max-width: 100%; display: block; }
    .image-shimmer { width: 100%; min-height: 260px; background: linear-gradient(90deg, var(--off-white) 25%, var(--light-gray) 50%, var(--off-white) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; }
    @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">
    <div class="top-bar">
        <div>
            <div class="top-bar-title">New Image</div>
            <div class="top-bar-subtitle">Generate AI images in two phases — prompt engineering then AI image creation</div>
        </div>
    </div>
    <div class="content-area">

        <div id="globalAlert" class="alert alert-error">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span id="globalAlertText"></span>
        </div>

        <div class="progress-steps">
            <div class="step"><div class="step-dot active" id="step1dot">1</div><div class="step-label active" id="step1label">Prompt</div></div>
            <div class="step-line" id="line1"></div>
            <div class="step"><div class="step-dot" id="step2dot">2</div><div class="step-label" id="step2label">Image</div></div>
        </div>

        <!-- Phase 1 -->
        <div id="phase1Section">
            <div class="card">
                <div class="card-accent red"></div>
                <div class="card-header">
                    <div><div class="card-title">Phase 1 — Generate Prompt</div><div class="card-subtitle">Select a content group and enter a keyword to engineer an image prompt</div></div>
                    <span class="badge badge-red"><span class="badge-dot"></span>Step 1</span>
                </div>
                <div class="card-body">
                    <div id="noGroupsWarning" class="alert alert-error" style="display:none;margin-bottom:16px;">
                        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        No content groups yet. <a href="/content-groups" style="color:inherit;font-weight:700;">Create one first →</a>
                    </div>
                    <form id="phase1Form">
                        <div class="form-group" style="margin-bottom:16px;">
                            <label class="form-label" for="groupSelect">Content Group</label>
                            <select class="form-input" id="groupSelect" required>
                                <option value="">— Select a content group —</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:16px;">
                            <label class="form-label" for="modelSelect">Text Model <span style="font-weight:400;color:var(--text-muted);">(for prompt engineering)</span></label>
                            <select class="form-input" id="modelSelect">
                                <optgroup label="OpenAI">
                                    <option value="gpt-5.5" selected>GPT-5.5</option>
                                </optgroup>
                                <optgroup label="Anthropic">
                                    <option value="claude-opus-4-7">Claude Opus 4.7</option>
                                    <option value="claude-sonnet-4-6">Claude Sonnet 4.6</option>
                                </optgroup>
                                <optgroup label="Google">
                                    <option value="gemini-2.5-pro">Gemini 2.5 Pro</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="keywordInput">Keyword / Topic</label>
                            <input type="text" class="form-input" id="keywordInput" placeholder="e.g. hero image for AI agency website" required>
                        </div>
                        <button type="submit" class="btn btn-primary" id="generatePromptBtn">
                            <svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                            Generate Prompt
                        </button>
                    </form>
                    <div class="loading-bar" id="phase1Loading">
                        <div class="spinner"></div>
                        <span id="phase1LoadingText">Generating image prompt…</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Phase 2 -->
        <div id="phase2Section" class="hidden">
            <div class="card">
                <div class="card-accent blue"></div>
                <div class="card-header">
                    <div><div class="card-title">Phase 2 — Generate Image</div><div class="card-subtitle">Review and refine the prompt, choose size and quality, then generate</div></div>
                    <span class="badge badge-blue"><span class="badge-dot"></span>Step 2</span>
                </div>
                <div class="card-body">
                    <div class="form-group" style="margin-bottom:20px;">
                        <label class="form-label" for="promptEditor">Image Prompt <span style="font-weight:400;color:var(--text-muted);">(editable)</span></label>
                        <textarea class="form-textarea" id="promptEditor" rows="8"></textarea>
                    </div>

                    <!-- Optional context image for AI image editing (uses /v1/images/edits) -->
                    <div class="form-group" style="margin-bottom:20px;">
                        <label class="form-label">Reference Image <span style="font-weight:400;color:var(--text-muted);">(optional — for image editing)</span></label>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <label for="contextImageInput" class="btn btn-secondary" style="cursor:pointer;margin:0;">
                                <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                Attach Image
                            </label>
                            <input type="file" id="contextImageInput" accept="image/png,image/jpeg,image/webp" style="display:none;" onchange="onContextImageChange(event)">
                            <div id="contextImagePreview" style="display:none;align-items:center;gap:8px;background:var(--off-white);border:1px solid var(--light-gray);border-radius:8px;padding:6px 10px;">
                                <img id="contextImageThumb" src="" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:4px;flex-shrink:0;">
                                <span id="contextImageName" style="font-size:12px;color:var(--dark);font-family:'Inter',sans-serif;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                                <button type="button" onclick="clearContextImage()" style="background:none;border:none;cursor:pointer;padding:0 2px;color:var(--text-muted);font-size:18px;line-height:1;flex-shrink:0;" title="Remove">×</button>
                            </div>
                        </div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:5px;">When attached, the prompt describes how to edit the image rather than generate from scratch</div>
                    </div>

                    <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;margin-bottom:20px;">
                        <div>
                            <div class="form-label" style="margin-bottom:8px;">Size</div>
                            <div class="size-picker">
                                <button type="button" class="btn btn-secondary btn-view-active" id="size169Btn" onclick="setSize('1792x1024')">
                                    <svg viewBox="0 0 22 16" style="width:16px;height:12px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;"><rect x="1" y="1" width="20" height="14" rx="2"/></svg>
                                    Landscape 16:9
                                </button>
                                <button type="button" class="btn btn-secondary" id="size11Btn" onclick="setSize('1024x1024')">
                                    <svg viewBox="0 0 18 18" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;"><rect x="1" y="1" width="16" height="16" rx="2"/></svg>
                                    Square 1:1
                                </button>
                                <button type="button" class="btn btn-secondary" id="size916Btn" onclick="setSize('1024x1792')">
                                    <svg viewBox="0 0 14 22" style="width:10px;height:16px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;"><rect x="1" y="1" width="12" height="20" rx="2"/></svg>
                                    Portrait 9:16
                                </button>
                            </div>
                        </div>
                        <div>
                            <div class="form-label" style="margin-bottom:8px;">Quality</div>
                            <div class="quality-toggle">
                                <button type="button" class="btn btn-secondary btn-view-active" id="qualStdBtn" onclick="setQuality('standard')">Standard</button>
                                <button type="button" class="btn btn-secondary" id="qualHdBtn" onclick="setQuality('hd')">HD</button>
                            </div>
                            <div id="qualityNote" style="font-size:11px;color:var(--text-muted);margin-top:5px;"></div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <button type="button" class="btn btn-blue" id="generateImageBtn" onclick="generateImage()">
                            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                            Generate Image
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetToNew()">
                            <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.95"/></svg>
                            Start Over
                        </button>
                    </div>
                    <div class="loading-bar" id="phase2Loading">
                        <div class="spinner"></div>
                        <span id="phase2LoadingText">Generating image…</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Result -->
        <div id="phase3Section" class="hidden">
            <div class="card">
                <div class="card-accent green"></div>
                <div class="card-header">
                    <div><div class="card-title">Generated Image</div><div class="card-subtitle">Your AI image is ready</div></div>
                    <span class="badge badge-green"><span class="badge-dot"></span>Complete</span>
                </div>
                <div class="card-body">
                    <div class="image-result-wrap" id="imageResultWrap">
                        <div id="imageShimmer" class="image-shimmer" style="display:none;"></div>
                        <img id="resultImage" src="" alt="Generated image" style="display:none;">
                    </div>
                    <div id="revisedPromptNote" style="display:none;margin-top:12px;padding:12px;background:var(--off-white);border:1px solid var(--light-gray);border-radius:8px;font-size:12px;color:var(--text-muted);line-height:1.6;">
                        <strong style="color:var(--dark);">Model-revised prompt:</strong> <span id="revisedPromptText"></span>
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
                        <a id="downloadBtn" href="#" download="generated-image.jpg" class="btn btn-green" target="_blank">
                            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Download
                        </a>
                        <button type="button" class="btn btn-secondary" onclick="resetToNew()">
                            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                            New Image
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.js"></script>
<script src="/scripts/shared.js"></script>
<script src="/scripts/new-image.js"></script>
</body>
</html>
