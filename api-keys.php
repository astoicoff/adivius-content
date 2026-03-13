<?php $pageTitle = 'API Keys | Content Creator'; ?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">
    <div class="top-bar">
        <div>
            <div class="top-bar-title">API Keys</div>
            <div class="top-bar-subtitle">Manage your API keys for the generation pipeline</div>
        </div>
    </div>
    <div class="content-area">
        <div class="card">
            <div class="card-accent yellow"></div>
            <div class="card-header"><div><div class="card-title">API Keys</div><div class="card-subtitle">Your keys are stored securely and never shared</div></div></div>
            <div class="card-body">
                <div id="settingsAlert" class="alert" style="margin-bottom:16px;"></div>

                <div class="settings-section-title">OpenAI</div>
                <p class="settings-section-desc">Required for content brief and article generation (GPT-5+).</p>
                <div class="form-group">
                    <label class="form-label">OpenAI API Key</label>
                    <div class="key-row"><input type="password" class="form-input" id="openaiKeyInput" placeholder="sk-..."></div>
                </div>

                <div class="settings-section-title" style="margin-top:8px;">Anthropic Claude</div>
                <p class="settings-section-desc">Optional — for Claude-powered generation.</p>
                <div class="form-group">
                    <label class="form-label">Claude API Key</label>
                    <div class="key-row"><input type="password" class="form-input" id="claudeKeyInput" placeholder="sk-ant-..."></div>
                </div>

                <div class="settings-section-title" style="margin-top:8px;">Google Gemini</div>
                <p class="settings-section-desc">Optional — for Gemini-powered generation.</p>
                <div class="form-group">
                    <label class="form-label">Gemini API Key</label>
                    <div class="key-row"><input type="password" class="form-input" id="geminiKeyInput" placeholder="AIza..."></div>
                </div>

                <div class="settings-section-title" style="margin-top:8px;">Perplexity</div>
                <p class="settings-section-desc">Used for deep competitor research and E-E-A-T data.</p>
                <div class="form-group">
                    <label class="form-label">Perplexity API Key</label>
                    <div class="key-row"><input type="password" class="form-input" id="perplexityKeyInput" placeholder="pplx-..."></div>
                </div>

                <div class="settings-section-title" style="margin-top:8px;">SerpAPI</div>
                <p class="settings-section-desc">Fetches top Google SERP results for the target keyword.</p>
                <div class="form-group">
                    <label class="form-label">SerpAPI Key</label>
                    <div class="key-row"><input type="password" class="form-input" id="serpapiKeyInput" placeholder="..."></div>
                </div>

                <div style="display:flex; align-items:center; gap:14px; margin-top:4px;">
                    <button class="btn btn-primary" id="saveKeysBtn" onclick="saveSettings()">
                        <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Save Keys
                    </button>
                    <span class="save-indicator" id="saveIndicator">
                        <svg style="width:14px;height:14px;stroke:#2a7a1a;fill:none;stroke-width:2;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        Saved!
                    </span>
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
