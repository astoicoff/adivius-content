<?php $pageTitle = 'View Content | Content Creator'; ?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
    /* Expand content to fill page height */
    .content-area       { display: flex; flex-direction: column; }
    #contentArea        { flex: 1; display: flex; flex-direction: column; }
    #contentArea .card  { flex: 1; display: flex; flex-direction: column; margin-bottom: 0; }
    #contentArea .card-body { flex: 1; display: flex; flex-direction: column; }
    #htmlOutput, #editOutput { flex: 1; min-height: 300px; resize: none; }
    #cleanOutput        { flex: 1; min-height: 300px; }
    .content-view-bar .btn { padding: 6px 10px; }
    .card-copy-btn      { display:flex; align-items:center; gap:6px; background:var(--off-white); border:1px solid var(--light-gray); border-radius:6px; cursor:pointer; padding:5px 10px; color:var(--dark); font-size:12px; font-family:'Inter',sans-serif; font-weight:500; transition:background 0.15s,border-color 0.15s; }
    .card-copy-btn:hover { background:#e8e8e8; border-color:#bbb; }
    .card-copy-btn svg  { width:13px; height:13px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; }
    .card-copy-row      { display:flex; margin-bottom:10px; }

    /* Table of Contents */
    .toc-nav            { background:var(--off-white); border:1px solid var(--light-gray); border-radius:8px; padding:14px 18px; margin-bottom:24px; }
    .toc-title          { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--text-muted); margin-bottom:10px; }
    .toc-list           { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:4px; }
    .toc-item a         { text-decoration:none; color:var(--dark); font-size:13px; font-family:'Inter',sans-serif; line-height:1.4; }
    .toc-item a:hover   { color:var(--blue); }
    .toc-h1 a           { font-weight:600; }
    .toc-h2 a           { padding-left:12px; }
    .toc-h3 a           { padding-left:24px; font-size:12px; color:var(--text-muted); }
    .toc-h3 a:hover     { color:var(--blue); }

    /* Meta panel */
    #metaPanel          { margin-bottom: 16px; }
    #metaPanel .card    { flex: unset; margin-bottom: 0; }
    .meta-row           { display: flex; align-items: baseline; gap: 14px; }
    .meta-row + .meta-row { border-top: 1px solid var(--light-gray); padding-top: 10px; }
    .meta-key           { font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.8px; min-width: 44px; flex-shrink: 0; }
    .meta-val           { font-size: 13px; color: var(--dark); line-height: 1.5; font-family: 'Inter', sans-serif; }
    .meta-code          { font-family: monospace; font-size: 12px; color: var(--text-muted); background: var(--off-white); padding: 2px 7px; border-radius: 4px; border: 1px solid var(--light-gray); }
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">
    <div class="top-bar">
        <div>
            <div class="top-bar-title" id="topBarTitle">Content</div>
            <div class="top-bar-subtitle" id="topBarSubtitle">Generated WordPress HTML</div>
        </div>
    </div>
    <div class="content-area">

        <!-- Back button + status/date on the right, title below -->
        <div style="margin-bottom:20px;">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <a class="btn-back" id="backBtn" href="/content-groups">
                    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                    <span id="backLabel">Content Groups</span>
                </a>
                <div style="display:flex;gap:10px;align-items:center;">
                    <span id="viewBadge"></span>
                    <div id="viewDate" style="font-size:12px;color:var(--text-muted);font-family:'Inter',sans-serif;"></div>
                </div>
            </div>
            <div id="viewKeyword" style="font-size:22px;font-weight:700;color:var(--dark);margin-top:10px;line-height:1.3;letter-spacing:0.3px;text-transform:uppercase;"></div>
            <div id="viewMeta" class="word-count-meta" style="display:none;margin-top:4px;"></div>
        </div>

        <!-- Loading -->
        <div id="loadingState" class="loading-bar visible" style="margin-bottom:20px;">
            <div class="spinner"></div> Loading content...
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
                <button id="btnHtml" class="btn btn-secondary btn-view-active" onclick="setViewMode('html')">
                    <svg viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    HTML
                </button>
                <button id="btnClean" class="btn btn-secondary" onclick="setViewMode('clean')">
                    <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    Clean View
                </button>
                <button id="btnEdit" class="btn btn-secondary" onclick="setViewMode('edit')">
                    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit
                </button>
                <button id="btnSeo" class="btn btn-secondary" onclick="openSeoModal()">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                    SEO Score
                </button>
                <button id="btnDensity" class="btn btn-secondary" onclick="openDensityModal()">
                    <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    Keyword Density
                </button>
                <button id="btnVersions" class="btn btn-secondary" onclick="openVersionsModal()" style="display:none;">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Versions <span id="versionsCount"></span>
                </button>
                <button class="btn btn-yellow" onclick="duplicateContent()">
                    <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                    Duplicate
                </button>
                <button class="btn btn-green" onclick="regenerate()">
                    <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.95"/></svg>
                    Regenerate
                </button>

                <button id="btnPublish" class="btn btn-secondary" onclick="openPublishModal()" style="display:none;">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20"/></svg>
                    Publish
                </button>
                <button id="btnDelete" class="btn btn-red" onclick="deleteContent()">
                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                    Delete
                </button>
            </div>

            <!-- Meta panel (h1 / title / url) -->
            <div id="metaPanel" style="display:none;">
                <div class="card">
                    <div class="card-accent blue"></div>
                    <div class="card-body" style="padding:14px 20px;gap:10px;display:flex;flex-direction:column;">
                        <div class="meta-row" id="metaRowH1" style="display:none;">
                            <span class="meta-key">H1</span>
                            <span class="meta-val" id="metaH1"></span>
                        </div>
                        <div class="meta-row" id="metaRowTitle" style="display:none;">
                            <span class="meta-key">Title</span>
                            <span class="meta-val" id="metaTitle"></span>
                        </div>
                        <div class="meta-row" id="metaRowUrl" style="display:none;">
                            <span class="meta-key">URL</span>
                            <code class="meta-val meta-code" id="metaUrl"></code>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-accent red"></div>
                <div class="card-body">
                    <div id="copyHtmlRow" class="card-copy-row">
                        <button id="copyHtmlIcon" class="card-copy-btn" onclick="copyHtml()" title="Copy HTML">
                            <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                            Copy HTML
                        </button>
                    </div>
                    <div id="copyCleanRow" class="card-copy-row" style="display:none;">
                        <button id="copyCleanIcon" class="card-copy-btn" onclick="copyClean()" title="Copy text">
                            <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                            Copy Text
                        </button>
                    </div>
                    <textarea class="form-textarea" id="htmlOutput" readonly></textarea>
                    <div class="content-rendered" id="cleanOutput" style="display:none;"></div>
                    <textarea class="form-textarea" id="editOutput" style="display:none;"></textarea>

                    <div id="saveRow" style="display:none;align-items:center;gap:10px;margin-top:14px;">
                        <button id="saveBtn" class="btn btn-green" onclick="saveContent()">
                            <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Save
                        </button>
                        <button class="btn btn-secondary" onclick="setViewMode('html')">Cancel</button>
                        <span class="save-indicator" id="saveIndicator">
                            <svg style="width:14px;height:14px;stroke:#2a7a1a;fill:none;stroke-width:2;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                            Saved!
                        </span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<!-- Publish Modal -->
