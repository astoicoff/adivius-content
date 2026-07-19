<?php $pageTitle = 'API Keys | Content Creator'; ?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
    .keys-section-title  { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.8px; margin: 22px 0 10px; }
    .keys-section-title:first-of-type { margin-top: 4px; }

    .key-card            { border: 1px solid var(--light-gray); border-radius: 10px; padding: 16px 18px; margin-bottom: 12px; background: var(--card); transition: border-color 0.15s; }
    .key-card:hover      { border-color: #c5c5c5; }

    .key-card-head       { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 4px; gap: 8px; flex-wrap: wrap; }
    .key-card-title      { font-size: 14px; font-weight: 600; color: var(--dark); display: flex; align-items: center; gap: 8px; }
    .key-card-desc       { font-size: 12px; color: var(--text-muted); margin-bottom: 12px; line-height: 1.5; }
    .key-card-desc a     { color: var(--blue); text-decoration: none; }
    .key-card-desc a:hover { text-decoration: underline; }

    .key-input-row       { display: flex; gap: 8px; align-items: stretch; flex-wrap: wrap; }
    .key-input-wrap      { flex: 1; min-width: 200px; position: relative; }
    .key-input-wrap input.form-input { padding-right: 40px; font-family: 'Inter', monospace; font-size: 12.5px; }
    .key-eye-btn         { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 6px; display: flex; align-items: center; color: var(--text-muted); border-radius: 4px; }
    .key-eye-btn:hover   { color: var(--dark); background: var(--off-white); }
    .key-eye-btn svg     { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

    .key-test-btn        { padding: 0 14px; font-size: 12px; font-weight: 600; flex-shrink: 0; min-height: 40px; }

    .key-status          { font-size: 12px; margin-top: 8px; display: flex; align-items: center; gap: 6px; min-height: 18px; font-family: 'Inter', sans-serif; }
    .key-status svg      { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
    .key-status.untested { color: var(--text-muted); }
    .key-status.testing  { color: var(--blue); }
    .key-status.ok       { color: #2a7a1a; }
    .key-status.fail     { color: var(--red); word-break: break-word; }

    .req-pill            { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; padding: 2px 7px; border-radius: 10px; font-family: 'Inter', sans-serif; }
    .req-pill.required   { background: var(--red-tint); color: var(--red); }
    .req-pill.optional   { background: var(--off-white); color: var(--text-muted); border: 1px solid var(--light-gray); }
    .req-pill.recommend  { background: #EDF7FF; color: var(--blue); }

    .keys-toolbar        { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 4px; flex-wrap: wrap; }
    .keys-toolbar-left   { font-size: 12px; color: var(--text-muted); }

    /* Provider logo dot */
    .key-logo            { width: 22px; height: 22px; border-radius: 5px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: white; flex-shrink: 0; font-family: 'Inter', sans-serif; }
    .key-logo.openai     { background: #10a37f; }
    .key-logo.claude     { background: #d97757; }
    .key-logo.gemini     { background: linear-gradient(135deg, #4285f4 0%, #ea4335 100%); }
    .key-logo.perplexity { background: #20b8cd; }
    .key-logo.serpapi    { background: #e8662b; }
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">
    <div class="top-bar">
        <div>
            <div class="top-bar-title">API Keys</div>
            <div class="top-bar-subtitle">Connect each provider used by the generation pipeline</div>
        </div>
    </div>
    <div class="content-area">
        <div class="card">
            <div class="card-accent yellow"></div>
            <div class="card-body">

                <div id="settingsAlert" class="alert" style="margin-bottom:16px;"></div>

                <div class="keys-toolbar">
                    <div class="keys-toolbar-left">Keys are stored encrypted in your account. Test calls each provider's cheapest endpoint.</div>
                    <div style="display:flex;gap:8px;">
                        <button class="btn btn-secondary" id="testAllBtn" onclick="testAll()" style="padding:8px 14px;font-size:12px;">
                            <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                            Test All
                        </button>
                        <button class="btn btn-primary" id="saveKeysBtn" onclick="saveSettings()" style="padding:8px 14px;font-size:12px;">
                            <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Save All
                        </button>
                        <span class="save-indicator" id="saveIndicator" style="margin-left:4px;">
                            <svg style="width:14px;height:14px;stroke:#2a7a1a;fill:none;stroke-width:2;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                            Saved!
                        </span>
                    </div>
                </div>

                <!-- ── Generation Models ─────────────────────────────────── -->
                <div class="keys-section-title">Generation Models</div>

                <!-- OpenAI -->
                <div class="key-card" id="card-openai">
                    <div class="key-card-head">
                        <div class="key-card-title">
                            <span class="key-logo openai">AI</span>
                            OpenAI
                            <span class="req-pill required">Required</span>
                        </div>
                    </div>
                    <div class="key-card-desc">
                        Powers Phase 1 brief generation and Phase 2 article writing (GPT-5.5).
                        <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">Get key →</a>
                    </div>
                    <div class="key-input-row">
                        <div class="key-input-wrap">
                            <input type="password" class="form-input" id="openaiKeyInput" placeholder="sk-..." autocomplete="off">
                            <button type="button" class="key-eye-btn" onclick="togglePw('openaiKeyInput', this)" title="Show/hide">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <button class="btn btn-secondary key-test-btn" onclick="testOne('openai')">Test</button>
                    </div>
                    <div class="key-status untested" id="status-openai">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
                        Not tested yet
                    </div>
                </div>

                <!-- Claude -->
                <div class="key-card" id="card-claude">
                    <div class="key-card-head">
                        <div class="key-card-title">
                            <span class="key-logo claude">A</span>
                            Anthropic Claude
                            <span class="req-pill optional">Optional</span>
                        </div>
                    </div>
                    <div class="key-card-desc">
                        Reserved for future Claude-powered generation paths.
                        <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">Get key →</a>
                    </div>
                    <div class="key-input-row">
                        <div class="key-input-wrap">
                            <input type="password" class="form-input" id="claudeKeyInput" placeholder="sk-ant-..." autocomplete="off">
                            <button type="button" class="key-eye-btn" onclick="togglePw('claudeKeyInput', this)" title="Show/hide">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <button class="btn btn-secondary key-test-btn" onclick="testOne('claude')">Test</button>
                    </div>
                    <div class="key-status untested" id="status-claude">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
                        Not tested yet
                    </div>
                </div>

                <!-- Gemini -->
                <div class="key-card" id="card-gemini">
                    <div class="key-card-head">
                        <div class="key-card-title">
                            <span class="key-logo gemini">G</span>
                            Google Gemini
                            <span class="req-pill optional">Optional</span>
                        </div>
                    </div>
                    <div class="key-card-desc">
                        Reserved for future Gemini-powered generation paths.
                        <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">Get key →</a>
                    </div>
                    <div class="key-input-row">
                        <div class="key-input-wrap">
                            <input type="password" class="form-input" id="geminiKeyInput" placeholder="AIza..." autocomplete="off">
                            <button type="button" class="key-eye-btn" onclick="togglePw('geminiKeyInput', this)" title="Show/hide">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <button class="btn btn-secondary key-test-btn" onclick="testOne('gemini')">Test</button>
                    </div>
                    <div class="key-status untested" id="status-gemini">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
                        Not tested yet
                    </div>
                </div>

                <!-- ── Research Tools ─────────────────────────────────── -->
                <div class="keys-section-title">Research Tools</div>

                <!-- Perplexity -->
                <div class="key-card" id="card-perplexity">
                    <div class="key-card-head">
                        <div class="key-card-title">
                            <span class="key-logo perplexity">P</span>
                            Perplexity
                            <span class="req-pill recommend">Recommended</span>
                        </div>
                    </div>
                    <div class="key-card-desc">
                        Deep competitor scraping (Phase 1) and E-E-A-T fact research (Phase 2).
                        Without it, both phases run with mock data.
                        <a href="https://www.perplexity.ai/account/api/keys" target="_blank" rel="noopener">Get key →</a>
                    </div>
                    <div class="key-input-row">
                        <div class="key-input-wrap">
                            <input type="password" class="form-input" id="perplexityKeyInput" placeholder="pplx-..." autocomplete="off">
                            <button type="button" class="key-eye-btn" onclick="togglePw('perplexityKeyInput', this)" title="Show/hide">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <button class="btn btn-secondary key-test-btn" onclick="testOne('perplexity')">Test</button>
                    </div>
                    <div class="key-status untested" id="status-perplexity">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
                        Not tested yet — testing costs ~$0.0001
                    </div>
                </div>

                <!-- SerpAPI -->
                <div class="key-card" id="card-serpapi">
                    <div class="key-card-head">
                        <div class="key-card-title">
                            <span class="key-logo serpapi">S</span>
                            SerpAPI
                            <span class="req-pill recommend">Recommended</span>
                        </div>
                    </div>
                    <div class="key-card-desc">
                        Fetches the true top-5 Google results for the target keyword.
                        Without it, Phase 1 falls back to mock URLs.
                        <a href="https://serpapi.com/manage-api-key" target="_blank" rel="noopener">Get key →</a>
                    </div>
                    <div class="key-input-row">
                        <div class="key-input-wrap">
                            <input type="password" class="form-input" id="serpapiKeyInput" placeholder="..." autocomplete="off">
                            <button type="button" class="key-eye-btn" onclick="togglePw('serpapiKeyInput', this)" title="Show/hide">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <button class="btn btn-secondary key-test-btn" onclick="testOne('serpapi')">Test</button>
                    </div>
                    <div class="key-status untested" id="status-serpapi">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
                        Not tested yet
                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.js"></script>
<script src="/scripts/shared.js"></script>
<script src="/scripts/api-keys.js"></script>
</body>
</html>
