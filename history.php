<?php $pageTitle = 'History | Content Creator'; ?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">
    <div class="top-bar">
        <div>
            <div class="top-bar-title">History</div>
            <div class="top-bar-subtitle">Review all your past content generations</div>
        </div>
    </div>
    <div class="content-area">
        <div class="card">
            <div class="card-accent blue"></div>
            <div class="card-header"><div><div class="card-title">Generation History</div><div class="card-subtitle">All your past content generations</div></div></div>
            <div class="card-body">
                <div id="historyList">
                    <div class="history-empty">
                        <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Loading...
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.js"></script>
<script src="/scripts/shared.js"></script>
<script src="/scripts/history.js"></script>
</body>
</html>
