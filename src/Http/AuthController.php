<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Http;

final class AuthController
{
    private readonly AuthSession $session;

    public function __construct(
        ?AuthSession $session = null,
        private readonly ?Authenticator $authenticator = null,
        private readonly ?AuthFailureMonitor $failureMonitor = null,
        private readonly ?UserRepository $users = null,
    ) {
        $this->session = $session ?? new AuthSession();
    }

    public function login(): void
    {
        if ($this->session->isAuthenticated()) {
            header('Location: /dashboard');
            exit;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$this->session->validCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
                http_response_code(403);
                return;
            }

            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

            if ($this->failureMonitor !== null && !$this->failureMonitor->allow($email, $ipAddress)) {
                http_response_code(429);
                return;
            }

            $userId = $this->authenticator?->authenticate($email, $password);
            if ($userId !== null) {
                $this->session->login($userId);
                header('Location: /dashboard');
                exit;
            }

            http_response_code(401);
            $this->failureMonitor?->recordFailure($email, $ipAddress);
        }

        $csrfToken = htmlspecialchars($this->session->csrfToken(), ENT_QUOTES, 'UTF-8');

        echo str_replace('{{CSRF_TOKEN}}', $csrfToken, <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ClearStats Login</title>
    <style>
        :root {
            --bg: #07111b;
            --bg-2: #0d1d2d;
            --panel: rgba(15, 23, 42, 0.86);
            --panel-border: rgba(148, 163, 184, 0.18);
            --text: #edf5ff;
            --muted: #a7bed6;
            --brand: #7ae7ff;
            --brand-2: #8a7dff;
            --accent: #7ef0c8;
            --danger: #ff8e8e;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0; min-height: 100%; background: radial-gradient(circle at top, rgba(122,231,255,0.17), transparent 30%), linear-gradient(180deg, var(--bg) 0%, var(--bg-2) 100%);
            color: var(--text); font-family: Inter, "Segoe UI", sans-serif;
        }
        body { display: grid; place-items: center; min-height: 100vh; }
        .shell {
            width: min(1080px, calc(100% - 32px));
            display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 28px; align-items: center;
        }
        .brand-panel, .login-panel {
            background: rgba(10, 18, 28, 0.7); border: 1px solid var(--panel-border); border-radius: 28px; box-shadow: 0 30px 70px rgba(2, 6, 23, 0.45);
            backdrop-filter: blur(12px);
        }
        .brand-panel {
            padding: 40px 38px; min-height: 560px; display: flex; flex-direction: column; justify-content: center;
            background-image: linear-gradient(180deg, rgba(14,26,40,0.82), rgba(9,15,26,0.9));
        }
        .brand {
            display: inline-flex; align-items: center; gap: 12px; font-size: 1.1rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;
        }
        .brand-mark {
            width: 28px; height: 28px; border-radius: 10px; background: linear-gradient(135deg, var(--brand), var(--brand-2));
            box-shadow: 0 0 26px rgba(122,231,255,0.52);
        }
        h1 {
            margin: 34px 0 18px; font-size: clamp(2.5rem, 5vw, 4rem); line-height: 0.95; letter-spacing: -0.06em;
        }
        .gradient { background: linear-gradient(135deg, #eafcff, #97ecff 35%, #8a7dff 70%, #d0c2ff); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .tagline {
            margin: 0 0 28px; color: var(--muted); line-height: 1.7; font-size: 1.05rem; max-width: 480px;
        }
        .metrics { display: grid; grid-template-columns: repeat(3, minmax(100px, 1fr)); gap: 14px; }
        .metric { padding: 16px 14px; border: 1px solid rgba(148,163,184,0.12); border-radius: 18px; background: rgba(148,163,184,0.04); }
        .metric .value { font-size: 1.8rem; font-weight: 800; letter-spacing: -0.05em; }
        .metric .label { font-size: 0.74rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; }
        .login-panel {
            padding: 32px 28px;
        }
        .login-panel h2 { margin: 0 0 8px; font-size: 1.7rem; }
        .login-panel p { margin: 0 0 28px; color: var(--muted); }
        form { display: grid; gap: 18px; }
        label { display: grid; gap: 8px; color: var(--muted); font-size: 0.92rem; }
        input {
            width: 100%; border-radius: 14px; border: 1px solid rgba(148,163,184,0.16); background: rgba(15,23,42,0.9); color: var(--text); padding: 14px 16px; font-size: 1rem;
        }
        input:focus { outline: none; border-color: rgba(122,231,255,0.72); box-shadow: 0 0 0 3px rgba(122,231,255,0.12); }
        .row { display: flex; justify-content: space-between; align-items: center; font-size: 0.88rem; color: var(--muted); }
        .checkbox { display: inline-flex; align-items: center; gap: 8px; }
        a { color: var(--brand); text-decoration: none; }
        button {
            border: 0; border-radius: 14px; font-weight: 700; font-size: 1rem; padding: 14px 18px; cursor: pointer; color: #07111b; background: linear-gradient(135deg, var(--brand), #d2f6ff 40%, var(--brand-2));
            box-shadow: 0 15px 30px rgba(122,231,255,0.22);
        }
        .other { border-top: 1px solid rgba(148,163,184,0.12); padding-top: 18px; text-align: center; color: var(--muted); font-size: 0.9rem; }
        @media (max-width: 860px) {
            .shell { grid-template-columns: 1fr; }
            .brand-panel { min-height: unset; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <section class="brand-panel">
            <div class="brand"><span class="brand-mark"></span> ClearStats</div>
            <h1>Analytics that <span class="gradient">respect privacy.</span></h1>
            <p class="tagline">Monitor your owned websites, protect user trust, and keep raw tracking data out of your stack with a self-hosted analytics platform designed for operators, not surveillance.</p>
            <div class="metrics">
                <div class="metric"><div class="value">31k</div><div class="label">Pageviews</div></div>
                <div class="metric"><div class="value">4.8%</div><div class="label">Bounce</div></div>
                <div class="metric"><div class="value">92</div><div class="label">Regions</div></div>
            </div>
        </section>

        <section class="login-panel">
            <h2>Sign in</h2>
            <p>Use your administrator account to review the portfolio.</p>
            <form method="post" action="/login">
                <input type="hidden" name="_csrf" value="{{CSRF_TOKEN}}">
                <label>
                    Email
                    <input type="email" name="email" placeholder="you@company.com">
                </label>
                <label>
                    Password
                    <input type="password" name="password" placeholder="Enter your password">
                </label>
                <div class="row">
                    <span class="checkbox"><input type="checkbox" name="remember" value="1"> Keep me signed in</span>
                    <a href="/forgot-password">Forgot password?</a>
                </div>
                <button type="submit">Log in</button>
            </form>
            <div class="other">Need access? Ask your account administrator.</div>
        </section>
    </div>
</body>
</html>
HTML);
    }

    public function logout(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !$this->session->validCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(403);
            return;
        }
        $this->session->logout();
        header('Location: /login');
        exit;
    }

    public function forgotPassword(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$this->session->validCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
                http_response_code(403);
                return;
            }
        }

