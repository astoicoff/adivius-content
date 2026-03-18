<?php $pageTitle = 'Content Groups | Content Creator'; ?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">
    <div class="top-bar">
        <div>
            <div class="top-bar-title" id="topBarTitle">Content Groups</div>
            <div class="top-bar-subtitle" id="topBarSubtitle">Manage your content group rules</div>
        </div>
    </div>
    <div class="content-area">

        <!-- Groups List -->
        <div id="viewGroupsList">
            <div class="groups-header">
                <span></span>
                <button class="btn btn-green" onclick="openGroupEdit(null)">
                    <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    New Content Group
                </button>
            </div>
            <div class="card">
                <div class="card-accent green"></div>
                <div class="card-header"><div><div class="card-title">Content Groups</div><div class="card-subtitle">Click a group to view and manage its contents</div></div></div>
                <div class="card-body">
                    <div id="groupsList"></div>
                </div>
            </div>
        </div>

        <!-- Group Detail -->
        <div id="viewGroupEdit" style="display:none;">

            <div class="group-detail-header">
                <button class="btn-back" onclick="showGroupsList()">
                    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                    Content Groups
                </button>
                <div class="group-detail-title-row">
                    <input type="text" class="group-detail-name-input" id="groupDetailName"
                           placeholder="Group name..." onblur="saveGroupName()">
                    <div style="display:flex;gap:8px;flex-shrink:0;">
                        <button class="btn btn-secondary" onclick="openRulesPanel('instructions')">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            Instructions Rules
                        </button>
                        <button class="btn btn-secondary" onclick="openRulesPanel('content')">
                            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Content Rules
                        </button>
                    </div>
                </div>
            </div>

            <div id="groupEditAlert" class="alert" style="margin-bottom:16px;"></div>

            <div id="groupContentList"></div>
        </div>

    </div>
</main>

<!-- Rules Side Panel -->
<div id="rulesOverlay" class="rules-overlay" onclick="closeRulesPanel()"></div>
<div id="rulesPanel" class="rules-panel">
    <div class="rules-panel-header">
        <div class="rules-panel-title" id="rulesPanelTitle">Instructions Rules</div>
        <button class="rules-panel-close" onclick="closeRulesPanel()">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <p class="rules-desc" id="rulesPanelDesc" style="margin-bottom:16px;"></p>
    <div id="groupNameRow" class="form-group" style="display:none;">
        <label class="form-label">Group Name</label>
        <input type="text" class="form-input" id="groupNameInput" placeholder="e.g. Blog Posts, Product Descriptions...">
    </div>
    <textarea class="form-textarea" id="rulesTextarea" rows="20" style="flex:1;resize:vertical;margin-bottom:16px;"></textarea>

    <!-- WordPress fields (shown instead of textarea in wordpress mode) -->
    <div id="wpFieldsSection" style="display:none;flex-direction:column;gap:14px;margin-bottom:16px;">
        <div class="form-group">
            <label class="form-label">Site URL</label>
            <input type="url" class="form-input" id="wpSiteUrl" placeholder="https://yoursite.com">
        </div>
        <div class="form-group">
            <label class="form-label">Username</label>
            <input type="text" class="form-input" id="wpUsername" placeholder="WordPress username">
        </div>
        <div class="form-group">
            <label class="form-label">Application Password</label>
            <input type="password" class="form-input" id="wpAppPassword" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx">
            <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Generate in WordPress → Users → Profile → Application Passwords</div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="btn btn-green" onclick="saveRules()">
            <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save Rules
        </button>
        <span class="save-indicator" id="rulesSaveIndicator">
            <svg style="width:14px;height:14px;stroke:#2a7a1a;fill:none;stroke-width:2;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            Saved!
        </span>
    </div>
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

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.js"></script>
<script src="/scripts/shared.js"></script>
<script src="/scripts/content-groups.js"></script>
</body>
</html>