<div id="publishOverlay" class="rules-overlay" onclick="closePublishModal()"></div>
<div id="publishModal" class="density-modal" style="max-width:380px;">
    <div class="density-modal-header">
        <div class="rules-panel-title">Publish to WordPress</div>
        <button class="rules-panel-close" onclick="closePublishModal()">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div style="padding:0 20px 20px;">
        <p id="publishSiteLabel" style="font-size:13px;color:var(--text-muted);margin-bottom:20px;font-family:'Inter',sans-serif;"></p>
        <div id="publishPostUrl" style="display:none;margin-bottom:16px;">
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Already published at:</div>
            <a id="publishPostLink" href="#" target="_blank" rel="noopener" style="font-size:13px;word-break:break-all;color:var(--blue);"></a>
        </div>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-secondary" style="flex:1;" onclick="publishContent('draft')" id="btnPublishDraft">Save as Draft</button>
            <button class="btn btn-green"     style="flex:1;" onclick="publishContent('publish')" id="btnPublishLive">Publish Live</button>
        </div>
        <div id="publishResult" style="margin-top:14px;display:none;"></div>
    </div>
</div>

<!-- SEO Score Modal -->
<div id="seoOverlay" class="rules-overlay" onclick="closeSeoModal()"></div>
<div id="seoModal" class="density-modal" style="max-width:480px;">
    <div class="density-modal-header">
        <div class="rules-panel-title">SEO Score</div>
        <button class="rules-panel-close" onclick="closeSeoModal()">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div id="seoModalBody" style="padding:24px 28px;text-align:center;"></div>
</div>

<!-- Keyword Density Modal -->
<div id="densityOverlay" class="rules-overlay" onclick="closeDensityModal()"></div>
<div id="densityModal" class="density-modal">
    <div class="density-modal-header">
        <div class="rules-panel-title" id="densityModalTitle">Keyword Density</div>
        <button class="rules-panel-close" onclick="closeDensityModal()">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div id="densityContent" class="density-content"></div>
</div>

<!-- Version History Modal -->
<div id="versionsOverlay" class="rules-overlay" onclick="closeVersionsModal()"></div>
<div id="versionsModal" class="density-modal" style="max-width:760px;width:90vw;">
    <div class="density-modal-header">
        <div class="rules-panel-title">Version History</div>
        <button class="rules-panel-close" onclick="closeVersionsModal()">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div id="versionsContent" style="padding:0 20px 20px;max-height:75vh;overflow-y:auto;"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.js"></script>
<script src="/scripts/shared.js"></script>
<script src="/scripts/view-content.js"></script>
</body>
</html>