        $csrfToken = htmlspecialchars($this->session->csrfToken(), ENT_QUOTES, 'UTF-8');
        $message = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
            ? 'If the account exists, contact your ClearStats administrator to issue a reset. No account details are disclosed here.'
            : 'Enter your account email. Password resets are issued by a ClearStats administrator until outbound mail delivery is configured.';

        echo str_replace(['{{CSRF_TOKEN}}', '{{MESSAGE}}'], [$csrfToken, htmlspecialchars($message, ENT_QUOTES, 'UTF-8')], <<<'HTML'
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Forgot password | ClearStats</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#07111b;color:#edf3ff;font-family:Inter,"Segoe UI",sans-serif}.card{width:min(460px,calc(100% - 40px));padding:28px;background:#0e1724;border:1px solid rgba(148,163,184,.18);border-radius:20px}h1{margin-top:0}p{color:#a7bed6;line-height:1.6}form{display:grid;gap:16px;margin-top:22px}label{display:grid;gap:8px;color:#a7bed6}input{padding:13px;border-radius:10px;border:1px solid rgba(148,163,184,.18);background:#0f172a;color:#edf3ff}.actions{display:flex;justify-content:flex-end;gap:10px}a,button{padding:11px 15px;border-radius:10px;border:1px solid rgba(148,163,184,.18);background:transparent;color:#edf3ff;text-decoration:none;cursor:pointer}button{background:#7ae7ff;color:#07111b;font-weight:700}</style></head>
<body><main class="card"><h1>Forgot password?</h1><p>{{MESSAGE}}</p><form method="post" action="/forgot-password"><input type="hidden" name="_csrf" value="{{CSRF_TOKEN}}"><label>Email address<input type="email" name="email" autocomplete="email" required></label><div class="actions"><a href="/login">Back to sign in</a><button type="submit">Request help</button></div></form></main></body></html>
HTML);
    }

    public function passwordChange(): void
    {
        $userId = $this->session->userId();
        if ($userId === null || $this->users === null) {
            header('Location: /login');
            exit;
        }

        $error = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$this->session->validCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
                http_response_code(403);
                return;
            }
            try {
                $this->users->changePassword(
                    $userId,
                    (string) ($_POST['current_password'] ?? ''),
                    (string) ($_POST['new_password'] ?? ''),
                );
                $this->session->login($userId);
                header('Location: /dashboard');
                exit;
            } catch (\Throwable $exception) {
                http_response_code(422);
                $error = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
            }
        }

        $csrfToken = htmlspecialchars($this->session->csrfToken(), ENT_QUOTES, 'UTF-8');
        echo str_replace(['{{CSRF_TOKEN}}', '{{ERROR}}'], [$csrfToken, $error], <<<'HTML'
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Change password | ClearStats</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#07111b;color:#edf3ff;font-family:Inter,"Segoe UI",sans-serif}.card{width:min(460px,calc(100% - 40px));padding:28px;background:#0e1724;border:1px solid rgba(148,163,184,.18);border-radius:20px}h1{margin-top:0}p{color:#a7bed6}form{display:grid;gap:16px}label{display:grid;gap:8px;color:#a7bed6}input{padding:13px;border-radius:10px;border:1px solid rgba(148,163,184,.18);background:#0f172a;color:#edf3ff}.error{color:#ff9b9b}.actions{display:flex;justify-content:flex-end;gap:10px}a,button{padding:11px 15px;border-radius:10px;border:1px solid rgba(148,163,184,.18);background:transparent;color:#edf3ff;text-decoration:none;cursor:pointer}button{background:#7ae7ff;color:#07111b;font-weight:700}</style></head><body><main class="card"><h1>Change password</h1><p>Use a new password with at least 12 characters.</p><p class="error">{{ERROR}}</p><form method="post" action="/password"><input type="hidden" name="_csrf" value="{{CSRF_TOKEN}}"><label>Current password<input type="password" name="current_password" required></label><label>New password<input type="password" name="new_password" minlength="12" required></label><div class="actions"><a href="/dashboard">Cancel</a><button type="submit">Change password</button></div></form></main></body></html>
HTML);
    }
}
