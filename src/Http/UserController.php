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
            $badgeClass = match ($role) {
                'admin' => 'badge-accent',
                'editor' => 'badge-success',
                default => 'badge-neutral',
            };
            $userRows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td><span class="badge %s">%s</span></td></tr>',
                htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8'),
                $role === 'admin' ? 'All sites' : 'Assigned sites',
                htmlspecialchars((string) $user['site_count'], ENT_QUOTES, 'UTF-8'),
                $badgeClass,
                htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8'),
            );
        }

        if ($userRows === '') {
            $userRows = '<tr><td class="table-empty" colspan="4">Only administrators can view user access.</td></tr>';
        }

        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ClearStats Users</title>
</head>
<body>
    <main class="page">
        <div class="page-head">
            <h1 class="page-title">Users</h1>
            <div class="actions">
                <a class="btn" href="/users/assign">Manage access</a>
                <a class="btn btn-primary" href="/users/new">Invite user</a>
            </div>
        </div>

        <section class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Email</th><th>Access</th><th>Sites</th><th>Role</th></tr>
                    </thead>
                    <tbody>{{USER_ROWS}}</tbody>
                </table>
            </div>
        </section>
    </main>
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
</head>
<body>
    <main class="page">
        <section class="card card-narrow">
            <h1 class="page-title">Invite user</h1>
            <p class="card-lede">Create an account for an operator. Site assignments can be adjusted after the account is created.</p>
            <form class="form" method="post" action="/users/new">
                <input type="hidden" name="_csrf" value="{{CSRF_TOKEN}}">
                <label><span>Email address</span><input type="email" name="email" autocomplete="email" required></label>
                <label><span>Temporary password</span><input type="password" name="password" autocomplete="new-password" minlength="12" required></label>
                <label><span>Role</span><select name="role"><option value="viewer">Viewer</option><option value="editor">Editor</option><option value="admin">Admin</option></select></label>
                <div class="form-actions">
                    <a class="btn" href="/users">Cancel</a>
                    <button class="btn btn-primary" type="submit">Create user</button>
                </div>
            </form>
        </section>
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
</head>
<body>
    <main class="page">
        <section class="card card-narrow">
            <h1 class="page-title">Manage access</h1>
            <p class="card-lede">Assign a site role to an existing account. Reassigning the same user and site updates its role.</p>
            <form class="form" method="post" action="/users/assign">
                <input type="hidden" name="_csrf" value="{{CSRF_TOKEN}}">
                <label><span>User</span><select name="user_id" required>{{USERS}}</select></label>
                <label><span>Site</span><select name="site_id" required>{{SITES}}</select></label>
                <label><span>Role</span><select name="role"><option value="viewer">Viewer</option><option value="editor">Editor</option><option value="admin">Admin</option></select></label>
                <div class="form-actions">
                    <a class="btn" href="/users">Cancel</a>
                    <button class="btn btn-primary" type="submit">Save access</button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
HTML,
        );
    }
}
