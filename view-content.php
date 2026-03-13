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
                <button id="btnDensity" class="btn btn-secondary" onclick="openDensityModal()">
                    <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    Keyword Density
                </button>
                <button id="btnCopy" class="btn btn-green" onclick="copyContent()">
                    <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                    Copy HTML
                </button>
                <button id="btnDelete" class="btn btn-red" onclick="deleteContent()">
                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                    Delete
                </button>
            </div>

            <div class="card">
                <div class="card-accent red"></div>
                <div class="card-body">
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

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.js"></script>
<script src="/scripts/shared.js"></script>
<script src="/scripts/view-content.js"></script>
</body>
</html>
