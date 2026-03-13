<?php $pageTitle = 'New Content | Content Creator'; ?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
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
                    <form id="phase1Form">
                        <div class="form-group">
                            <label class="form-label" for="groupSelect">Content Group</label>
                            <select class="form-input" id="groupSelect" required>
                                <option value="">— Select a content group —</option>
                            </select>
                        </div>
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
                    <button class="btn btn-blue" id="proceedToPhase2Btn">
                        <svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                        Confirm Brief & Write Content
                    </button>
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
