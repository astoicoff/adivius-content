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
                <div style="display:flex;align-items:center;gap:12px;">
                    <button class="btn-back" onclick="showGroupsList()">
                        <svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                        Content Groups
                    </button>
                    <input type="text" class="group-detail-name-input" id="groupDetailName"
                           placeholder="Group name..." onblur="saveGroupName()">
                </div>
                <div id="groupSettingsBtns" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <button class="btn btn-secondary" onclick="openRulesPanel('instructions')">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Instructions Rules
                    </button>
                    <button class="btn btn-secondary" onclick="openRulesPanel('content')">
                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Content Rules
                    </button>
                    <button class="btn btn-secondary" onclick="openRulesPanel('wordpress')">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20"/></svg>
                        WordPress
                    </button>
                    <button class="btn btn-secondary" onclick="openRulesPanel('webhook')">
                        <svg viewBox="0 0 24 24"><polyline points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        Webhook
                    </button>
                    <button id="btnDeleteGroup" class="btn btn-red" onclick="deleteGroup()" style="display:none;">
                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                        Delete Group
                    </button>
                    <button id="btnMembers" class="btn btn-secondary" onclick="openMembersPanel()" style="display:none;">
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                        Members
                    </button>
                    <button class="btn btn-nucleus" onclick="openRulesPanel('nucleus')">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
                        Nucleus
                    </button>
                </div>
            </div>

            <div id="groupEditAlert" class="alert" style="margin-bottom:16px;"></div>

            <!-- Analytics chart (group detail) -->
            <div id="analyticsCard" style="display:none;margin-bottom:16px;">
                <div class="card">
                    <div class="card-accent blue"></div>
                    <div class="card-header" style="padding-bottom:0;">
                        <div><div class="card-title">Content Analytics</div></div>
                        <div style="display:flex;gap:6px;">
                            <button class="btn btn-secondary btn-view-active" id="chartWeekBtn" onclick="setChartMode('week')" style="padding:5px 12px;font-size:12px;">Weeks</button>
                            <button class="btn btn-secondary" id="chartMonthBtn" onclick="setChartMode('month')" style="padding:5px 12px;font-size:12px;">Months</button>
                        </div>
                    </div>
                    <div class="card-body" style="padding-top:10px;">
                        <canvas id="analyticsChart" height="80"></canvas>
                    </div>
                </div>
            </div>
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

    <!-- Nucleus fields (shown instead of textarea in nucleus mode) -->
    <div id="nucleusFieldsSection" style="display:none;flex-direction:column;gap:14px;margin-bottom:16px;">
        <div class="form-group">
            <label class="form-label" style="margin-bottom:2px;">Nucleus site</label>
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px;line-height:1.5;">
                The Nucleus site this group is bound to. Content is published here on <strong>Send to Nucleus</strong>, and Nucleus can push briefs into this group from here. The site's parent client is set automatically.
            </div>
            <select class="form-input" id="nucleusSiteSelect" onchange="onNucleusSiteChange()">
                <option value="">— Not linked —</option>
            </select>
            <div id="nucleusSiteWarning" style="display:none;margin-top:8px;padding:8px 10px;background:#FEFCE8;border:1px solid rgba(247,223,88,0.5);border-radius:6px;font-size:11.5px;color:#7a6a00;line-height:1.5;">
                <strong>Heads up:</strong> this is a personal site (no client on Nucleus). Nucleus's current contract requires client_id to publish, so <em>Send to Nucleus</em> won't work for this site yet. Briefs pushed in still land in this group; publishing back is pending a Nucleus contract change.
            </div>
        </div>
    </div>

    <!-- Webhook fields (shown instead of textarea in webhook mode) -->
    <div id="webhookFieldsSection" style="display:none;flex-direction:column;gap:14px;margin-bottom:16px;">
        <div class="form-group">
            <label class="form-label">Webhook URL</label>
            <input type="url" class="form-input" id="webhookUrl" placeholder="https://hooks.zapier.com/...">
            <div style="font-size:11px;color:var(--text-muted);margin-top:4px;line-height:1.5;">
                Fires <code>POST</code> with <code>event</code>, <code>generation_id</code>, <code>keyword</code>, <code>meta</code>, <code>content</code>, <code>html</code> when a generation completes. Retries up to 3× on failure.
            </div>
        </div>
        <div class="form-group">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                <label class="form-label" style="margin:0;">Custom Headers <span style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
                <button type="button" class="btn btn-secondary" style="padding:4px 10px;font-size:12px;" onclick="addWebhookHeaderRow()">
                    <svg viewBox="0 0 24 24" style="width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add header
                </button>
            </div>
            <div id="webhookHeadersList" style="display:flex;flex-direction:column;gap:6px;"></div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:6px;line-height:1.5;">
                Sent with every POST. Use for <code>Authorization</code>, <code>X-API-Key</code>, etc. Names accept <code>A-Z</code>, <code>0-9</code>, <code>-</code>, <code>_</code>. <code>Content-Type</code> and <code>User-Agent</code> are managed automatically.
            </div>
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

<!-- Members Panel -->
<div id="membersOverlay" class="rules-overlay" onclick="closeMembersPanel()"></div>
<div id="membersPanel" class="rules-panel" style="max-width:420px;">
    <div class="rules-panel-header">
        <div class="rules-panel-title">Team Members</div>
        <button class="rules-panel-close" onclick="closeMembersPanel()">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div id="membersPanelBody" style="flex:1;overflow-y:auto;">
        <div class="loading-bar visible"><div class="spinner"></div> Loading…</div>
    </div>
    <div style="padding-top:12px;border-top:1px solid var(--light-gray);">
        <button class="btn btn-green" onclick="openInviteForm()" style="width:100%;">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Invite Someone
        </button>
    </div>
</div>

<!-- Invite Modal -->
<div id="inviteOverlay" class="rules-overlay" onclick="closeInviteModal()"></div>
<div id="inviteModal" class="density-modal" style="max-width:400px;">
    <div class="density-modal-header">
        <div class="rules-panel-title">Invite Team Member</div>
        <button class="rules-panel-close" onclick="closeInviteModal()">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div style="padding:0 20px 20px;display:flex;flex-direction:column;gap:14px;">
        <div class="form-group">
            <label class="form-label">Email address</label>
            <input type="email" class="form-input" id="inviteEmail" placeholder="colleague@company.com">
        </div>
        <div class="form-group">
            <label class="form-label">Role</label>
            <select class="form-input" id="inviteRole">
                <option value="moderator">Moderator — can generate &amp; edit content</option>
                <option value="viewer">Viewer — read-only access</option>
            </select>
        </div>
        <button class="btn btn-green" id="inviteBtn" onclick="sendInvite()">
            <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Generate Invite Link
        </button>
        <div id="inviteResult" style="display:none;"></div>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script src="/scripts/content-groups.js"></script>
</body>
</html>
