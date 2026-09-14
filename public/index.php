<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

/**
 * ClearStats front controller.
 *
 * Routing is intentionally minimal — no framework, per project convention
 * (see /CLAUDE.md and docs/analytics-platform-spec.md).
 *
 * TODO: wire this up once src/Http/Router (or equivalent) exists.
 * Expected routes at minimum:
 *   POST /api/event        -> ClearStats\Ingestion\EventController
 *   GET  /dashboard/*       -> ClearStats\Http\DashboardController
 *   GET  /login, /logout    -> ClearStats\Http\AuthController
 */

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
$httpsEnabled = (bool) ($config['app']['https'] ?? false);
if (($config['app']['https'] ?? null) === null) {
    $httpsEnabled = ($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
}
if ($httpsEnabled) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
header('Cross-Origin-Resource-Policy: cross-origin');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?? '/';
$path = $path === '' ? '/' : rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

$router = new ClearStats\Http\Router();
$route = $router->route($method, $path);

$session = null;
$protectedRoute = $route !== null && in_array($route['controller'], ['DashboardController', 'SiteController', 'UserController'], true);
$isAuthenticated = false;
$isAdmin = false;
if ($protectedRoute) {
    $session = new ClearStats\Http\AuthSession();
    $isAuthenticated = $session->isAuthenticated();
}
if ($isAuthenticated && $session !== null) {
    $userDatabase = new ClearStats\Db\Database($config['db']);
    $roleStatement = $userDatabase->pdo()->prepare('SELECT role FROM users WHERE id = :user_id LIMIT 1');
    $roleStatement->execute(['user_id' => $session->userId()]);
    $isAdmin = $roleStatement->fetchColumn() === 'admin';
}

if (
    $protectedRoute
    && !$isAuthenticated
) {
    header('Location: /login');
    exit;
}

if ($route === null) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo "Not Found\n";
    return;
}

$controllerMap = [
    'HomeController' => ClearStats\Http\HomeController::class,
    'InfoController' => ClearStats\Http\InfoController::class,
    'AuthController' => ClearStats\Http\AuthController::class,
    'DashboardController' => ClearStats\Http\DashboardController::class,
    'SiteController' => ClearStats\Http\SiteController::class,
    'UserController' => ClearStats\Http\UserController::class,
    'EventController' => ClearStats\Ingestion\EventController::class,
];

$controllerClass = $controllerMap[$route['controller']] ?? null;
if ($controllerClass === null || !class_exists($controllerClass)) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Controller not found\n";
    return;
}

$controller = match ($controllerClass) {
    ClearStats\Http\AuthController::class => new $controllerClass(
        new ClearStats\Http\AuthSession(),
        new ClearStats\Http\DatabaseAuthenticator(
            (new ClearStats\Db\Database($config['db']))->pdo(),
        ),
        new ClearStats\Http\AuthFailureMonitor(
            (function () use ($config): Redis {
                $redis = new Redis();
                $redis->connect((string) $config['redis']['host'], (int) $config['redis']['port']);
                if ($config['redis']['database'] !== null) {
                    $redis->select((int) $config['redis']['database']);
                }
                return $redis;
            })(),
            (string) ($config['redis']['key_prefix'] ?? 'clearstats:'),
            (int) ($config['auth']['failed_login_limit'] ?? 10),
            (int) ($config['auth']['failed_login_window_seconds'] ?? 900),
        ),
        new ClearStats\Http\UserRepository(
            (new ClearStats\Db\Database($config['db']))->pdo(),
        ),
    ),
    ClearStats\Http\SiteController::class => new $controllerClass(
        new ClearStats\Http\SiteRepository(
            (new ClearStats\Db\Database($config['db']))->pdo(),
        ),
        new ClearStats\Http\AuthSession(),
    ),
    ClearStats\Http\UserController::class => new $controllerClass(
        new ClearStats\Http\UserRepository(
            (new ClearStats\Db\Database($config['db']))->pdo(),
        ),
        new ClearStats\Http\AuthSession(),
    ),
    ClearStats\Http\DashboardController::class => new $controllerClass(
        new ClearStats\Rollup\DashboardQuery(
            $dashboardDatabase = new ClearStats\Db\Database($config['db']),
        ),
        new ClearStats\Http\SiteRepository($dashboardDatabase->pdo()),
        new ClearStats\Http\AuthSession(),
    ),
    ClearStats\Ingestion\EventController::class => new $controllerClass(
        new ClearStats\Domain\SiteValidation(),
        new ClearStats\Domain\SiteAccessPolicy(),
        new ClearStats\Ingestion\VisitorHasher(
            new ClearStats\Ingestion\SaltProvider(
                null,
                (new ClearStats\Db\Database($config['db']))->pdo(),
                (int) ($config['salt']['rotation_hours'] ?? 24),
            ),
        ),
        null,
        new ClearStats\Ingestion\DatabaseSiteResolver(
            (new ClearStats\Db\Database($config['db']))->pdo(),
        ),
        (int) ($config['ingestion']['max_payload_bytes'] ?? 32768),
        new ClearStats\Ingestion\ClientIpResolver(
            $config['ingestion']['trusted_proxy_ips'] ?? [],
        ),
        null,
        (int) ($config['ingestion']['rate_limit_per_minute'] ?? 120),
        new ClearStats\Ingestion\BotDetector(),
        new ClearStats\Ingestion\CountryResolver(
            $config['ingestion']['trusted_proxy_ips'] ?? [],
            $config['geoip']['country_database_path'] ?? null,
        ),
        new ClearStats\Ingestion\UserAgentClassifier(),
        new ClearStats\Ingestion\LanguageResolver(),
    ),
    default => new $controllerClass(),
};
if (!method_exists($controller, $route['action'])) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Action not found\n";
    return;
}

