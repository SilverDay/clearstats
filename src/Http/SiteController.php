<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Http;

final class SiteController
{
    public function __construct(
        private readonly ?SiteRepository $repository = null,
        ?AuthSession $session = null,
    ) {
        $this->session = $session ?? new AuthSession();
    }

    private readonly AuthSession $session;

    public function index(): void
    {
        $userId = $this->session->userId();
        if ($userId === null || $this->repository === null) {
            header('Location: /login');
            exit;
        }

        $sites = $this->repository->forUser($userId);
        $siteRows = '';
        foreach ($sites as $site) {
            $isActive = (int) ($site['active'] ?? 0) === 1;
            $siteRows .= sprintf(
                '<div class="list-item"><div><div class="list-item-title">%s</div><div class="list-item-meta">%s &middot; %sd retention</div></div><div class="list-item-actions"><span class="badge %s">%s</span><a href="/sites/install?site=%s">Install</a><a href="/sites/edit?site=%s">Edit</a></div></div>',
                htmlspecialchars((string) $site['name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $site['domain'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $site['raw_event_retention_days'], ENT_QUOTES, 'UTF-8'),
                $isActive ? 'badge-success' : 'badge-neutral',
                $isActive ? 'Active' : 'Inactive',
                rawurlencode((string) $site['id']),
                rawurlencode((string) $site['id']),
            );
        }

        if ($siteRows === '') {
            $siteRows = '<p class="muted">No sites are assigned to your account yet.</p>';
        }

        $activeCount = count(array_filter($sites, static fn(array $site): bool => (int) ($site['active'] ?? 0) === 1));
        $retention = $sites === [] ? '-' : (string) $sites[0]['raw_event_retention_days'] . 'd';
        $createdSite = null;
        $createdSiteId = trim((string) ($_GET['created'] ?? ''));
        if ($createdSiteId !== '') {
            foreach ($sites as $site) {
                if ((string) $site['id'] === $createdSiteId) {
                    $createdSite = $site;
                    break;
                }
            }
        }

        $installPanel = '';
        if ($createdSite !== null) {
            $scheme = ($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
            $scriptUrl = $scheme . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/js/track.js';
            $snippet = sprintf(
                '<script defer data-site-id="%s" src="%s"></script>',
                htmlspecialchars((string) $createdSite['id'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($scriptUrl, ENT_QUOTES, 'UTF-8'),
            );
            $installPanel = sprintf(
                '<section class="install-panel"><h2>Connect %s</h2><p>Add this script before the closing <code>&lt;/body&gt;</code> tag on <strong>%s</strong>.</p><pre><code>%s</code></pre><p>The script sends one privacy-safe pageview and supports SPA route changes without cookies or browser storage.</p></section>',
                htmlspecialchars((string) $createdSite['name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $createdSite['domain'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8'),
            );
        }

        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ClearStats Sites</title>
</head>
<body>
    <main class="page">
        {{INSTALL_PANEL}}
        <div class="page-head">
            <h1 class="page-title">Sites</h1>
            <div class="actions"><a class="btn btn-primary" href="/sites/new">Add site</a></div>
        </div>

        <div class="grid-split">
            <section class="list">{{SITE_ROWS}}</section>
            <aside class="stack-sm">
                <div class="stat"><span class="stat-label">Tracked sites</span><span class="stat-value">{{SITE_COUNT}}</span></div>
                <div class="stat"><span class="stat-label">Active domains</span><span class="stat-value">{{ACTIVE_COUNT}}</span></div>
                <div class="stat"><span class="stat-label">Retention policy</span><span class="stat-value">{{RETENTION}}</span></div>
            </aside>
        </div>
    </main>
</body>
</html>
HTML;

        echo str_replace(
            ['{{SITE_ROWS}}', '{{SITE_COUNT}}', '{{ACTIVE_COUNT}}', '{{RETENTION}}', '{{INSTALL_PANEL}}'],
            [$siteRows, (string) count($sites), (string) $activeCount, $retention, $installPanel],
            $html,
        );
    }

    public function edit(): void
    {
        $userId = $this->session->userId();
        $siteId = trim((string) ($_GET['site'] ?? $_POST['site_id'] ?? ''));
        if ($userId === null || $this->repository === null) {
            header('Location: /login');
            exit;
        }

        $site = $this->repository->findForAdmin($userId, $siteId);
        if ($site === null) {
            http_response_code(404);
            echo 'Site not found';
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$this->session->validCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
                http_response_code(403);
                return;
            }

            try {
                $this->repository->update(
                    $userId,
                    $siteId,
                    (string) ($_POST['name'] ?? ''),
                    (string) ($_POST['domain'] ?? ''),
                    (string) ($_POST['ip_source'] ?? 'direct'),
                    (int) ($_POST['retention'] ?? 30),
                    isset($_POST['active']),
                );
                header('Location: /sites');
                exit;
            } catch (\Throwable $exception) {
                http_response_code(422);
            }
        }

        $checked = ((int) ($site['active'] ?? 0) === 1) ? ' checked' : '';
        $name = htmlspecialchars((string) $site['name'], ENT_QUOTES, 'UTF-8');
        $domain = htmlspecialchars((string) $site['domain'], ENT_QUOTES, 'UTF-8');
        $retention = htmlspecialchars((string) $site['raw_event_retention_days'], ENT_QUOTES, 'UTF-8');
        $ipSource = (string) $site['ip_source'];
        $csrfToken = htmlspecialchars($this->session->csrfToken(), ENT_QUOTES, 'UTF-8');

        echo sprintf(
            <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit site | ClearStats</title>
</head>
<body>
    <main class="page">
        <section class="card card-wide">
            <h1 class="page-title">Edit site</h1>
            <p class="card-lede">Update the canonical domain, proxy source, retention policy, or active state.</p>
            <form class="form" method="post" action="/sites/edit">
                <input type="hidden" name="_csrf" value="%s">
                <input type="hidden" name="site_id" value="%s">
                <div class="form-row">
                    <label><span>Site name</span><input type="text" name="name" value="%s" required></label>
                    <label><span>Canonical domain</span><input type="text" name="domain" value="%s" required></label>
                </div>
                <div class="form-row">
                    <label><span>IP source</span><select name="ip_source"><option value="direct"%s>direct</option><option value="x-forwarded-for"%s>x-forwarded-for</option><option value="cf-connecting-ip"%s>cf-connecting-ip</option></select></label>
                    <label><span>Raw retention (days)</span><input type="number" name="retention" value="%s" min="1" max="3650" required></label>
                </div>
                <label class="checkbox"><input type="checkbox" name="active" value="1"%s> Active</label>
                <div class="form-actions">
                    <a class="btn" href="/sites">Cancel</a>
                    <button class="btn btn-primary" type="submit">Save changes</button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
HTML,
            $csrfToken,
            htmlspecialchars($siteId, ENT_QUOTES, 'UTF-8'),
            $name,
            $domain,
            $ipSource === 'direct' ? ' selected' : '',
            $ipSource === 'x-forwarded-for' ? ' selected' : '',
            $ipSource === 'cf-connecting-ip' ? ' selected' : '',
            $retention,
            $checked,
        );
    }

    public function install(): void
    {
        $userId = $this->session->userId();
        $siteId = trim((string) ($_GET['site'] ?? ''));
        if ($userId === null || $this->repository === null) {
            header('Location: /login');
            exit;
        }

        $site = null;
        foreach ($this->repository->forUser($userId) as $assignedSite) {
            if ((string) $assignedSite['id'] === $siteId) {
                $site = $assignedSite;
                break;
            }
        }

        if ($site === null) {
            http_response_code(404);
            echo 'Site not found';
            return;
        }

        header('Location: /sites?created=' . rawurlencode($siteId));
        exit;
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
                $createdSite = $this->repository->create(
                    (string) ($_POST['name'] ?? ''),
                    (string) ($_POST['domain'] ?? ''),
                    $userId,
                    (string) ($_POST['ip_source'] ?? 'direct'),
                    (int) ($_POST['retention'] ?? 30),
                );
                header('Location: /sites?created=' . rawurlencode((string) $createdSite['id']));
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
    <title>Create site | ClearStats</title>
</head>
<body>
    <main class="page">
        <section class="card card-wide">
            <h1 class="page-title">Create site</h1>
            <p class="card-lede">Register a new domain and assign the retention and proxy settings for analytics collection.</p>
            <form class="form" method="post">
                <input type="hidden" name="_csrf" value="{{CSRF_TOKEN}}">
                <div class="form-row">
                    <label><span>Site name</span><input type="text" name="name" placeholder="Example marketing" required></label>
                    <label><span>Canonical domain</span><input type="text" name="domain" placeholder="example.com" required></label>
                </div>
                <div class="form-row">
                    <label><span>IP source</span><select name="ip_source"><option value="direct">direct</option><option value="x-forwarded-for">x-forwarded-for</option><option value="cf-connecting-ip">cf-connecting-ip</option></select></label>
                    <label><span>Raw retention (days)</span><input type="number" name="retention" value="30" min="1" max="3650" required></label>
                </div>
                <div class="form-actions">
                    <a class="btn" href="/sites">Cancel</a>
                    <button class="btn btn-primary" type="submit">Save site</button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
HTML);
    }
}
