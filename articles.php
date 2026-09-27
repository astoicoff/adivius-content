<?php $pageTitle = 'Articles | Content Creator'; ?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
    /* Modelled on Nucleus's /tasks: a one-line quick-add bar, then one
       section per stage — a tinted pill + count above its own tight table. */
    .art-quickadd        { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; padding: 9px 14px; background: var(--card); border: 1px solid var(--light-gray); border-radius: 8px; }
    .art-quickadd svg    { width: 16px; height: 16px; stroke: var(--text-muted); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
    .art-quickadd input  { flex: 1; min-width: 0; border: none; outline: none; background: transparent; font-family: 'Poppins', sans-serif; font-size: 13px; color: var(--dark); }
    .art-quickadd input::placeholder { color: var(--text-muted); }

    .art-filter          { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 16px; padding: 4px 10px; border-radius: 6px; background: #EDF7FF; color: var(--blue); font-size: 12px; font-weight: 600; }
    .art-filter a        { color: inherit; text-decoration: none; font-size: 14px; line-height: 1; }
    .art-filter a:hover  { color: #006aa0; }

    .art-sections        { display: flex; flex-direction: column; gap: 28px; }
    .art-group-head      { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
    .art-pill            { display: inline-flex; align-items: center; gap: 7px; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; }
    .art-pill-sm         { padding: 2px 8px; font-size: 11px; border-radius: 20px; gap: 5px; white-space: nowrap; }
    .art-dot             { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
    .art-count           { font-size: 12px; color: var(--text-muted); font-family: 'Inter', sans-serif; }

    /* Stage palette — Adivius tokens only */
    .st-progress  { background: #EDF7FF;         color: #006aa0; }  .st-progress  .art-dot { background: var(--blue); }
    .st-brief     { background: #FEFCE8;         color: #7a6a00; }  .st-brief     .art-dot { background: var(--yellow); }
    .st-ready     { background: #F0FAF0;         color: #2a7a1a; }  .st-ready     .art-dot { background: var(--green); }
    .st-nucleus   { background: rgba(26,26,26,0.06); color: var(--dark); } .st-nucleus .art-dot { background: var(--dark); }
    .st-published { background: var(--green);    color: #fff; }     .st-published .art-dot { background: #fff; }
    .st-failed    { background: var(--red-tint); color: var(--red); } .st-failed   .art-dot { background: var(--red); }

    .art-table-wrap      { overflow-x: auto; background: var(--card); border: 1px solid var(--light-gray); border-radius: 8px; }
    /* Fixed layout + a shared <colgroup> so every stage section lines its
       columns up with the others, instead of each table sizing to its rows. */
    .art-table           { width: 100%; min-width: 760px; table-layout: fixed; border-collapse: collapse; font-size: 13px; }
    .art-table col.c-status  { width: 130px; }
    .art-table col.c-group   { width: 160px; }
    .art-table col.c-model   { width: 110px; }
    .art-table col.c-date    { width: 118px; }
    .art-table thead     { background: var(--off-white); border-bottom: 1px solid var(--light-gray); }
    .art-table th        { padding: 7px 8px; text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-muted); white-space: nowrap; }
    .art-table th:first-child, .art-table td:first-child { padding-left: 14px; }
    .art-table th.sortable { cursor: pointer; user-select: none; }
    .art-table th.sortable:hover { color: var(--dark); }
    .art-table th .arrow { margin-left: 3px; font-size: 10px; }
    .art-table td        { padding: 9px 8px; border-bottom: 1px solid var(--light-gray); vertical-align: middle; }
    .art-table tr:last-child td { border-bottom: none; }
    .art-table tbody tr  { transition: background 0.12s; }
    .art-table tbody tr:hover { background: var(--off-white); }

    .art-title           { display: block; max-width: 100%; font-size: 13px; font-weight: 600; color: var(--dark); text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: color 0.12s; }
    a.art-title:hover    { color: var(--red); }
    .art-sub             { margin-top: 2px; max-width: 100%; font-size: 11px; color: var(--text-muted); font-family: 'Inter', sans-serif; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .art-sub.error       { color: var(--red); }
    .art-chip            { display: inline-block; max-width: 150px; padding: 2px 8px; border-radius: 6px; background: var(--off-white); border: 1px solid var(--light-gray); color: var(--text-muted); font-size: 11.5px; font-weight: 500; text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; transition: color 0.12s; }
    a.art-chip:hover     { color: var(--red); }
    .art-muted           { font-size: 12px; color: var(--text-muted); font-family: 'Inter', sans-serif; white-space: nowrap; }

    .art-empty           { padding: 56px 20px; text-align: center; color: var(--text-muted); background: var(--card); border: 1px dashed var(--light-gray); border-radius: 10px; }
    .art-empty svg       { width: 36px; height: 36px; stroke: var(--light-gray); fill: none; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; margin-bottom: 10px; }
    .art-empty h3        { font-size: 15px; font-weight: 700; color: var(--dark); margin-bottom: 4px; }
    .art-empty p         { font-size: 13px; font-family: 'Inter', sans-serif; }

</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">
    <div class="top-bar">
        <div>
            <div class="top-bar-title">Articles</div>
            <div class="top-bar-subtitle">Every content generation you can access, grouped by stage</div>
        </div>
        <a href="/new-content" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            New Content
        </a>
    </div>
    <div class="content-area">

        <!-- Quick add: a keyword hands straight off to /new-content -->
        <form class="art-quickadd" id="quickAdd" onsubmit="return quickAdd(event)">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M12 18v-6M9 15h6"/></svg>
            <input id="quickAddInput" name="keyword" placeholder="New article — type a keyword and press Enter" aria-label="New article keyword" autocomplete="off">
            <button type="submit" class="btn btn-primary">Start</button>
        </form>

        <div id="filterChip"></div>

        <div id="loadingState" class="loading-bar visible" style="margin-top:0;">
            <div class="spinner"></div> Loading articles…
        </div>
        <div id="errorState" class="alert alert-error">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span id="errorText"></span>
        </div>

        <div id="sections" class="art-sections"></div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.js"></script>
<script src="/scripts/shared.js"></script>
<script src="/scripts/articles.js"></script>
</body>
</html>
