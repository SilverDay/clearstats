<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Ingestion;

use ClearStats\Domain\SiteAccessPolicy;
use ClearStats\Domain\SiteValidation;

/**
 * Handles POST /api/event.
 *
 * Responsibilities (see docs/analytics-platform-spec.md §5):
 *  - Validate site_id, Origin/Referer against the registered site.
 *  - Resolve client IP per the site's configured ip_source (§3.1) —
 *    respect the trusted-proxy allowlist before trusting forwarded headers.
 *  - Compute visitor_hash = HMAC-SHA256(daily_salt, domain||ip||ua).
 *  - Discard raw IP and raw User-Agent immediately after hashing —
 *    they must never reach a log, a queue payload, or a DB row.
 *  - Push the validated, hashed event onto Redis (clearstats:events).
 *  - Apply basic bot/UA filtering and rate limiting.
 */
final class EventController
{
    public function __construct(
        private SiteValidation $siteValidation = new SiteValidation(),
        private SiteAccessPolicy $siteAccessPolicy = new SiteAccessPolicy(),
        private ?VisitorHasher $visitorHasher = null,
        private ?EventQueue $eventQueue = null,
        private ?SiteResolver $siteResolver = null,
        private readonly int $maxPayloadBytes = 32768,
        ?ClientIpResolver $clientIpResolver = null,
        ?RequestRateLimiter $rateLimiter = null,
        private readonly int $rateLimitPerMinute = 120,
        ?BotDetector $botDetector = null,
        ?CountryResolver $countryResolver = null,
        ?UserAgentClassifier $userAgentClassifier = null,
        ?LanguageResolver $languageResolver = null,
        ?AbuseMonitor $abuseMonitor = null,
    ) {
        $this->visitorHasher ??= new VisitorHasher(new SaltProvider());
        $this->eventQueue ??= $this->buildDefaultQueue();
        $this->clientIpResolver = $clientIpResolver ?? new ClientIpResolver();
        $this->rateLimiter = $rateLimiter ?? $this->buildDefaultRateLimiter();
        $this->botDetector = $botDetector ?? new BotDetector();
        $this->countryResolver = $countryResolver ?? new CountryResolver();
        $this->userAgentClassifier = $userAgentClassifier ?? new UserAgentClassifier();
        $this->languageResolver = $languageResolver ?? new LanguageResolver();
        $this->abuseMonitor = $abuseMonitor;
    }

    private readonly ?AbuseMonitor $abuseMonitor;

    private readonly ClientIpResolver $clientIpResolver;
    private readonly RequestRateLimiter $rateLimiter;
    private readonly BotDetector $botDetector;
    private readonly CountryResolver $countryResolver;
    private readonly UserAgentClassifier $userAgentClassifier;
    private readonly LanguageResolver $languageResolver;

    /**
     * @param list<array{user_id: string, site_id: string, role: string}> $accessRecords
     */
    public function canProcessRequest(
        string $userId,
        string $siteId,
        string $configuredDomain,
        string $requestHost,
        array $accessRecords = [],
    ): bool {
        if (!$this->siteValidation->isValidSiteDomain($configuredDomain, $requestHost)) {
            return false;
        }

        if ($accessRecords === []) {
            return false;
        }

        return $this->siteAccessPolicy->canAccess(
            $userId,
            $siteId,
            $accessRecords,
        );
    }

