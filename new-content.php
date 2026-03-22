<?php $pageTitle = 'New Content | Content Creator'; ?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
    .mode-toggle       { display: inline-flex; border: 1px solid var(--light-gray); border-radius: 8px; overflow: hidden; margin-bottom: 20px; }
    .mode-toggle .btn  { border-radius: 0; border: none; padding: 7px 18px; font-size: 13px; }
    .mode-toggle .btn + .btn { border-left: 1px solid var(--light-gray); }

    .kw-chips          { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .kw-chip           { display: inline-flex; align-items: center; gap: 6px; background: var(--off-white); border: 1px solid var(--light-gray); border-radius: 20px; padding: 4px 10px 4px 13px; font-size: 12px; font-family: 'Inter', sans-serif; color: var(--dark); }
    .kw-chip-remove    { background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 18px; line-height: 1; padding: 0 0 1px; display: flex; align-items: center; }
    .kw-chip-remove:hover { color: var(--red); }

    .bulk-progress     { margin-top: 20px; display: flex; flex-direction: column; gap: 10px; }
    .bulk-item         { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: var(--off-white); border: 1px solid var(--light-gray); border-radius: 8px; gap: 12px; }
    .bulk-item-kw      { font-size: 13px; font-weight: 600; color: var(--dark); flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bulk-item-right   { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
    .bulk-item.running { border-color: var(--blue); background: #E8F4FB; }
    .bulk-item.done    { border-color: #b3dba0; background: #EEF8E7; }
    .bulk-item.failed  { border-color: #f5b0ac; background: var(--red-tint); }
    .bulk-note         { font-size: 12px; color: var(--text-muted); font-family: 'Inter', sans-serif; margin-top: 6px; }
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">
    <div class="top-bar">
        <div>
            <div class="top-bar-title">New Content</div>
            <div class="top-bar-subtitle">Generate SEO-optimized content in two phases</div>
        </div>
    </div>
    <div class="content-area">

        <div id="globalAlert" class="alert alert-error">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span id="globalAlertText"></span>
        </div>

        <div class="progress-steps">
            <div class="step"><div class="step-dot active" id="step1dot">1</div><div class="step-label active" id="step1label">Keyword</div></div>
            <div class="step-line" id="line1"></div>
            <div class="step"><div class="step-dot" id="step2dot">2</div><div class="step-label" id="step2label">Brief</div></div>
            <div class="step-line" id="line2"></div>
            <div class="step"><div class="step-dot" id="step3dot">3</div><div class="step-label" id="step3label">Content</div></div>
        </div>

        <!-- Phase 1 -->
        <div id="phase1Section">
            <div class="card">
                <div class="card-accent red"></div>
                <div class="card-header">
                    <div><div class="card-title">Phase 1 — Generate Brief</div><div class="card-subtitle">Select your content group and target keyword</div></div>
                    <span class="badge badge-red"><span class="badge-dot"></span>Step 1</span>
                </div>
                <div class="card-body">
                    <div id="noGroupsWarning" class="alert alert-error" style="display:none;margin-bottom:16px;">
                        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        No content groups yet. <a href="/content-groups" style="color:inherit;font-weight:700;">Create one first →</a>
                    </div>

                    <!-- Mode toggle -->
                    <div class="mode-toggle">
                        <button id="modeSingleBtn" class="btn btn-secondary btn-view-active" onclick="setMode('single')">Single</button>
                        <button id="modeBulkBtn"   class="btn btn-secondary" onclick="setMode('bulk')">Bulk</button>
                    </div>

                    <!-- Shared: group selector -->
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label" for="groupSelect">Content Group</label>
                        <select class="form-input" id="groupSelect" required>
                            <option value="">— Select a content group —</option>
                        </select>
                    </div>

                    <!-- Single mode -->
                    <div id="singleMode">
                        <form id="phase1Form">
                            <div class="form-group">
                                <label class="form-label" for="keywordInput">Target Keyword</label>
                                <input type="text" class="form-input" id="keywordInput" placeholder="e.g. how to wear a suit" required>
                            </div>
                            <button type="submit" class="btn btn-primary" id="generateBriefBtn">
                                <svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                                Generate Brief
                            </button>
                        </form>
                        <div class="loading-bar" id="phase1Loading">
                            <div class="spinner"></div>
                            Running SerpAPI + Perplexity analysis... this takes ~30 seconds.
                        </div>
                    </div>

                    <!-- Bulk mode -->
                    <div id="bulkMode" style="display:none;">
                        <div class="form-group">
                            <label class="form-label" for="bulkKeywordsInput">Keywords <span style="font-weight:400;color:var(--text-muted);">(one per line, max 10)</span></label>
                            <textarea class="form-textarea" id="bulkKeywordsInput" rows="6" placeholder="how to wear a suit&#10;smartwatch with a suit&#10;formal shoes for men"></textarea>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <button class="btn btn-secondary" onclick="parseBulkKeywords()">
                                <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                                Add Keywords
                            </button>
                            <button class="btn btn-primary" id="bulkGenerateBtn" onclick="runBulkGeneration()" style="display:none;">
                                <svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                                <span id="bulkGenerateLabel">Generate All</span>
                            </button>
                        </div>
                        <div id="bulkChips" class="kw-chips" style="display:none;"></div>
                        <div id="bulkNote" class="bulk-note" style="display:none;"></div>
                        <div id="bulkProgress" class="bulk-progress" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Phase 2 -->
        <div id="phase2Section" class="hidden">
            <div class="card">
                <div class="card-accent blue"></div>
                <div class="card-header">
                    <div><div class="card-title">Phase 1 Output — Editable Brief</div><div class="card-subtitle">Review and edit the content brief before proceeding</div></div>
                    <span class="badge badge-blue"><span class="badge-dot"></span>Step 2</span>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label" for="briefEditor">Content Brief</label>
                        <textarea class="form-textarea" id="briefEditor" rows="18"></textarea>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <button class="btn btn-secondary" id="saveBriefBtn" onclick="saveBrief()">
                            <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Save Brief
                        </button>
                        <button class="btn btn-blue" id="proceedToPhase2Btn">
                            <svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                            Confirm Brief & Write Content
                        </button>
                        <span class="save-indicator" id="briefSaveIndicator">
                            <svg style="width:14px;height:14px;stroke:#2a7a1a;fill:none;stroke-width:2;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                            Saved!
                        </span>
                    </div>
                    <div class="loading-bar" id="phase2Loading">
                        <div class="spinner"></div>
                        Drafting content using E-E-A-T framework... this may take a minute.
                    </div>
                </div>
            </div>
        </div>

        <!-- Phase 3 -->
        <div id="phase3Section" class="hidden">
            <div class="card">
                <div class="card-accent green"></div>
                <div class="card-header">
                    <div><div class="card-title">Phase 2 Output — WordPress HTML</div><div class="card-subtitle">Your SEO-optimized content is ready to copy into WordPress</div></div>
                    <span class="badge badge-green"><span class="badge-dot"></span>Complete</span>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <textarea class="form-textarea" id="htmlEditor" rows="25"></textarea>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <button class="btn btn-green" onclick="copyContent()">
                            <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                            Copy HTML
                        </button>
                        <button class="btn btn-secondary" onclick="resetToNew()">
                            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                            New Content
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.js"></script>
<script src="/scripts/shared.js"></script>
<script src="/scripts/new-content.js"></script>
</body>
</html>
