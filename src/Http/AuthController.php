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
</head>
<body>
    <main class="auth">
        <section class="panel">
            <h1 class="panel-title">Analytics that <em>respect privacy.</em></h1>
            <p class="panel-lede">Monitor your owned websites, protect user trust, and keep raw tracking data out of your stack with a self-hosted analytics platform designed for operators, not surveillance.</p>
            <div class="grid grid-3">
                <div class="stat"><span class="stat-value">31k</span><span class="stat-label">Pageviews</span></div>
                <div class="stat"><span class="stat-value">4.8%</span><span class="stat-label">Bounce</span></div>
                <div class="stat"><span class="stat-value">92</span><span class="stat-label">Regions</span></div>
            </div>
        </section>

        <section class="card auth-form">
            <h2>Sign in</h2>
            <p>Use your administrator account to review the portfolio.</p>
            <form class="form" method="post" action="/login">
                <input type="hidden" name="_csrf" value="{{CSRF_TOKEN}}">
                <label><span>Email</span><input type="email" name="email" autocomplete="username" placeholder="you@company.com"></label>
                <label><span>Password</span><input type="password" name="password" autocomplete="current-password" placeholder="Enter your password"></label>
                <div class="form-meta">
                    <label class="checkbox"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
                    <a href="/forgot-password">Forgot password?</a>
                </div>
                <button class="btn btn-primary btn-block" type="submit">Log in</button>
            </form>
            <p class="auth-foot">Need access? Ask your account administrator.</p>
        </section>
    </main>
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
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot password | ClearStats</title>
</head>
<body>
    <main class="page">
        <section class="card card-narrow">
            <h1 class="page-title">Forgot password?</h1>
            <p class="card-lede">{{MESSAGE}}</p>
            <form class="form" method="post" action="/forgot-password">
                <input type="hidden" name="_csrf" value="{{CSRF_TOKEN}}">
                <label><span>Email address</span><input type="email" name="email" autocomplete="email" required></label>
                <div class="form-actions">
                    <a class="btn" href="/login">Back to sign in</a>
                    <button class="btn btn-primary" type="submit">Request help</button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change password | ClearStats</title>
</head>
<body>
    <main class="page">
        <section class="card card-narrow">
            <h1 class="page-title">Change password</h1>
            <p class="card-lede">Use a new password with at least 12 characters.</p>
            <form class="form" method="post" action="/password">
                <input type="hidden" name="_csrf" value="{{CSRF_TOKEN}}">
                <p class="form-error">{{ERROR}}</p>
                <label><span>Current password</span><input type="password" name="current_password" autocomplete="current-password" required></label>
                <label><span>New password</span><input type="password" name="new_password" autocomplete="new-password" minlength="12" required></label>
                <div class="form-actions">
                    <a class="btn" href="/dashboard">Cancel</a>
                    <button class="btn btn-primary" type="submit">Change password</button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
HTML);
    }
}