$isHtmlRoute = $route['controller'] !== 'EventController';
if ($isHtmlRoute) {
    ob_start();
}

$controller->{$route['action']}();

if ($isHtmlRoute) {
    $html = (string) ob_get_clean();
    $themeScript = '<link rel="stylesheet" href="/css/app-shell.css"><script defer src="/js/theme.js"></script>';
    $navigation = '';
    if ($isAuthenticated) {
        $navigation = '<nav class="clearstats-app-nav" aria-label="Primary navigation"><a class="clearstats-nav-brand" href="/dashboard">ClearStats</a><div class="clearstats-nav-links"><a href="/dashboard">Dashboard</a><a href="/sites">Sites</a>'
            . ($isAdmin ? '<a href="/users">Users</a>' : '')
            . '<a href="/password">Password</a><form method="post" action="/logout" class="clearstats-logout-form"><input type="hidden" name="_csrf" value="' . htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8') . '"><button type="submit">Log out</button></form></div><div id="clearstats-theme-slot"></div></nav>';
    }
    $navigationStyle = '<style>.clearstats-app-nav{position:sticky;top:0;z-index:900;display:flex;align-items:center;gap:24px;min-height:58px;padding:10px 24px;background:rgba(9,15,25,.96);border-bottom:1px solid rgba(148,163,184,.18);font:14px/1.2 Inter,"Segoe UI",sans-serif}.clearstats-nav-brand{color:#f8fafc;font-weight:800;letter-spacing:.04em;text-decoration:none}.clearstats-nav-links{display:flex;align-items:center;gap:18px;flex-wrap:wrap}.clearstats-nav-links a{color:#dbeafe;text-decoration:none}.clearstats-nav-links a:hover,.clearstats-nav-links a:focus{color:#7ae7ff;text-decoration:underline;text-underline-offset:3px}.clearstats-nav-links a:focus,.clearstats-nav-brand:focus{outline:2px solid #7ae7ff;outline-offset:3px}.clearstats-app-nav #clearstats-theme-slot{margin-left:auto}.clearstats-app-nav .clearstats-theme-control{position:static;box-shadow:none;padding:0;border:0;background:transparent}.clearstats-app-nav .clearstats-theme-control select{min-width:82px}@media(max-width:700px){.clearstats-app-nav{align-items:flex-start;flex-wrap:wrap;padding:12px 16px}.clearstats-nav-links{gap:12px}.clearstats-app-nav #clearstats-theme-slot{margin-left:0}}[data-theme="light"] .clearstats-app-nav{background:#ffffff;border-color:#c5d0dc}[data-theme="light"] .clearstats-nav-brand,[data-theme="light"] .clearstats-nav-links a{color:#183047}[data-theme="light"] .clearstats-nav-links a:hover,[data-theme="light"] .clearstats-nav-links a:focus{color:#075985}</style>';
    $bodyClass = $isAuthenticated ? ' class="clearstats-app-page"' : '';
    $html = str_replace('<body>', '<body' . $bodyClass . '>' . $navigationStyle . $navigation, $html);
    echo str_replace('</head>', $themeScript . '</head>', $html);
}
