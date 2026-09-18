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
    'HealthController' => ClearStats\Http\HealthController::class,
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
        new ClearStats\Rollup\DashboardQuery(
            new ClearStats\Db\Database($config['db']),
        ),
    ),
    ClearStats\Http\HomeController::class => new $controllerClass(
        new ClearStats\Rollup\DashboardQuery(
            new ClearStats\Db\Database($config['db']),
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
    ClearStats\Http\HealthController::class => new $controllerClass(
        (new ClearStats\Db\Database($config['db']))->pdo(),
        new ClearStats\Ingestion\EventQueue(
            (function () use ($config): Redis {
                $redis = new Redis();
                $redis->connect((string) $config['redis']['host'], (int) $config['redis']['port']);
                if ($config['redis']['database'] !== null) {
                    $redis->select((int) $config['redis']['database']);
                }
                return $redis;
            })(),
        ),
        (string) ($config['health']['token'] ?? ''),
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
        new ClearStats\Ingestion\AbuseMonitor(
            (function () use ($config): Redis {
                $redis = new Redis();
                $redis->connect((string) $config['redis']['host'], (int) $config['redis']['port']);
                if ($config['redis']['database'] !== null) {
                    $redis->select((int) $config['redis']['database']);
                }
                return $redis;
            })(),
            new ClearStats\Ingestion\SaltProvider(
                null,
                (new ClearStats\Db\Database($config['db']))->pdo(),
                (int) ($config['salt']['rotation_hours'] ?? 24),
            ),
            (string) ($config['redis']['key_prefix'] ?? 'clearstats:'),
            (int) ($config['ingestion']['abuse_limit'] ?? 20),
            (int) ($config['ingestion']['abuse_window_seconds'] ?? 900),
        ),
    ),
    default => new $controllerClass(),
};
if (!method_exists($controller, $route['action'])) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Action not found\n";
    return;
}

$isHtmlRoute = !in_array($route['controller'], ['EventController', 'HealthController'], true);
if ($isHtmlRoute) {
    ob_start();
}

$controller->{$route['action']}();

if ($isHtmlRoute) {
    $html = (string) ob_get_clean();

    // Runs before first paint so a stored dark preference never flashes light.
    $themeBoot = <<<'JS'
<script>try{var m=localStorage.getItem('clearstats-theme');var d=m==='dark'||((!m||m==='system')&&matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.setAttribute('data-theme',d?'dark':'light')}catch(e){}</script>
JS;

    $assets = '<link rel="preload" href="/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>'
        . '<link rel="stylesheet" href="/css/app-shell.css?v=10">'
        . $themeBoot
        . '<script defer src="/js/theme.js?v=5"></script>';

    $navLink = static function (string $href, string $label) use ($path): string {
        return sprintf(
            '<a href="%s"%s>%s</a>',
            $href,
            $path === $href ? ' aria-current="page"' : '',
            $label,
        );
    };

    $navigation = '';
    if ($isAuthenticated) {
        $links = $navLink('/dashboard', 'Dashboard')
            . $navLink('/sites', 'Sites')
            . ($isAdmin ? $navLink('/users', 'Users') : '')
            . $navLink('/password', 'Password');
        $end = '<div id="clearstats-theme-slot"></div>'
            . '<form method="post" action="/logout"><input type="hidden" name="_csrf" value="'
            . htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8')
            . '"><button class="btn btn-sm btn-ghost" type="submit">Log out</button></form>';
        $home = '/dashboard';
    } else {
        $links = $navLink('/about', 'About') . $navLink('/faq', 'FAQ');
        $end = '<div id="clearstats-theme-slot"></div><a class="btn btn-sm btn-primary" href="/login">Log in</a>';
        $home = '/';
    }

    $navigation = '<header class="topnav"><div class="topnav-inner">'
        . '<a class="topnav-brand" href="' . $home . '">ClearStats</a>'
        . '<nav class="topnav-links" aria-label="Primary navigation">' . $links . '</nav>'
        . '<div class="topnav-end">' . $end . '</div>'
        . '</div></header>';

    $html = (string) (preg_replace_callback(
        '/<body\b[^>]*>/i',
        static fn(array $match): string => $match[0] . $navigation,
        $html,
        1,
    ) ?? $html);
    echo str_replace('</head>', $assets . '</head>', $html);
}
