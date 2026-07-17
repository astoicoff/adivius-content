<?php $pageTitle = 'Images | Content Creator'; ?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
    .gallery-filter-bar  { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
    .gallery-chip        { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; border: 1px solid var(--light-gray); background: var(--card); font-size: 12px; font-weight: 600; color: var(--text-muted); cursor: pointer; white-space: nowrap; transition: background 0.12s, color 0.12s, border-color 0.12s; }
    .gallery-chip:hover  { background: var(--off-white); color: var(--dark); }
    .gallery-chip.active { background: var(--dark); color: #fff; border-color: var(--dark); }
    .gallery-chip-dot    { width: 7px; height: 7px; border-radius: 50%; background: currentColor; opacity: 0.5; }

    .gallery-status-sel  { padding: 6px 12px; border-radius: 20px; border: 1px solid var(--light-gray); background: var(--card); font-size: 12px; font-weight: 600; color: var(--text-muted); cursor: pointer; margin-left: auto; }

    .gallery-meta        { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; font-size: 12px; color: var(--text-muted); font-family: 'Inter', sans-serif; }
    .gallery-count       { font-weight: 700; color: var(--dark); }

    .gallery-grid        { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }

    .gallery-card        { text-decoration: none; color: inherit; display: flex; flex-direction: column; border-radius: 10px; overflow: hidden; border: 1px solid var(--light-gray); background: var(--card); transition: box-shadow 0.15s, transform 0.15s; }
    .gallery-card:hover  { box-shadow: 0 4px 16px rgba(0,0,0,0.10); transform: translateY(-2px); }

    .gallery-thumb            { position: relative; width: 100%; overflow: hidden; background: var(--off-white); }
    .gallery-thumb.landscape  { aspect-ratio: 16/9; }
    .gallery-thumb.square     { aspect-ratio: 1; }
    .gallery-thumb.portrait   { aspect-ratio: 9/16; }
    .gallery-thumb img        { width: 100%; height: 100%; object-fit: cover; display: block; }

    .gallery-placeholder      { width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; }
    .gallery-placeholder svg  { width: 28px; height: 28px; stroke: var(--text-muted); fill: none; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }
    .gallery-placeholder span { font-size: 11px; color: var(--text-muted); font-family: 'Inter', sans-serif; }

    .gallery-shimmer     { width: 100%; height: 100%; background: linear-gradient(90deg, var(--off-white) 25%, var(--light-gray) 50%, var(--off-white) 75%); background-size: 200% 100%; animation: gshimmer 1.5s infinite; }
    @keyframes gshimmer  { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

    .gallery-info        { padding: 10px 12px; display: flex; flex-direction: column; gap: 4px; }
    .gallery-keyword     { font-size: 12px; font-weight: 700; color: var(--dark); text-transform: uppercase; letter-spacing: 0.4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .gallery-footer      { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
    .gallery-date        { font-size: 11px; color: var(--text-muted); font-family: 'Inter', sans-serif; }

    .gallery-empty       { padding: 60px 20px; text-align: center; color: var(--text-muted); font-family: 'Inter', sans-serif; }
    .gallery-empty svg   { width: 40px; height: 40px; stroke: var(--light-gray); fill: none; stroke-width: 1.5; margin-bottom: 14px; }
    .gallery-empty h3    { font-size: 16px; font-weight: 700; color: var(--dark); margin: 0 0 6px; }
    .gallery-empty p     { font-size: 13px; margin: 0 0 18px; }
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">
    <div class="top-bar">
        <div>
            <div class="top-bar-title">Images</div>
            <div class="top-bar-subtitle">All AI-generated images across your content groups</div>
        </div>
        <a href="/new-image" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            New Image
        </a>
    </div>
    <div class="content-area">

        <!-- Filters -->
        <div class="gallery-filter-bar" id="filterBar">
            <button class="gallery-chip active" id="chipAll" onclick="setGroupFilter(null)">All</button>
            <!-- group chips injected by JS -->
            <select class="gallery-status-sel" id="statusFilter" onchange="applyFilters()">
                <option value="">All Statuses</option>
                <option value="completed">Completed</option>
                <option value="pending">Pending</option>
                <option value="generating_prompt">Generating Prompt</option>
                <option value="generating_image">Generating Image</option>
                <option value="failed">Failed</option>
            </select>
        </div>

        <!-- Loading -->
        <div id="loadingState" class="loading-bar visible" style="margin-bottom:20px;">
            <div class="spinner"></div> Loading images…
        </div>

        <!-- Error -->
        <div id="errorState" class="alert alert-error" style="display:none;">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span id="errorText"></span>
        </div>

        <!-- Stats + grid -->
        <div id="contentArea" style="display:none;">
            <div class="gallery-meta">
                <span id="galleryCount" class="gallery-count">0</span>
                <span>images</span>
            </div>
            <div class="gallery-grid" id="galleryGrid"></div>
            <div class="gallery-empty" id="emptyState" style="display:none;">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                <h3>No images found</h3>
                <p>Try a different filter or generate your first image.</p>
                <a href="/new-image" class="btn btn-primary">Generate Image</a>
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.js"></script>
<script src="/scripts/shared.js"></script>
<script src="/scripts/images.js"></script>
</body>
</html>
