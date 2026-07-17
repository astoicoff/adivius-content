<?php $pageTitle = 'View Image | Content Creator'; ?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
    .img-layout          { display: grid; grid-template-columns: 1fr 340px; gap: 20px; align-items: start; }
    @media (max-width: 860px) { .img-layout { grid-template-columns: 1fr; } }

    .img-main            { border-radius: 10px; overflow: hidden; border: 1px solid var(--light-gray); background: var(--off-white); min-height: 200px; display: flex; align-items: center; justify-content: center; }
    .img-main img        { max-width: 100%; display: block; }
    .img-shimmer         { width: 100%; min-height: 260px; background: linear-gradient(90deg, var(--off-white) 25%, var(--light-gray) 50%, var(--off-white) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; }
    @keyframes shimmer   { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

    .img-sidebar         { display: flex; flex-direction: column; gap: 14px; }
    .img-meta-card       { background: var(--card); border: 1px solid var(--light-gray); border-radius: 10px; overflow: hidden; }
    .img-meta-card-head  { padding: 12px 16px; border-bottom: 1px solid var(--light-gray); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); }
    .img-meta-card-body  { padding: 12px 16px; display: flex; flex-direction: column; gap: 10px; }
    .img-meta-row        { display: flex; align-items: baseline; gap: 10px; }
    .img-meta-key        { font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.7px; min-width: 52px; flex-shrink: 0; }
    .img-meta-val        { font-size: 13px; color: var(--dark); line-height: 1.4; font-family: 'Inter', sans-serif; word-break: break-word; }

    .prompt-box          { background: var(--off-white); border: 1px solid var(--light-gray); border-radius: 8px; padding: 12px; font-size: 13px; font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark); white-space: pre-wrap; word-break: break-word; }
    .revised-note        { font-size: 11px; color: var(--text-muted); font-family: 'Inter', sans-serif; line-height: 1.5; margin-top: 8px; }
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">
    <div class="top-bar">
        <div>
            <div class="top-bar-title" id="topBarTitle">Image</div>
            <div class="top-bar-subtitle" id="topBarSubtitle">AI-generated image</div>
        </div>
        <a id="topBarGroup" href="/content-groups" style="display:none;align-items:center;gap:6px;font-size:13px;font-family:'Inter',sans-serif;font-weight:500;color:var(--text-muted);text-decoration:none;white-space:nowrap;">
            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
            <span id="topBarGroupName"></span>
        </a>
    </div>
    <div class="content-area">

        <!-- Header row -->
        <div style="margin-bottom:20px;">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <a class="btn-back" id="backBtn" href="/content-groups">
                    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                    <span id="backLabel">Content Groups</span>
                </a>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span id="viewBadge"></span>
                    <div id="viewDate" style="font-size:12px;color:var(--text-muted);font-family:'Inter',sans-serif;"></div>
                </div>
            </div>
            <div id="viewKeyword" style="font-size:20px;font-weight:700;color:var(--dark);margin-top:10px;line-height:1.3;letter-spacing:0.3px;text-transform:uppercase;"></div>
        </div>

        <!-- Loading -->
        <div id="loadingState" class="loading-bar visible" style="margin-bottom:20px;">
            <div class="spinner"></div> Loading image…
        </div>

        <!-- Error -->
        <div id="errorState" class="alert alert-error" style="display:none;">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span id="errorText"></span>
        </div>

        <!-- Content -->
        <div id="contentArea" style="display:none;">

            <!-- Action bar -->
            <div class="content-view-bar" style="margin-bottom:16px;">
                <button id="btnRegenerate" class="btn btn-green" onclick="regenerateImage()">
                    <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.95"/></svg>
                    Regenerate
                </button>
                <a id="btnNewPrompt" href="#" class="btn btn-secondary">
                    <svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                    New Prompt
                </a>
                <a id="btnDownload" href="#" download="generated-image.jpg" class="btn btn-blue" target="_blank">
                    <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download
                </a>
                <button id="btnDelete" class="btn btn-red" onclick="deleteImage()">
                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                    Delete
                </button>
            </div>

            <!-- Regenerate progress -->
            <div class="loading-bar" id="regenLoading" style="margin-bottom:16px;">
                <div class="spinner"></div>
                <span id="regenLoadingText">Regenerating image…</span>
            </div>

            <!-- Two-column layout -->
            <div class="img-layout">
                <!-- Image -->
                <div>
                    <div class="img-main" id="imgWrap">
                        <div id="imgShimmer" class="img-shimmer" style="display:none;"></div>
                        <img id="mainImage" src="" alt="" style="display:none;">
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="img-sidebar">
                    <!-- Prompt -->
                    <div class="img-meta-card">
                        <div class="img-meta-card-head">Image Prompt</div>
                        <div class="img-meta-card-body">
                            <div class="prompt-box" id="promptBox"></div>
                            <div id="revisedNote" class="revised-note" style="display:none;">
                                <strong>DALL-E revised:</strong> <span id="revisedText"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Metadata -->
                    <div class="img-meta-card">
                        <div class="img-meta-card-head">Details</div>
                        <div class="img-meta-card-body">
                            <div class="img-meta-row" id="metaGroup" style="display:none;">
                                <span class="img-meta-key">Group</span>
                                <span class="img-meta-val" id="metaGroupVal"></span>
                            </div>
                            <div class="img-meta-row">
                                <span class="img-meta-key">Keyword</span>
                                <span class="img-meta-val" id="metaKeyword"></span>
                            </div>
                            <div class="img-meta-row" id="metaModelRow" style="display:none;">
                                <span class="img-meta-key">Model</span>
                                <span class="img-meta-val" id="metaModel"></span>
                            </div>
                            <div class="img-meta-row" id="metaSizeRow" style="display:none;">
                                <span class="img-meta-key">Size</span>
                                <span class="img-meta-val" id="metaSize"></span>
                            </div>
                            <div class="img-meta-row" id="metaQualityRow" style="display:none;">
                                <span class="img-meta-key">Quality</span>
                                <span class="img-meta-val" id="metaQuality"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.js"></script>
<script src="/scripts/shared.js"></script>
<script src="/scripts/view-image.js"></script>
</body>
</html>
