<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Http;

final class UserController
{
    private readonly AuthSession $session;

    public function __construct(
        private readonly ?UserRepository $repository = null,
        ?AuthSession $session = null,
    ) {
        $this->session = $session ?? new AuthSession();
    }

    public function index(): void
    {
        $userId = $this->session->userId();
        if ($userId === null || $this->repository === null) {
            header('Location: /login');
            exit;
        }

        $userRows = '';
        foreach ($this->repository->allForAdmin($userId) as $user) {
            $role = strtolower((string) ($user['role'] ?? 'viewer'));
            $roleClass = in_array($role, ['admin', 'editor', 'viewer'], true) ? $role : 'viewer';
            $userRows .= sprintf(
                '<tr><td>%s</td><td class="muted">%s</td><td>%s</td><td>%s</td><td><span class="role %s">%s</span></td></tr>',
                htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8'),
                $role === 'admin' ? 'All sites' : 'Assigned sites',
                htmlspecialchars((string) $user['site_count'], ENT_QUOTES, 'UTF-8'),
                $roleClass,
                htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8'),
            );
        }

        if ($userRows === '') {
            $userRows = '<tr><td colspan="5" class="muted">Only administrators can view user access.</td></tr>';
        }

        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ClearStats Users</title>
    <style>
        :root { --bg:#07111b; --bg-2:#0d1d2d; --panel:rgba(15,23,42,0.82); --text:#edf3ff; --muted:#9bb0c8; --brand:#7ae7ff; --brand-2:#8a7dff; --accent:#7ef0c8; --line:rgba(148,163,184,0.14); }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; background: linear-gradient(180deg, var(--bg) 0%, var(--bg-2) 100%); color: var(--text); font-family: Inter, "Segoe UI", sans-serif; }
        body { padding: 30px; }
        .shell { width: min(1100px, 100%); margin: 0 auto; }
        .topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom: 22px; }
        .title { margin:0; font-size:2.2rem; letter-spacing:-0.05em; }
        .button { display:inline-flex; align-items:center; justify-content:center; background: linear-gradient(135deg, rgba(122,231,255,0.22), rgba(138,125,255,0.28)); border:1px solid rgba(122,231,255,0.36); color:var(--text); border-radius:12px; padding:11px 16px; text-decoration:none; }
        .card { background: rgba(14,23,36,0.82); border:1px solid var(--line); border-radius: 22px; padding: 20px; }
        .table { width:100%; border-collapse: collapse; }
        .table th, .table td { text-align:left; padding: 14px 10px; border-bottom: 1px solid rgba(148,163,184,0.12); }
        .table th { color: var(--muted); font-weight: 600; }
        .role { display:inline-flex; padding:6px 10px; border-radius: 999px; font-size: 0.72rem; letter-spacing:0.08em; text-transform: uppercase; }
        .role.admin { background: rgba(122,231,255,0.12); color: var(--brand); }
        .role.editor { background: rgba(126,240,200,0.1); color: var(--accent); }
        .role.viewer { background: rgba(255,191,123,0.08); color: #ffd79f; }
        .muted { color: var(--muted); }
        @media (max-width: 700px) { .table { display:block; overflow-x:auto; } }
    </style>
</head>
<body>
    <div class="shell">
        <div class="topbar">
            <h1 class="title">Users</h1>
            <div>
                <a class="button" href="/users/assign">Manage access</a>
                <a class="button" href="/users/new">Invite user</a>
            </div>
        </div>
        <div class="card">
            <table class="table">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Access</th><th>Sites</th><th>Role</th></tr>
                </thead>
                <tbody>{{USER_ROWS}}</tbody>
            </table>
        </div>
    </div>
</body>
</html>
HTML;

        echo str_replace('{{USER_ROWS}}', $userRows, $html);
    }

    public function create(): void
    {
        $userId = $this->session->userId();
        if ($userId === null || $this->repository === null) {
            header('Location: /login');
            exit;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$this->session->validCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
                http_response_code(403);
                return;
            }

            try {
                $this->repository->create(
                    $userId,
                    (string) ($_POST['email'] ?? ''),
                    (string) ($_POST['password'] ?? ''),
                    (string) ($_POST['role'] ?? 'viewer'),
                );
                header('Location: /users');
                exit;
            } catch (\Throwable $exception) {
                http_response_code(422);
            }
        }

        $csrfToken = htmlspecialchars($this->session->csrfToken(), ENT_QUOTES, 'UTF-8');

        echo str_replace('{{CSRF_TOKEN}}', $csrfToken, <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invite user | ClearStats</title>
    <style>
        :root { --bg:#07111b; --bg-2:#0d1d2d; --panel:rgba(15,23,42,0.82); --text:#edf3ff; --muted:#9bb0c8; --brand:#7ae7ff; --brand-2:#8a7dff; --line:rgba(148,163,184,0.14); }
        * { box-sizing:border-box; }
        html, body { margin:0; min-height:100%; background:linear-gradient(180deg,var(--bg) 0%,var(--bg-2) 100%); color:var(--text); font-family:Inter,"Segoe UI",sans-serif; }
        body { display:grid; place-items:center; min-height:100vh; padding:30px; }
        .card { width:min(620px,100%); padding:28px; background:rgba(14,23,36,0.82); border:1px solid var(--line); border-radius:24px; box-shadow:0 25px 70px rgba(2,6,23,0.45); }
        h1 { margin:0 0 8px; font-size:2.2rem; letter-spacing:-0.06em; }
        p { color:var(--muted); line-height:1.6; }
        form { display:grid; gap:18px; margin-top:24px; }
        label { display:grid; gap:8px; color:var(--muted); font-size:0.9rem; }
        input, select { width:100%; border-radius:12px; padding:14px 16px; border:1px solid var(--line); background:rgba(15,23,42,0.9); color:var(--text); }
        .actions { display:flex; justify-content:flex-end; gap:12px; margin-top:8px; }
        .button { display:inline-flex; align-items:center; justify-content:center; padding:12px 18px; border-radius:12px; border:1px solid var(--line); background:rgba(148,163,184,0.04); color:var(--text); text-decoration:none; }
        button { cursor:pointer; background:linear-gradient(135deg,rgba(122,231,255,0.22),rgba(138,125,255,0.28)); border-color:rgba(122,231,255,0.36); }
    </style>
</head>
<body>
    <main class="card">
        <h1>Invite user</h1>
        <p>Create an account for an operator. Site assignments can be adjusted after the account is created.</p>
        <form method="post" action="/users/new">
            <input type="hidden" name="_csrf" value="{{CSRF_TOKEN}}">
            <label>Email address<input type="email" name="email" required></label>
            <label>Temporary password<input type="password" name="password" minlength="12" required></label>
            <label>Role
                <select name="role">
                    <option value="viewer">Viewer</option>
                    <option value="editor">Editor</option>
                    <option value="admin">Admin</option>
                </select>
            </label>
            <div class="actions"><a class="button" href="/users">Cancel</a><button class="button" type="submit">Create user</button></div>
        </form>
    </main>
</body>
</html>
HTML);
    }

    public function assign(): void
    {
        $adminId = $this->session->userId();
        if ($adminId === null || $this->repository === null) {
            header('Location: /login');
            exit;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$this->session->validCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
                http_response_code(403);
                return;
            }

            try {
                $this->repository->assignSite(
                    $adminId,
                    (string) ($_POST['user_id'] ?? ''),
                    (string) ($_POST['site_id'] ?? ''),
                    (string) ($_POST['role'] ?? 'viewer'),
                );
                header('Location: /users');
                exit;
            } catch (\Throwable $exception) {
                http_response_code(422);
            }
        }

        $userOptions = '';
        foreach ($this->repository->allForAdmin($adminId) as $user) {
            $userOptions .= sprintf(
                '<option value="%s">%s</option>',
                htmlspecialchars((string) $user['id'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8'),
            );
        }
        $siteOptions = '';
        foreach ($this->repository->sitesForAdmin($adminId) as $site) {
            $siteOptions .= sprintf(
                '<option value="%s">%s (%s)</option>',
                htmlspecialchars((string) $site['id'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $site['name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $site['domain'], ENT_QUOTES, 'UTF-8'),
            );
        }

        echo str_replace(
            ['{{USERS}}', '{{SITES}}', '{{CSRF_TOKEN}}'],
            [$userOptions, $siteOptions, htmlspecialchars($this->session->csrfToken(), ENT_QUOTES, 'UTF-8')],
            <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage access | ClearStats</title>
    <style>
        :root { --bg:#07111b; --bg-2:#0d1d2d; --text:#edf3ff; --muted:#9bb0c8; --line:rgba(148,163,184,0.14); }
        * { box-sizing:border-box; } html,body { margin:0; min-height:100%; background:linear-gradient(180deg,var(--bg),var(--bg-2)); color:var(--text); font-family:Inter,"Segoe UI",sans-serif; }
        body { display:grid; place-items:center; min-height:100vh; padding:30px; } .card { width:min(620px,100%); padding:28px; background:rgba(14,23,36,.82); border:1px solid var(--line); border-radius:24px; }
        h1 { margin:0 0 8px; font-size:2.2rem; letter-spacing:-.06em; } p { color:var(--muted); line-height:1.6; } form { display:grid; gap:18px; margin-top:24px; }
        label { display:grid; gap:8px; color:var(--muted); font-size:.9rem; } select { width:100%; padding:14px 16px; border:1px solid var(--line); border-radius:12px; background:rgba(15,23,42,.9); color:var(--text); }
        .actions { display:flex; justify-content:flex-end; gap:12px; } .button { padding:12px 18px; border:1px solid var(--line); border-radius:12px; color:var(--text); text-decoration:none; background:rgba(148,163,184,.04); cursor:pointer; }
    </style>
</head>
<body><main class="card"><h1>Manage access</h1><p>Assign a site role to an existing account. Reassigning the same user and site updates its role.</p>
<form method="post" action="/users/assign"><input type="hidden" name="_csrf" value="{{CSRF_TOKEN}}"><label>User<select name="user_id" required>{{USERS}}</select></label><label>Site<select name="site_id" required>{{SITES}}</select></label><label>Role<select name="role"><option value="viewer">Viewer</option><option value="editor">Editor</option><option value="admin">Admin</option></select></label><div class="actions"><a class="button" href="/users">Cancel</a><button class="button" type="submit">Save access</button></div></form></main></body>
</html>
HTML,
        );
    }
}
