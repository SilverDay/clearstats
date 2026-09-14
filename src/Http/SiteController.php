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
            $status = ((int) ($site['active'] ?? 0) === 1) ? 'Active' : 'Inactive';
            $siteRows .= sprintf(
                '<div class="site-item"><div><div class="site-name">%s</div><div class="site-meta">%s · %sd retention</div></div><div><span class="status">%s</span> <a class="edit-link" href="/sites/install?site=%s">Install</a> <a class="edit-link" href="/sites/edit?site=%s">Edit</a></div></div>',
                htmlspecialchars((string) $site['name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $site['domain'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $site['raw_event_retention_days'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($status, ENT_QUOTES, 'UTF-8'),
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
                '<section class="install-panel"><div class="install-kicker">Site ready</div><h2>Connect %s</h2><p>Add this script before the closing <code>&lt;/body&gt;</code> tag on <strong>%s</strong>.</p><pre><code>%s</code></pre><p class="install-note">The script sends one privacy-safe pageview and supports SPA route changes without cookies or browser storage.</p></section>',
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
    <style>
        :root { --bg:#07111b; --bg-2:#0d1d2d; --panel:rgba(15,23,42,0.82); --text:#edf3ff; --muted:#9bb0c8; --brand:#7ae7ff; --brand-2:#8a7dff; --accent:#7ef0c8; --warn:#ffbf7b; --line:rgba(148,163,184,0.14); }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; background: linear-gradient(180deg, var(--bg) 0%, var(--bg-2) 100%); color: var(--text); font-family: Inter, "Segoe UI", sans-serif; }
        body { padding: 30px; }
        .shell { width: min(1100px, 100%); margin: 0 auto; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 26px; }
        .title { font-size: 2.2rem; letter-spacing: -0.05em; margin: 0; }
        .button { display:inline-flex; align-items:center; justify-content:center; padding: 11px 16px; border-radius: 12px; text-decoration:none; border:1px solid var(--line); background:rgba(148,163,184,0.04); color:var(--text); }
        .button.primary { background: linear-gradient(135deg, rgba(122,231,255,0.22), rgba(138,125,255,0.28)); border-color: rgba(122,231,255,0.3); }
        .grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 18px; }
        .card { background: rgba(14,23,36,0.82); border: 1px solid var(--line); border-radius: 22px; padding: 20px; }
        .site-list { display: grid; gap: 14px; }
        .site-item { display:flex; justify-content:space-between; align-items:center; border: 1px solid var(--line); border-radius: 16px; background: rgba(148,163,184,0.03); padding: 16px 18px; }
        .site-name { font-weight: 700; font-size: 1.1rem; }
        .site-meta { color: var(--muted); margin-top: 4px; font-size: 0.9rem; }
        .status { display:inline-flex; padding:7px 10px; border-radius: 999px; font-size: 0.75rem; letter-spacing: 0.06em; text-transform: uppercase; background: rgba(126,240,200,0.1); color: var(--accent); border: 1px solid rgba(126,240,200,0.18); }
        .side-card { display:grid; gap:14px; }
        .stat { padding: 16px; border-radius: 16px; background: rgba(148,163,184,0.04); border:1px solid var(--line); }
        .stat .value { font-size: 1.8rem; letter-spacing:-0.05em; font-weight:800; margin-top: 8px; }
        .muted { color: var(--muted); }
        .install-panel { margin-bottom: 18px; padding: 22px; border: 1px solid rgba(122,231,255,0.28); border-radius: 22px; background: linear-gradient(135deg, rgba(122,231,255,0.10), rgba(138,125,255,0.10)); }
        .install-panel h2 { margin: 6px 0 8px; font-size: 1.5rem; }
        .install-panel p { color: var(--muted); line-height: 1.6; }
        .install-kicker { color: var(--brand); font-size: .75rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .install-panel pre { overflow-x: auto; margin: 16px 0; padding: 15px; border-radius: 12px; background: rgba(2,6,23,.62); color: #d8f8ff; }
        .install-panel code { font-family: "SFMono-Regular", Consolas, monospace; font-size: .88rem; }
        .install-note { margin-bottom: 0; font-size: .9rem; }
        @media (max-width: 820px) { .grid { grid-template-columns: 1fr; } .topbar { flex-wrap: wrap; gap: 12px; } }
    </style>
</head>
<body>
    <div class="shell">
        {{INSTALL_PANEL}}
        <div class="topbar">
            <h1 class="title">Sites</h1>
            <a class="button primary" href="/sites/new">Add site</a>
        </div>

        <div class="grid">
            <section class="card">
                <div class="site-list">{{SITE_ROWS}}</div>
            </section>

            <aside class="card side-card">
                <div class="stat">
                    <div class="muted">Tracked sites</div>
                    <div class="value">{{SITE_COUNT}}</div>
                </div>
                <div class="stat">
                    <div class="muted">Active domains</div>
                    <div class="value">{{ACTIVE_COUNT}}</div>
                </div>
                <div class="stat">
                    <div class="muted">Retention policy</div>
                    <div class="value">{{RETENTION}}</div>
                </div>
            </aside>
        </div>
    </div>
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
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Edit site | ClearStats</title>
<style>:root{--bg:#07111b;--bg-2:#0d1d2d;--text:#edf3ff;--muted:#9bb0c8;--line:rgba(148,163,184,.14)}*{box-sizing:border-box}html,body{margin:0;min-height:100%%;background:linear-gradient(180deg,var(--bg),var(--bg-2));color:var(--text);font-family:Inter,"Segoe UI",sans-serif}body{display:grid;place-items:center;min-height:100vh;padding:30px}.card{width:min(620px,100%%);padding:28px;background:rgba(14,23,36,.82);border:1px solid var(--line);border-radius:24px}h1{margin:0 0 8px;font-size:2.2rem;letter-spacing:-.06em}p{color:var(--muted);line-height:1.6}form{display:grid;gap:18px;margin-top:24px}label{display:grid;gap:8px;color:var(--muted);font-size:.9rem}input,select{width:100%%;padding:14px 16px;border:1px solid var(--line);border-radius:12px;background:rgba(15,23,42,.9);color:var(--text)}.check{display:flex;align-items:center;gap:8px}.actions{display:flex;justify-content:flex-end;gap:12px}.button{padding:12px 18px;border:1px solid var(--line);border-radius:12px;color:var(--text);text-decoration:none;background:rgba(148,163,184,.04);cursor:pointer}</style></head>
<body><main class="card"><h1>Edit site</h1><p>Update the canonical domain, proxy source, retention policy, or active state.</p><form method="post" action="/sites/edit"><input type="hidden" name="_csrf" value="%s"><input type="hidden" name="site_id" value="%s"><label>Site name<input name="name" value="%s" required></label><label>Canonical domain<input name="domain" value="%s" required></label><label>IP source<select name="ip_source"><option value="direct"%s>direct</option><option value="x-forwarded-for"%s>x-forwarded-for</option><option value="cf-connecting-ip"%s>cf-connecting-ip</option></select></label><label>Raw retention (days)<input type="number" name="retention" value="%s" min="1" max="3650" required></label><label class="check"><input type="checkbox" name="active" value="1"%s> Active</label><div class="actions"><a class="button" href="/sites">Cancel</a><button class="button" type="submit">Save changes</button></div></form></main></body></html>
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
    <title>Create Site</title>
    <style>
        :root { --bg:#07111b; --bg-2:#0d1d2d; --panel:rgba(15,23,42,0.82); --text:#edf3ff; --muted:#9bb0c8; --brand:#7ae7ff; --brand-2:#8a7dff; --accent:#7ef0c8; --line:rgba(148,163,184,0.14); }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; background: linear-gradient(180deg, var(--bg) 0%, var(--bg-2) 100%); color: var(--text); font-family: Inter, "Segoe UI", sans-serif; }
        body { display: grid; place-items: center; min-height:100vh; padding: 30px; }
        .card { width: min(760px, 100%); background: rgba(14,23,36,0.82); border:1px solid var(--line); border-radius: 24px; padding: 28px; box-shadow: 0 25px 70px rgba(2,6,23,0.45); }
        h1 { margin: 0 0 8px; font-size: 2.2rem; letter-spacing: -0.06em; }
        .muted { color: var(--muted); }
        form { display:grid; gap: 18px; margin-top: 22px; }
        label { display:grid; gap:8px; font-size:0.9rem; color: var(--muted); }
        input, select { width:100%; border-radius: 12px; padding: 14px 16px; border:1px solid var(--line); background: rgba(15,23,42,0.9); color: var(--text); }
        input:focus, select:focus { outline:none; border-color: rgba(122,231,255,0.7); box-shadow: 0 0 0 3px rgba(122,231,255,0.12); }
        .row { display:grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .button-row { display:flex; justify-content:flex-end; gap:12px; margin-top:8px; }
        .button { text-decoration:none; display:inline-flex; align-items:center; justify-content:center; padding:12px 18px; border-radius:12px; border:1px solid var(--line); background: rgba(148,163,184,0.04); color: var(--text); }
        .button.primary { background: linear-gradient(135deg, rgba(122,231,255,0.2), rgba(138,125,255,0.25)); border-color: rgba(122,231,255,0.35); }
        @media (max-width: 640px) { .row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="card">
        <h1>Create site</h1>
        <p class="muted">Register a new domain and assign the retention and proxy settings for analytics collection.</p>
        <form method="post">
            <input type="hidden" name="_csrf" value="{{CSRF_TOKEN}}">
            <div class="row">
                <label>
                    Site name
                    <input type="text" name="name" value="Example marketing">
                </label>
                <label>
                    Canonical domain
                    <input type="text" name="domain" value="example.com">
                </label>
            </div>
            <div class="row">
                <label>
                    IP source
                    <select name="ip_source">
                        <option>direct</option>
                        <option>x-forwarded-for</option>
                        <option>cf-connecting-ip</option>
                    </select>
                </label>
                <label>
                    Raw retention (days)
                    <input type="number" name="retention" value="30">
                </label>
            </div>
            <div class="button-row">
                <a class="button" href="/sites">Cancel</a>
                <button class="button primary" type="submit">Save site</button>
            </div>
        </form>
    </div>
</body>
</html>
HTML);
    }
}
