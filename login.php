<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Creator — Sign In</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            --text-muted: #666666;
            --red-tint: #FFF0F0;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--off-white);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-container { width: 100%; max-width: 420px; }

        .auth-logo { text-align: center; margin-bottom: 32px; }
        .auth-logo .logo-icon { display: inline-flex; align-items: center; gap: 10px; }

        .auth-logo .logo-dot {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-logo .logo-dot svg { width: 20px; height: 20px; }

        .auth-logo .logo-text { font-size: 20px; font-weight: 700; color: var(--dark); letter-spacing: -0.3px; }
        .auth-logo .logo-text span { color: var(--red); }

        .auth-card {
            background: var(--card);
            border-radius: 16px;
            border: 1px solid var(--light-gray);
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }

        .auth-card-top-bar {
            height: 4px;
            background: linear-gradient(90deg, var(--red) 0%, var(--blue) 33%, var(--green) 66%, var(--yellow) 100%);
        }

        .auth-card-inner { padding: 36px; }

        .auth-tabs { display: flex; margin-bottom: 28px; border-bottom: 2px solid var(--light-gray); }

        .auth-tab {
            flex: 1;
            padding: 10px 0;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .auth-tab.active { color: var(--red); border-bottom-color: var(--red); }
        .auth-tab:hover:not(.active) { color: var(--dark); }

        .auth-form { display: none; flex-direction: column; gap: 16px; }
        .auth-form.active { display: flex; }

        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 13px; font-weight: 500; color: var(--dark); }

        .form-group input {
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
        .form-group input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(0,143,214,0.12); }

        .btn-submit {
            padding: 13px 20px;
            background: var(--red);
            color: white;
            border: none;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            margin-top: 4px;
        }
        .btn-submit:hover { background: #d42620; }
        .btn-submit:active { transform: scale(0.99); }
        .btn-submit:disabled { background: var(--light-gray); cursor: not-allowed; transform: none; }

        .auth-message { padding: 12px 14px; border-radius: 8px; font-size: 13px; font-weight: 500; display: none; }
        .auth-message.error   { display: block; background: var(--red-tint); color: var(--red); border: 1px solid rgba(238,45,36,0.2); }
        .auth-message.success { display: block; background: #F0FAF0; color: #2a7a1a; border: 1px solid rgba(107,189,69,0.3); }

        .password-hint { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-logo">
            <div class="logo-icon">
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
                <span class="logo-text">Content <span>Creator</span></span>
            </div>
        </div>

        <div class="auth-card">
            <div class="auth-card-top-bar"></div>
            <div class="auth-card-inner">
                <div class="auth-tabs">
                    <div class="auth-tab active" data-tab="login">Sign In</div>
                    <div class="auth-tab" data-tab="register">Create Account</div>
                </div>

                <div id="authMessage" class="auth-message"></div>

                <form class="auth-form active" id="loginForm">
                    <div class="form-group">
                        <label for="loginEmail">Email</label>
                        <input type="email" id="loginEmail" placeholder="you@example.com" required autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="loginPassword">Password</label>
                        <input type="password" id="loginPassword" placeholder="••••••••" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn-submit" id="loginBtn">Sign In</button>
                </form>

                <form class="auth-form" id="registerForm">
                    <div class="form-group">
                        <label for="registerEmail">Email</label>
                        <input type="email" id="registerEmail" placeholder="you@example.com" required autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="registerPassword">Password</label>
                        <input type="password" id="registerPassword" placeholder="••••••••" required autocomplete="new-password" minlength="6">
                        <span class="password-hint">Minimum 6 characters</span>
                    </div>
                    <div class="form-group">
                        <label for="registerConfirm">Confirm Password</label>
                        <input type="password" id="registerConfirm" placeholder="••••••••" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn-submit" id="registerBtn">Create Account</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.js"></script>
    <script src="/scripts/auth.js"></script>
</body>
</html>