    public function handle(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'POST')) === 'OPTIONS') {
            $siteId = trim((string) ($_GET['site_id'] ?? ''));
            $site = $this->siteResolver?->resolve($siteId);
            if ($site !== null && $this->originMatchesSite((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), (string) $site['domain'])) {
                $this->respondCors((string) $_SERVER['HTTP_ORIGIN']);
                http_response_code(204);
                return;
            }

            http_response_code(403);
            return;
        }

        // Resolved from the query string so a malformed body is still attributable;
        // track.js posts to /api/event?site_id=...
        $querySiteId = trim((string) ($_GET['site_id'] ?? ''));
        $querySite = $querySiteId === '' ? null : $this->siteResolver?->resolve($querySiteId);
        $sourceIp = $this->clientIpResolver->resolve((string) ($querySite['ip_source'] ?? 'direct'), $_SERVER);

        if ($this->abuseMonitor !== null && !$this->abuseMonitor->allow($sourceIp)) {
            $this->respondJson(429, ['error' => 'Too many rejected requests.']);
            return;
        }

        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > $this->maxPayloadBytes) {
            $this->rejectRequest(413, 'Payload is too large.', $sourceIp);
            return;
        }

        $payload = $this->readRequestPayload();
        if (!is_array($payload)) {
            $this->rejectRequest(400, 'Invalid JSON payload.', $sourceIp);
            return;
        }

        $allowedFields = ['site_id', 'domain', 'event_type', 'url_path', 'referrer_url', 'event_name', 'props', 'session_id', 'session_started_at', 'engagement_seconds', 'campaign_source', 'campaign_medium', 'campaign_name'];
        if (array_diff(array_keys($payload), $allowedFields) !== []) {
            $this->rejectRequest(400, 'Unexpected event fields.', $sourceIp);
            return;
        }

        $siteId = (string) ($payload['site_id'] ?? '');
        $eventType = (string) ($payload['event_type'] ?? 'pageview');
        $urlPath = (string) ($payload['url_path'] ?? '/');
        $sessionId = preg_match('/^[a-f0-9]{32}$/', (string) ($payload['session_id'] ?? '')) === 1 ? (string) $payload['session_id'] : null;
        $sessionStartedAt = $this->parseClientTimestamp($payload['session_started_at'] ?? null);
        $engagementSeconds = max(0, min(86400, (int) ($payload['engagement_seconds'] ?? 0)));
        $referrerUrl = (string) ($payload['referrer_url'] ?? ($_SERVER['HTTP_REFERER'] ?? ''));
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        $requestHost = $this->extractHostFromUrl($origin !== '' ? $origin : $referrerUrl);
        $site = $siteId === $querySiteId ? $querySite : $this->siteResolver?->resolve($siteId);
        $configuredDomain = (string) ($site['domain'] ?? ($payload['domain'] ?? ''));

        if ($siteId === '' || $configuredDomain === '') {
            $this->rejectRequest(400, 'Missing required event fields.', $sourceIp);
            return;
        }

        if ($this->siteResolver !== null && $site === null) {
            $this->rejectRequest(404, 'Unknown or inactive site.', $sourceIp);
            return;
        }

        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($this->originMatchesSite($origin, $configuredDomain)) {
            $this->respondCors($origin);
        }

        if ($userAgent === '') {
            $this->rejectRequest(400, 'User-Agent header is required.', $sourceIp);
            return;
        }

        if ($this->botDetector->isLikelyBot($userAgent)) {
            $this->respondJson(202, ['status' => 'ignored']);
            return;
        }

        if (!in_array($eventType, ['pageview', 'custom', 'session_end'], true)) {
            $this->rejectRequest(400, 'Invalid event type.', $sourceIp);
            return;
        }

        if ($urlPath === '' || strlen($urlPath) > 2048) {
            $this->rejectRequest(400, 'Invalid URL path.', $sourceIp);
            return;
        }

        if ($requestHost === '' || !$this->siteValidation->isValidSiteDomain($configuredDomain, $requestHost)) {
            $this->rejectRequest(403, 'Origin does not match the configured site domain.', $sourceIp);
            return;
        }

        $clientIp = $this->clientIpResolver->resolve((string) ($site['ip_source'] ?? 'direct'), $_SERVER);
        if (!$this->rateLimiter->allow($siteId, $clientIp, $this->rateLimitPerMinute)) {
            $this->respondJson(429, ['error' => 'Rate limit exceeded.']);
            return;
        }

        $metadata = $this->userAgentClassifier->classify($userAgent);
        // One instant for both the salt period and created_at, so they cannot
        // straddle a rotation boundary.
        $receivedAt = time();
        $event = [
            'event_id' => bin2hex(random_bytes(32)),
            'site_id' => $siteId,
            'session_id' => $sessionId,
            'session_started_at' => $sessionStartedAt,
            'visitor_hash' => $this->visitorHasher->hash(
                $configuredDomain,
                $clientIp,
                $userAgent,
                $receivedAt,
            ),
            'event_type' => $eventType,
            'event_name' => (string) ($payload['event_name'] ?? ''),
            'engagement_seconds' => $engagementSeconds,
            'url_path' => $urlPath,
            'campaign_source' => $this->boundedCampaignValue($payload['campaign_source'] ?? null),
            'campaign_medium' => $this->boundedCampaignValue($payload['campaign_medium'] ?? null),
            'campaign_name' => $this->boundedCampaignValue($payload['campaign_name'] ?? null),
            'referrer_domain' => $this->extractDomainFromUrl($referrerUrl),
            'country_code' => $this->countryResolver->resolve($_SERVER, $clientIp),
            'device_type' => $metadata['device_type'],
            'browser' => $metadata['browser'],
            'operating_system' => $metadata['operating_system'],
            'language_code' => $this->languageResolver->resolve($_SERVER),
            'created_at' => gmdate('c', $receivedAt),
        ];

        $this->eventQueue->push($event);

        $this->respondJson(202, ['status' => 'accepted']);
    }

    private function readRequestPayload(): mixed
    {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && trim($raw) !== '') {
            if (strlen($raw) > $this->maxPayloadBytes) {
                return null;
            }

            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return null;
            }
            if (is_array($decoded)) {
                return $decoded;
            }

            return null;
        }

        if (isset($_POST) && is_array($_POST) && $_POST !== []) {
            return $_POST;
        }

        return [];
    }

    private function respondJson(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** Counts the rejection against the source before answering, so repeat offenders trip the block. */
    private function rejectRequest(int $statusCode, string $error, string $clientIp): void
    {
        $this->abuseMonitor?->recordRejection($clientIp);
        $this->respondJson($statusCode, ['error' => $error]);
    }

    private function parseClientTimestamp(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function boundedCampaignValue(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : substr($value, 0, 128);
    }

    private function originMatchesSite(string $origin, string $configuredDomain): bool
    {
        if ($origin === '') {
            return false;
        }

        $originHost = $this->extractHostFromUrl($origin);
        return $originHost !== '' && $this->siteValidation->isValidSiteDomain($configuredDomain, $originHost);
    }

    private function respondCors(string $origin): void
    {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 600');
        header('Vary: Origin');
    }

    private function extractHostFromUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'])) {
            return '';
        }

        return (string) $parts['host'];
    }

    private function extractDomainFromUrl(string $url): string
    {
        $host = $this->extractHostFromUrl($url);
        if ($host === '') {
            return '';
        }

        return strtolower(rtrim($host, '.'));
    }

    private function buildDefaultQueue(): EventQueue
    {
        $configPath = __DIR__ . '/../../config/config.php';
        $config = is_file($configPath) ? require $configPath : [];
        $redisConfig = is_array($config) && isset($config['redis']) && is_array($config['redis']) ? $config['redis'] : [];

        $redis = new \Redis();
        $host = (string) ($redisConfig['host'] ?? '127.0.0.1');
        $port = (int) ($redisConfig['port'] ?? 6379);
        $db = isset($redisConfig['database']) && $redisConfig['database'] !== null ? (int) $redisConfig['database'] : null;

        $redis->connect($host, $port);
        if ($db !== null) {
            $redis->select($db);
        }

        return new EventQueue($redis);
    }

    private function buildDefaultRateLimiter(): RequestRateLimiter
    {
        $configPath = __DIR__ . '/../../config/config.php';
        $config = is_file($configPath) ? require $configPath : [];
        $redisConfig = is_array($config) && isset($config['redis']) && is_array($config['redis']) ? $config['redis'] : [];
        $redis = new \Redis();
        $redis->connect((string) ($redisConfig['host'] ?? '127.0.0.1'), (int) ($redisConfig['port'] ?? 6379));
        if (($redisConfig['database'] ?? null) !== null) {
            $redis->select((int) $redisConfig['database']);
        }

        return new RequestRateLimiter($redis, new SaltProvider(), (string) ($redisConfig['key_prefix'] ?? 'clearstats:'));
    }
}
