<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-dot">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <defs><clipPath id="logo-frame"><rect width="24" height="24" rx="3"/></clipPath></defs>
                <g clip-path="url(#logo-frame)">
                    <rect width="24" height="24" fill="white"/>
                    <rect width="24" height="6" fill="#EE2D24"/>
                    <rect y="6" width="7" height="18" fill="#008FD6"/>
                    <rect x="10" y="9"  width="12" height="2.5" rx="1" fill="#6BBD45"/>
                    <rect x="10" y="14" width="9"  height="2.5" rx="1" fill="#F7DF58"/>
                </g>
                <rect width="24" height="24" rx="3" fill="none" stroke="#DDDDDD" stroke-width="1"/>
            </svg>
        </div>
        <div>
            <div class="logo-text">Content <span>Creator</span></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Workspace</div>
        <a class="nav-item" href="/dashboard" id="navDashboard">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Dashboard
        </a>
        <a class="nav-item" href="/new-content" id="navGenerate">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            New Content
        </a>
        <a class="nav-item green" href="/content-groups" id="navGroups">
            <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
            Content Groups
        </a>
        <a class="nav-item blue" href="/history" id="navHistory">
            <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linejoin="round"/></svg>
            History
            <span class="nav-badge" id="historyBadge" style="display:none"></span>
        </a>
        <div class="nav-section-label">Account</div>
        <a class="nav-item yellow" href="/api-keys" id="navSettings">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
            API Keys
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar" id="userAvatar">?</div>
            <div class="user-email" id="userEmail">Loading...</div>
        </div>
        <button class="btn-logout" onclick="handleLogout()">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            Sign Out
        </button>
    </div>
</aside>
