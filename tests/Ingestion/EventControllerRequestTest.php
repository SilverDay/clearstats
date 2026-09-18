<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Ingestion;

use ClearStats\Domain\SiteAccessPolicy;
use ClearStats\Domain\SiteValidation;
use ClearStats\Ingestion\AbuseMonitor;
use ClearStats\Ingestion\BotDetector;
use ClearStats\Ingestion\ClientIpResolver;
use ClearStats\Ingestion\CountryResolver;
use ClearStats\Ingestion\EventController;
use ClearStats\Ingestion\EventQueue;
use ClearStats\Ingestion\LanguageResolver;
use ClearStats\Ingestion\RequestRateLimiter;
use ClearStats\Ingestion\SaltProvider;
use ClearStats\Ingestion\SiteResolver;
use ClearStats\Ingestion\UserAgentClassifier;
use ClearStats\Ingestion\VisitorHasher;
use PHPUnit\Framework\TestCase;

final class EventControllerRequestTest extends TestCase
{
    private \Redis $redis;

    /** @var array<string, mixed> */
    private array $server = [];

    protected function setUp(): void
    {
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1', 6379);
        $this->redis->del('clearstats:events', 'clearstats:events:processing');
        $this->clearAbuseKeys();

        $this->server = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64)';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_GET = ['site_id' => 'site-1'];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_GET = [];
        $_POST = [];
        $this->redis->del('clearstats:events', 'clearstats:events:processing');
        $this->clearAbuseKeys();
    }

    private function clearAbuseKeys(): void
    {
        $keys = $this->redis->keys('clearstats:abuse:*');
        if (is_array($keys) && $keys !== []) {
            $this->redis->del(...$keys);
        }
    }

    private function controller(int $abuseLimit = 20): EventController
    {
        $resolver = new class implements SiteResolver {
            public function resolve(string $siteId): ?array
            {
                return $siteId === 'site-1'
                    ? ['id' => 'site-1', 'domain' => 'example.com', 'ip_source' => 'direct']
                    : null;
            }
        };

        return new EventController(
            new SiteValidation(),
            new SiteAccessPolicy(),
            new VisitorHasher(new SaltProvider('test-salt')),
            new EventQueue($this->redis),
            $resolver,
            32768,
            new ClientIpResolver(),
            new RequestRateLimiter($this->redis, new SaltProvider('test-salt')),
            120,
            new BotDetector(),
            new CountryResolver([], null),
            new UserAgentClassifier(),
            new LanguageResolver(),
            new AbuseMonitor($this->redis, new SaltProvider('test-salt'), 'clearstats:', $abuseLimit, 900),
        );
    }

    /** @return array<string, mixed> */
    private function send(EventController $controller): array
    {
        ob_start();
        $controller->handle();

        return (array) json_decode((string) ob_get_clean(), true);
    }

    public function testAcceptsAValidEventAndQueuesIt(): void
    {
        $_POST = ['site_id' => 'site-1', 'event_type' => 'pageview', 'url_path' => '/pricing'];

        $body = $this->send($this->controller());

        $this->assertSame('accepted', $body['status'] ?? null);
        $this->assertSame(1, (int) $this->redis->lLen('clearstats:events'));

        $queued = json_decode((string) $this->redis->lIndex('clearstats:events', 0), true);
        $this->assertSame('/pricing', $queued['url_path']);
        $this->assertSame('site-1', $queued['site_id']);
    }

    public function testQueuedEventNeverCarriesRawIpOrUserAgent(): void
    {
        $_POST = ['site_id' => 'site-1', 'event_type' => 'pageview', 'url_path' => '/'];

        $this->send($this->controller());
        $queued = (string) $this->redis->lIndex('clearstats:events', 0);

        $this->assertStringNotContainsString('203.0.113.10', $queued);
        $this->assertStringNotContainsString('Mozilla/5.0', $queued);
        $this->assertSame(64, strlen((string) json_decode($queued, true)['visitor_hash']));
    }

    public function testCustomEventPropsAreStoredNotDiscarded(): void
    {
        $_POST = [
            'site_id' => 'site-1',
            'event_type' => 'custom',
            'url_path' => '/',
            'event_name' => 'video_started',
            'props' => ['video_id' => 'intro', 'position' => 0],
        ];

        $this->send($this->controller());
        $queued = json_decode((string) $this->redis->lIndex('clearstats:events', 0), true);

        $this->assertSame('{"video_id":"intro","position":0}', $queued['event_props']);
    }

    public function testOversizedPropsAreDroppedNotTruncated(): void
    {
        $_POST = [
            'site_id' => 'site-1',
            'event_type' => 'custom',
            'url_path' => '/',
            'event_name' => 'video_started',
            'props' => ['blob' => str_repeat('x', 3000)],
        ];

        $this->send($this->controller());
        $queued = json_decode((string) $this->redis->lIndex('clearstats:events', 0), true);

        $this->assertNull($queued['event_props']);
    }

    public function testAcceptsExtendedCampaignAndRevenueFields(): void
    {
        $_POST = [
            'site_id' => 'site-1',
            'event_type' => 'custom',
            'url_path' => '/',
            'event_name' => 'conversion:signup',
            'campaign_term' => 'analytics+software',
            'campaign_content' => 'header-cta',
            'revenue_amount' => 49.9,
            'revenue_currency' => 'eur',
        ];

        $this->send($this->controller());
        $queued = json_decode((string) $this->redis->lIndex('clearstats:events', 0), true);

        $this->assertSame('analytics+software', $queued['campaign_term']);
        $this->assertSame('header-cta', $queued['campaign_content']);
        $this->assertSame('49.90', $queued['revenue_amount']);
        $this->assertSame('EUR', $queued['revenue_currency']);
    }

    public function testDropsRevenueAmountWithoutAValidCurrency(): void
    {
        $_POST = [
            'site_id' => 'site-1',
            'event_type' => 'custom',
            'url_path' => '/',
            'event_name' => 'conversion:signup',
            'revenue_amount' => 49.9,
            'revenue_currency' => 'not-a-currency',
        ];

        $this->send($this->controller());
        $queued = json_decode((string) $this->redis->lIndex('clearstats:events', 0), true);

        $this->assertNull($queued['revenue_amount']);
        $this->assertNull($queued['revenue_currency']);
    }

    public function testRepeatedlyRejectedSourceIsBlocked(): void
    {
        $controller = $this->controller(2);
        $_POST = ['unexpected_field' => 'x'];

        $this->assertSame('Unexpected event fields.', $this->send($controller)['error'] ?? null);
        $this->assertSame('Unexpected event fields.', $this->send($controller)['error'] ?? null);
        $this->assertSame('Too many rejected requests.', $this->send($controller)['error'] ?? null);
    }

    public function testBlockAppliesToTheOffendingSourceOnly(): void
    {
        $controller = $this->controller(1);
        $_POST = ['unexpected_field' => 'x'];
        $this->send($controller);

        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';
        $_POST = ['site_id' => 'site-1', 'event_type' => 'pageview', 'url_path' => '/'];

        $this->assertSame('accepted', $this->send($controller)['status'] ?? null);
    }
}
