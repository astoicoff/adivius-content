<?php $pageTitle = 'Dashboard | Content Creator'; ?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
    .stat-grid      { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
    @media (max-width: 900px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 500px) { .stat-grid { grid-template-columns: 1fr; } }

    .stat-card      { background: var(--card); border-radius: 12px; border: 1px solid var(--light-gray); padding: 20px; display: flex; align-items: center; gap: 16px; }
    .stat-icon      { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .stat-icon svg  { width: 20px; height: 20px; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; fill: none; }
    .stat-icon.blue   { background: #E8F4FB; }    .stat-icon.blue svg   { stroke: var(--blue); }
    .stat-icon.yellow { background: #FEFAE6; }    .stat-icon.yellow svg { stroke: #c8a800; }
    .stat-icon.green  { background: #EEF8E7; }    .stat-icon.green svg  { stroke: var(--green); }
    .stat-icon.red    { background: var(--red-tint); } .stat-icon.red svg { stroke: var(--red); }
    .stat-value     { font-size: 26px; font-weight: 700; color: var(--dark); line-height: 1.1; letter-spacing: -0.5px; }
    .stat-label     { font-size: 11px; color: var(--text-muted); font-family: 'Inter', sans-serif; margin-top: 2px; }
    .stat-sub       { font-size: 11px; color: var(--text-muted); font-family: 'Inter', sans-serif; margin-top: 1px; }

    .activity-item  { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--light-gray); }
    .activity-item:last-child { border-bottom: none; }
    .activity-kw    { font-size: 13px; font-weight: 600; color: var(--dark); line-height: 1.3; }
    .activity-meta  { font-size: 11px; color: var(--text-muted); font-family: 'Inter', sans-serif; margin-top: 2px; }
    .activity-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }

    .quick-actions  { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px; }
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">
    <div class="top-bar">
        <div>
            <div class="top-bar-title">Dashboard</div>
            <div class="top-bar-subtitle">Welcome back — here's your overview</div>
        </div>
    </div>
    <div class="content-area">

        <!-- Stat cards -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </div>
                <div>
                    <div class="stat-value" id="statTotal">—</div>
                    <div class="stat-label">Total Generations</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <div>
                    <div class="stat-value" id="statMonth">—</div>
                    <div class="stat-label">This Month</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
                </div>
                <div>
                    <div class="stat-value" id="statGroups">—</div>
                    <div class="stat-label">Content Groups</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red">
                    <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <div>
                    <div class="stat-value" id="statTopGroup" style="font-size:16px;letter-spacing:0;">—</div>
                    <div class="stat-label" id="statTopGroupSub">Most active group</div>
                </div>
            </div>
        </div>

        <!-- Recent activity -->
        <div class="card">
            <div class="card-accent blue"></div>
            <div class="card-header">
                <div>
                    <div class="card-title">Recent Activity</div>
                    <div class="card-subtitle">Your last 5 generations</div>
                </div>
                <a href="/history" class="btn btn-secondary" style="font-size:12px;padding:6px 12px;">View All</a>
            </div>
            <div class="card-body" id="recentList">
                <div class="history-empty">
                    <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Loading...
                </div>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="quick-actions">
            <a href="/new-content" class="btn btn-primary">
                <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                New Content
            </a>
            <a href="/content-groups" class="btn btn-blue">
                <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
                Content Groups
            </a>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.js"></script>
<script src="/scripts/shared.js"></script>
<script src="/scripts/dashboard.js"></script>
</body>
</html>
