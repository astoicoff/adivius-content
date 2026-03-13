<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Content Creator' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #EE2D24;
            --blue: #008FD6;
            --green: #6BBD45;
            --yellow: #F7DF58;
            --dark: #1A1A1A;
            --off-white: #F9F9F9;
            --card: #FFFFFF;
            --light-gray: #DDDDDD;
            --text-muted: #777777;
            --red-tint: #FFF0F0;
            --sidebar-bg: #FFFFFF;
            --sidebar-width: 240px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--off-white);
            color: var(--dark);
            min-height: 100vh;
            display: flex;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--light-gray);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 100;
        }

        .sidebar-logo {
            padding: 22px 20px 18px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
            
        }

        .logo-dot {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .logo-dot svg { width: 18px; height: 18px; }

        .logo-text { font-size: 15px; font-weight: 700; color: var(--dark); line-height: 1.2; }
        .logo-text span { color: var(--red); }

        .sidebar-nav { padding: 16px 12px; flex: 1; overflow-y: auto; }

        .nav-section-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
            padding: 0 8px;
            margin-bottom: 6px;
            margin-top: 16px;
        }
        .nav-section-label:first-child { margin-top: 0; }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 10px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            transition: all 0.15s;
            user-select: none;
            text-decoration: none;
        }

        .nav-item:hover { background: var(--off-white); color: var(--dark); }
        .nav-item.active         { background: var(--red-tint); color: var(--red);   font-weight: 600; }
        .nav-item.green.active   { background: #F0FAF0;         color: var(--green); font-weight: 600; }
        .nav-item.blue.active    { background: #EDF7FF;         color: var(--blue);  font-weight: 600; }
        .nav-item.yellow.active  { background: #FEFCE8;         color: #7a6a00;      font-weight: 600; }

        .nav-item svg {
            width: 16px; height: 16px;
            flex-shrink: 0;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .nav-item .nav-badge {
            margin-left: auto;
            background: var(--yellow);
            color: var(--dark);
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
        }

        .sidebar-footer { padding: 14px 12px; border-top: 1px solid var(--light-gray); }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 8px;
            background: var(--off-white);
            margin-bottom: 8px;
        }

        .user-avatar {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: var(--blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }

        .user-email { font-size: 11px; color: var(--dark); font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .btn-logout {
            width: 100%;
            padding: 9px 12px;
            background: transparent;
            border: 1.5px solid var(--light-gray);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-logout:hover { border-color: var(--red); color: var(--red); background: var(--red-tint); }
        .btn-logout svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

        /* ── Main ── */
        .main { margin-left: var(--sidebar-width); flex: 1; min-height: 100vh; display: flex; flex-direction: column; }

        .top-bar {
            background: var(--card);
            border-bottom: 1px solid var(--light-gray);
            padding: 16px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .top-bar-title { font-size: 16px; font-weight: 700; color: var(--dark); }
        .top-bar-subtitle { font-size: 12px; color: var(--text-muted); font-weight: 400; margin-top: 1px; }

        .content-area { padding: 28px; flex: 1; }

        /* ── Cards ── */
        .card { background: var(--card); border: 1px solid var(--light-gray); border-radius: 12px; overflow: hidden; margin-bottom: 20px; }
        .card-accent { height: 4px; }
        .card-accent.red    { background: var(--red); }
        .card-accent.blue   { background: var(--blue); }
        .card-accent.green  { background: var(--green); }
        .card-accent.yellow { background: var(--yellow); }
        .card-header { padding: 20px 24px 0; display: flex; align-items: center; justify-content: space-between; }
        .card-title { font-size: 15px; font-weight: 600; color: var(--dark); }
        .card-subtitle { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
        .card-body { padding: 20px 24px 24px; }

        /* ── Badges ── */
        .badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-red    { background: var(--red-tint); color: var(--red); }
        .badge-blue   { background: #EDF7FF; color: var(--blue); }
        .badge-green  { background: #F0FAF0; color: #2a7a1a; }
        .badge-yellow { background: #FEFCE8; color: #7a6a00; }
        .badge-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        /* ── Form ── */
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 500; color: var(--dark); margin-bottom: 6px; }

        .form-input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--light-gray);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: var(--dark);
            background: var(--card);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .form-input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(0,143,214,0.1); }

        .form-textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--light-gray);
            border-radius: 8px;
            font-family: 'Inter', monospace;
            font-size: 12.5px;
            color: var(--dark);
            background: var(--card);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
            resize: vertical;
            line-height: 1.6;
        }
        .form-textarea:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(0,143,214,0.1); }

        /* ── Buttons ── */
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 20px; border: none; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s; text-decoration: none; }
        .btn svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .btn-primary   { background: var(--red);   color: white; }
        .btn-primary:hover { background: #d42620; }
        .btn-secondary { background: var(--off-white); color: var(--dark); border: 1.5px solid var(--light-gray); }
        .btn-secondary:hover { background: var(--light-gray); }
        .btn-blue  { background: var(--blue);  color: white; }
        .btn-blue:hover  { background: #007bbf; }
        .btn-green { background: var(--green); color: white; }
        .btn-green:hover { background: #5aa33c; }
        .btn-red  { background: var(--red);   color: white; }
        .btn-red:hover  { background: #d42620; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        /* ── Loading ── */
        .loading-bar { display: none; align-items: center; gap: 12px; padding: 14px 16px; background: #EDF7FF; border: 1px solid rgba(0,143,214,0.2); border-radius: 8px; font-size: 13px; color: var(--blue); font-weight: 500; margin-top: 16px; }
        .loading-bar.visible { display: flex; }
        .spinner { width: 18px; height: 18px; border: 2.5px solid rgba(0,143,214,0.2); border-top-color: var(--blue); border-radius: 50%; animation: spin 0.8s linear infinite; flex-shrink: 0; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Alert ── */
        .alert { display: none; padding: 13px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; margin-bottom: 20px; align-items: center;  }
        .alert.visible { display: flex; }
        .alert svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; margin-right: 10px; }
        .alert-error   { background: var(--red-tint); color: var(--red); border: 1px solid rgba(238,45,36,0.2); }
        .alert-success { background: #F0FAF0; color: #2a7a1a; border: 1px solid rgba(107,189,69,0.3); }

        /* ── Progress Steps ── */
        .progress-steps { display: flex; align-items: center; margin-bottom: 24px; }
        .step { display: flex; align-items: center; gap: 8px; }
        .step-dot { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; border: 2px solid var(--light-gray); background: var(--card); color: var(--text-muted); transition: all 0.3s; }
        .step-dot.active { border-color: var(--red); background: var(--red); color: white; }
        .step-dot.done   { border-color: var(--green); background: var(--green); color: white; }
        .step-label { font-size: 12px; font-weight: 500; color: var(--text-muted); }
        .step-label.active { color: var(--red); font-weight: 600; }
        .step-label.done   { color: var(--green); }
        .step-line { flex: 1; height: 2px; background: var(--light-gray); margin: 0 8px; transition: background 0.3s; }
        .step-line.done { background: var(--green); }

        /* ── History ── */
        .history-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-radius: 8px; border: 1px solid var(--light-gray); margin-bottom: 10px; background: var(--card); transition: border-color 0.15s; }
        .history-item:hover { border-color: var(--blue); }
        .history-keyword { font-size: 13px; font-weight: 600; color: var(--dark); margin-bottom: 3px; }
        .history-date { font-size: 11px; color: var(--text-muted); font-family: 'Inter', sans-serif; }
        .history-empty { text-align: center; padding: 48px 20px; color: var(--text-muted); font-size: 13px; }
        .history-empty svg { width: 40px; height: 40px; stroke: var(--light-gray); fill: none; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; display: block; margin: 0 auto 12px; }

        /* ── Settings ── */
        .settings-section-title { font-size: 13px; font-weight: 600; color: var(--dark); margin-bottom: 4px; }
        .settings-section-desc  { font-size: 12px; color: var(--text-muted); margin-bottom: 16px; line-height: 1.5; }
        .key-row { display: flex; gap: 8px; align-items: center; }
        .key-row .form-input { flex: 1; font-family: 'Inter', monospace; font-size: 12.5px; }
        .save-indicator { font-size: 11px; color: var(--green); font-weight: 500; display: none; align-items: center; gap: 4px; }
        .save-indicator.visible { display: flex; }

        /* ── Content Groups ── */
        .group-card { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-radius: 8px; border: 1px solid var(--light-gray); margin-bottom: 10px; background: var(--card); cursor: pointer; transition: border-color 0.15s; }
        .group-card:hover { border-color: var(--green); }
        .group-card-name { font-size: 13px; font-weight: 600; color: var(--dark); margin-bottom: 3px; }
        .group-card-date { font-size: 11px; color: var(--text-muted); font-family: 'Inter', sans-serif; }
        .group-card-arrow { color: var(--text-muted); }
        .group-card-arrow svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .no-groups-prompt { text-align: center; padding: 48px 20px; color: var(--text-muted); font-size: 13px; }
        .no-groups-prompt svg { width: 40px; height: 40px; stroke: var(--light-gray); fill: none; stroke-width: 1.5; display: block; margin: 0 auto 12px; }
        .groups-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .section-divider { border: none; border-top: 1px solid var(--light-gray); margin: 24px 0; }
        .group-edit-header { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
        .btn-back { background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 6px; font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 500; color: var(--text-muted); padding: 6px 0; transition: color 0.15s; text-decoration: none; }
        .btn-back:hover { color: var(--dark); }
        .btn-back svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .rules-label { font-size: 13px; font-weight: 600; color: var(--dark); margin-bottom: 4px; }
        .rules-desc  { font-size: 12px; color: var(--text-muted); margin-bottom: 8px; }

        /* ── Active view button ── */
        .btn-view-active { background: var(--blue) !important; color: white !important; border-color: var(--blue) !important; }

        /* ── Group Detail Header ── */
        .group-detail-header { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }
        .group-detail-title-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .group-detail-name-input { flex: 1; min-width: 180px; font-size: 20px; font-weight: 700; color: var(--dark); border: none; border-bottom: 2px solid transparent; background: transparent; font-family: 'Poppins', sans-serif; outline: none; padding: 2px 2px 4px; transition: border-color 0.2s; }
        .group-detail-name-input:focus { border-bottom-color: var(--green); }
        .group-detail-name-input::placeholder { color: var(--text-muted); font-weight: 500; font-size: 15px; }

        /* ── Rules Side Panel ── */
        .rules-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.35); z-index: 200; }
        .rules-overlay.visible { display: block; }
        .rules-panel { position: fixed; top: 0; right: 0; bottom: 0; width: 500px; max-width: 96vw; background: var(--card); border-left: 1px solid var(--light-gray); z-index: 201; display: flex; flex-direction: column; padding: 24px; transform: translateX(100%); transition: transform 0.25s ease; overflow-y: auto; }
        .rules-panel.open { transform: translateX(0); }
        .rules-panel-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
        .rules-panel-title { font-size: 15px; font-weight: 700; color: var(--dark); }
        .rules-panel-close { background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 4px; display: flex; align-items: center; transition: color 0.15s; }
        .rules-panel-close:hover { color: var(--dark); }
        .rules-panel-close svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; }

        /* ── Content Items ── */
        .content-item { border: 1px solid var(--light-gray); border-radius: 10px; margin-bottom: 10px; background: var(--card); overflow: hidden; transition: border-color 0.15s; }
        .content-item:hover { border-color: #aad4f0; }
        .content-item.expanded { border-color: var(--blue); }
        .content-item-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; cursor: pointer; user-select: none; gap: 12px; }
        .content-item-title { font-size: 13px; font-weight: 600; color: var(--dark); margin-bottom: 3px; }
        .content-item-date { font-size: 11px; color: var(--text-muted); font-family: 'Inter', sans-serif; }
        .content-item-body { padding: 16px; border-top: 1px solid var(--light-gray); display: none; }
        .content-item.expanded .content-item-body { display: block; }
        .content-view-bar { display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; align-items: center; }
        .content-rendered { line-height: 1.8; font-size: 14px; color: var(--dark); max-height: 600px; overflow-y: auto; padding: 20px; background: var(--off-white); border-radius: 8px; border: 1px solid var(--light-gray); }
        .content-rendered h1 { font-size: 22px; font-weight: 700; margin: 16px 0 8px; }
        .content-rendered h2 { font-size: 18px; font-weight: 700; margin: 14px 0 6px; }
        .content-rendered h3 { font-size: 15px; font-weight: 600; margin: 12px 0 4px; }
        .content-rendered p { margin-bottom: 10px; }
        .content-rendered ul, .content-rendered ol { margin: 8px 0 10px 24px; }
        .content-rendered li { margin-bottom: 4px; }

        /* ── Keyword Density Modal ── */
        .density-modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.95); background: var(--card); border: 1px solid var(--light-gray); border-radius: 16px; width: 720px; max-width: 95vw; max-height: 82vh; overflow: hidden; display: flex; flex-direction: column; z-index: 202; opacity: 0; pointer-events: none; transition: all 0.2s; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
        .density-modal.open { opacity: 1; pointer-events: auto; transform: translate(-50%, -50%) scale(1); }
        .density-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 16px; border-bottom: 1px solid var(--light-gray); flex-shrink: 0; }
        .density-content { overflow-y: auto; padding: 20px 24px; flex: 1; }
        .density-section-title { font-size: 11px; font-weight: 700; color: var(--text-muted); margin: 20px 0 8px; text-transform: uppercase; letter-spacing: 0.7px; }
        .density-section-title:first-child { margin-top: 0; }
        .density-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 4px; }
        .density-table th { text-align: left; padding: 8px 12px; background: var(--off-white); font-weight: 600; color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        .density-table td { padding: 8px 12px; border-top: 1px solid var(--light-gray); }
        .density-bar-wrap { display: flex; align-items: center; }
        .density-bar-fill { height: 6px; border-radius: 3px; background: var(--blue); min-width: 2px; }

        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main { margin-left: 0; } }
        .hidden { display: none !important; }
    </style>
</head>
