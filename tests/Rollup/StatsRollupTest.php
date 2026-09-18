<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Rollup;

use ClearStats\Db\Database;
use ClearStats\Db\MigrationRunner;
use ClearStats\Rollup\StatsRollup;
use ClearStats\Tests\TestDatabase;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class StatsRollupTest extends TestCase
{
    public function testAggregatesDailySiteAndPageStats(): void
    {
        $database = TestDatabase::create();

        $pdo = $database->pdo();
        $pdo->exec("DELETE FROM daily_site_stats");
        $pdo->exec("DELETE FROM daily_page_stats");
        $pdo->exec("DELETE FROM daily_referrer_stats");
        $pdo->exec("DELETE FROM daily_country_stats");
        $pdo->exec("DELETE FROM daily_device_stats");
        $pdo->exec("DELETE FROM events_raw");

        $pdo->exec("INSERT INTO events_raw (site_id, visitor_hash, event_type, event_name, url_path, referrer_domain, country_code, device_type, browser, created_at) VALUES
            ('site-a', 'hash-1', 'pageview', NULL, '/home', 'example.org', 'DE', 'desktop', 'chrome', '2026-09-14 12:00:00'),
            ('site-a', 'hash-1', 'pageview', NULL, '/home', 'example.org', 'DE', 'desktop', 'chrome', '2026-09-14 12:05:00'),
            ('site-a', 'hash-2', 'pageview', NULL, '/about', 'example.net', 'FR', 'mobile', 'safari', '2026-09-14 12:10:00')");

        $rollup = new StatsRollup($database);
        $rollup->aggregateDay('site-a', '2026-09-14');

        $siteRow = $pdo->query("SELECT pageviews, unique_visitor_hashes_count FROM daily_site_stats WHERE site_id = 'site-a' AND date = '2026-09-14'")->fetch();
        $pageRow = $pdo->query("SELECT pageviews FROM daily_page_stats WHERE site_id = 'site-a' AND date = '2026-09-14' AND url_path = '/home'")->fetch();
        $referrerRow = $pdo->query("SELECT visits FROM daily_referrer_stats WHERE site_id = 'site-a' AND date = '2026-09-14' AND referrer_domain = 'example.org'")->fetch();
        $countryRow = $pdo->query("SELECT visits FROM daily_country_stats WHERE site_id = 'site-a' AND date = '2026-09-14' AND country_code = 'DE'")->fetch();
        $deviceRow = $pdo->query("SELECT visits FROM daily_device_stats WHERE site_id = 'site-a' AND date = '2026-09-14' AND device_type = 'mobile'")->fetch();

        $this->assertSame('3', (string) $siteRow['pageviews']);
        $this->assertSame('2', (string) $siteRow['unique_visitor_hashes_count']);
        $this->assertSame('2', (string) $pageRow['pageviews']);
        $this->assertSame('2', (string) $referrerRow['visits']);
        $this->assertSame('2', (string) $countryRow['visits']);
        $this->assertSame('1', (string) $deviceRow['visits']);
    }

    public function testDimensionRollupsCountOncePerPageviewNotPerEvent(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $pdo->exec("DELETE FROM events_raw WHERE site_id = 'dim-count-site'");
        $pdo->exec("DELETE FROM daily_country_stats WHERE site_id = 'dim-count-site'");

        // One session: a pageview plus its session_end, both carrying the same
        // country_code. Only the pageview should count toward "visits" —
        // session_end/custom rows must not inflate the dimension count.
        $insert = $pdo->prepare('INSERT INTO events_raw (site_id, session_id, visitor_hash, event_type, url_path, country_code, created_at) VALUES (:site_id, :session_id, :visitor_hash, :event_type, :url_path, :country_code, :created_at)');
        $base = ['site_id' => 'dim-count-site', 'session_id' => 's1', 'visitor_hash' => str_repeat('4', 64), 'url_path' => '/', 'country_code' => 'DE', 'created_at' => '2026-09-14 10:00:00'];
        $insert->execute($base + ['event_type' => 'pageview']);
        $insert->execute($base + ['event_type' => 'session_end']);

        try {
            (new StatsRollup($database))->aggregateDay('dim-count-site', '2026-09-14');

            $country = $pdo->query("SELECT visits FROM daily_country_stats WHERE site_id = 'dim-count-site' AND country_code = 'DE'")->fetch();
            $this->assertSame('1', (string) $country['visits']);
        } finally {
            $pdo->exec("DELETE FROM events_raw WHERE site_id = 'dim-count-site'");
            $pdo->exec("DELETE FROM daily_country_stats WHERE site_id = 'dim-count-site'");
        }
    }

    public function testAggregatesPerPageVisitorsEntrancesAndBounces(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $pdo->exec("DELETE FROM events_raw WHERE site_id = 'page-detail-site'");
        $pdo->exec("DELETE FROM daily_page_stats WHERE site_id = 'page-detail-site'");

        $insert = $pdo->prepare('INSERT INTO events_raw (site_id, session_id, visitor_hash, event_type, url_path, created_at) VALUES (:site_id, :session_id, :visitor_hash, :event_type, :url_path, :created_at)');
        $base = ['site_id' => 'page-detail-site'];
        // Session "a" lands on /home and bounces (single pageview).
        $insert->execute($base + ['session_id' => 'a', 'visitor_hash' => str_repeat('1', 64), 'event_type' => 'pageview', 'url_path' => '/home', 'created_at' => '2026-09-14 10:00:00']);
        // Session "b" lands on /home, then continues to /pricing (not a bounce for /home).
        $insert->execute($base + ['session_id' => 'b', 'visitor_hash' => str_repeat('2', 64), 'event_type' => 'pageview', 'url_path' => '/home', 'created_at' => '2026-09-14 10:05:00']);
        $insert->execute($base + ['session_id' => 'b', 'visitor_hash' => str_repeat('2', 64), 'event_type' => 'pageview', 'url_path' => '/pricing', 'created_at' => '2026-09-14 10:06:00']);
        // Session "c" lands directly on /pricing and bounces.
        $insert->execute($base + ['session_id' => 'c', 'visitor_hash' => str_repeat('3', 64), 'event_type' => 'pageview', 'url_path' => '/pricing', 'created_at' => '2026-09-14 10:10:00']);
        // A non-pageview event against /home must not inflate its pageview count.
        $insert->execute($base + ['session_id' => 'a', 'visitor_hash' => str_repeat('1', 64), 'event_type' => 'session_end', 'url_path' => '/home', 'created_at' => '2026-09-14 10:01:00']);

        try {
            (new StatsRollup($database))->aggregateDay('page-detail-site', '2026-09-14');

            $home = $pdo->query("SELECT pageviews, visitors, entrances, bounces FROM daily_page_stats WHERE site_id = 'page-detail-site' AND url_path = '/home'")->fetch();
            $pricing = $pdo->query("SELECT pageviews, visitors, entrances, bounces FROM daily_page_stats WHERE site_id = 'page-detail-site' AND url_path = '/pricing'")->fetch();

            $this->assertSame('2', (string) $home['pageviews']);
            $this->assertSame('2', (string) $home['visitors']);
            $this->assertSame('2', (string) $home['entrances']);
            $this->assertSame('1', (string) $home['bounces']);

            $this->assertSame('2', (string) $pricing['pageviews']);
            $this->assertSame('2', (string) $pricing['visitors']);
            $this->assertSame('1', (string) $pricing['entrances']);
            $this->assertSame('1', (string) $pricing['bounces']);
        } finally {
            $pdo->exec("DELETE FROM events_raw WHERE site_id = 'page-detail-site'");
            $pdo->exec("DELETE FROM daily_page_stats WHERE site_id = 'page-detail-site'");
        }
    }

    public function testPurgesRawEventsUsingEachSitesRetentionPolicy(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $siteId = 'retention-site-' . bin2hex(random_bytes(6));
        $userEmail = 'retention-owner-' . bin2hex(random_bytes(6)) . '@example.test';
        $userId = '';

        try {
            $user = $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)');
            $user->execute(['email' => $userEmail, 'password_hash' => 'not-used', 'role' => 'admin']);
            $userId = (string) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO sites (id, name, domain, owner_user_id, raw_event_retention_days) VALUES (:id, :name, :domain, :owner_user_id, :retention_days)')->execute([
                'id' => $siteId,
                'name' => 'Retention test site',
                'domain' => $siteId . '.example.test',
                'owner_user_id' => $userId,
                'retention_days' => 7,
            ]);
            $insertEvent = $pdo->prepare('INSERT INTO events_raw (site_id, visitor_hash, event_type, url_path, created_at) VALUES (:site_id, :visitor_hash, :event_type, :url_path, :created_at)');
            $insertEvent->execute([
                'site_id' => $siteId,
                'visitor_hash' => str_repeat('b', 64),
                'event_type' => 'pageview',
                'url_path' => '/',
                'created_at' => '2026-09-01 12:00:00',
            ]);
            $insertEvent->execute([
                'site_id' => $siteId,
                'visitor_hash' => str_repeat('c', 64),
                'event_type' => 'pageview',
                'url_path' => '/',
                'created_at' => '2026-09-10 12:00:00',
            ]);

            $deleted = (new StatsRollup($database))->purgeExpiredRawEvents(
                new DateTimeImmutable('2026-09-14 12:00:00', new DateTimeZone('UTC')),
            );

            $remaining = $pdo->prepare('SELECT COUNT(*) FROM events_raw WHERE site_id = :site_id');
            $remaining->execute(['site_id' => $siteId]);
            $this->assertSame(1, $deleted);
            $this->assertSame('1', (string) $remaining->fetchColumn());
        } finally {
            $pdo->prepare('DELETE FROM events_raw WHERE site_id = :site_id')->execute(['site_id' => $siteId]);
            $pdo->prepare('DELETE FROM sites WHERE id = :site_id')->execute(['site_id' => $siteId]);
            if ($userId !== '') {
                $pdo->prepare('DELETE FROM users WHERE id = :user_id')->execute(['user_id' => $userId]);
            }
        }
    }

    public function testAggregatesSessionsEngagementConversionsAndCampaigns(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $pdo->exec("DELETE FROM events_raw WHERE site_id = 'metrics-site'");
        $pdo->exec("DELETE FROM daily_site_stats WHERE site_id = 'metrics-site'");
        $pdo->exec("DELETE FROM daily_event_stats WHERE site_id = 'metrics-site'");
        $pdo->exec("DELETE FROM daily_campaign_stats WHERE site_id = 'metrics-site'");
        $email = 'metrics-owner-' . bin2hex(random_bytes(6)) . '@example.test';
        $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (:email, :hash, :role)')->execute(['email' => $email, 'hash' => 'not-used', 'role' => 'admin']);
        $userId = (string) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO sites (id, name, domain, owner_user_id) VALUES (:id, :name, :domain, :owner_user_id)')->execute(['id' => 'metrics-site', 'name' => 'Metrics test', 'domain' => 'metrics.example.test', 'owner_user_id' => $userId]);
        $insert = $pdo->prepare('INSERT INTO events_raw (site_id, session_id, visitor_hash, event_type, event_name, engagement_seconds, url_path, campaign_source, campaign_medium, campaign_name, created_at) VALUES (:site_id, :session_id, :visitor_hash, :event_type, :event_name, :engagement_seconds, :url_path, :campaign_source, :campaign_medium, :campaign_name, :created_at)');
        $base = ['site_id' => 'metrics-site', 'url_path' => '/', 'campaign_source' => 'newsletter', 'campaign_medium' => 'email', 'campaign_name' => 'launch', 'created_at' => '2026-09-14 12:00:00'];
        $insert->execute($base + ['session_id' => 'session-one', 'visitor_hash' => str_repeat('1', 64), 'event_type' => 'pageview', 'event_name' => '', 'engagement_seconds' => 0]);
        $insert->execute($base + ['session_id' => 'session-one', 'visitor_hash' => str_repeat('1', 64), 'event_type' => 'session_end', 'event_name' => 'session_end', 'engagement_seconds' => 10]);
        $insert->execute($base + ['session_id' => 'session-two', 'visitor_hash' => str_repeat('2', 64), 'event_type' => 'pageview', 'event_name' => '', 'engagement_seconds' => 0]);
        $insert->execute($base + ['session_id' => 'session-two', 'visitor_hash' => str_repeat('2', 64), 'event_type' => 'pageview', 'event_name' => '', 'engagement_seconds' => 0]);
        $insert->execute($base + ['session_id' => 'session-two', 'visitor_hash' => str_repeat('2', 64), 'event_type' => 'session_end', 'event_name' => 'session_end', 'engagement_seconds' => 20]);
        $insert->execute($base + ['session_id' => 'session-two', 'visitor_hash' => str_repeat('2', 64), 'event_type' => 'custom', 'event_name' => 'conversion:signup', 'engagement_seconds' => 0]);

        try {
            (new StatsRollup($database))->aggregateDay('metrics-site', '2026-09-14');
            $row = $pdo->query("SELECT sessions, bounces, avg_engagement_seconds, bounce_rate FROM daily_site_stats WHERE site_id = 'metrics-site' AND date = '2026-09-14'")->fetch();
            $event = $pdo->query("SELECT events FROM daily_event_stats WHERE site_id = 'metrics-site' AND event_name = 'conversion:signup'")->fetchColumn();
            $campaign = $pdo->query("SELECT visits FROM daily_campaign_stats WHERE site_id = 'metrics-site' AND campaign_source = 'newsletter'")->fetchColumn();

            $this->assertSame('2', (string) $row['sessions']);
            $this->assertSame('1', (string) $row['bounces']);
            $this->assertSame('15', (string) $row['avg_engagement_seconds']);
            $this->assertSame(50.0, (float) $row['bounce_rate']);
            $this->assertSame('1', (string) $event);
            $this->assertSame('3', (string) $campaign);
        } finally {
            $pdo->exec("DELETE FROM events_raw WHERE site_id = 'metrics-site'");
            $pdo->exec("DELETE FROM daily_site_stats WHERE site_id = 'metrics-site'");
            $pdo->exec("DELETE FROM daily_event_stats WHERE site_id = 'metrics-site'");
            $pdo->exec("DELETE FROM daily_campaign_stats WHERE site_id = 'metrics-site'");
            $pdo->prepare('DELETE FROM sites WHERE id = :site_id')->execute(['site_id' => 'metrics-site']);
            $pdo->prepare('DELETE FROM users WHERE id = :user_id')->execute(['user_id' => $userId]);
        }
    }

    public function testAggregatesCampaignTermContentRegionCityAndRevenue(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $pdo->exec("DELETE FROM events_raw WHERE site_id = 'ext-site'");

        $insert = $pdo->prepare('INSERT INTO events_raw (site_id, visitor_hash, event_type, event_name, url_path, campaign_source, campaign_term, campaign_content, revenue_amount, revenue_currency, country_code, region, city, created_at) VALUES (:site_id, :visitor_hash, :event_type, :event_name, :url_path, :campaign_source, :campaign_term, :campaign_content, :revenue_amount, :revenue_currency, :country_code, :region, :city, :created_at)');
        $base = ['site_id' => 'ext-site', 'visitor_hash' => str_repeat('a', 64), 'created_at' => '2026-09-14 10:00:00'];

        $insert->execute($base + ['event_type' => 'pageview', 'event_name' => null, 'url_path' => '/', 'campaign_source' => 'google', 'campaign_term' => 'analytics', 'campaign_content' => 'ad-1', 'revenue_amount' => null, 'revenue_currency' => null, 'country_code' => 'DE', 'region' => 'BE', 'city' => 'Berlin']);
        $insert->execute($base + ['event_type' => 'pageview', 'event_name' => null, 'url_path' => '/pricing', 'campaign_source' => 'google', 'campaign_term' => 'analytics', 'campaign_content' => 'ad-2', 'revenue_amount' => null, 'revenue_currency' => null, 'country_code' => 'DE', 'region' => 'BY', 'city' => 'Munich']);
        $insert->execute($base + ['event_type' => 'custom', 'event_name' => 'conversion:signup', 'url_path' => '/pricing', 'campaign_source' => null, 'campaign_term' => null, 'campaign_content' => null, 'revenue_amount' => '49.90', 'revenue_currency' => 'EUR', 'country_code' => 'DE', 'region' => null, 'city' => null]);
        $insert->execute($base + ['event_type' => 'custom', 'event_name' => 'conversion:signup', 'url_path' => '/pricing', 'campaign_source' => null, 'campaign_term' => null, 'campaign_content' => null, 'revenue_amount' => '99.00', 'revenue_currency' => 'USD', 'country_code' => 'US', 'region' => null, 'city' => null]);

        try {
            (new StatsRollup($database))->aggregateDay('ext-site', '2026-09-14');

            $term = $pdo->query("SELECT visits FROM daily_campaign_term_stats WHERE site_id = 'ext-site' AND campaign_term = 'analytics'")->fetch();
            $this->assertSame('2', (string) $term['visits']);

            $content = $pdo->query("SELECT visits FROM daily_campaign_content_stats WHERE site_id = 'ext-site' AND campaign_content = 'ad-1'")->fetch();
            $this->assertSame('1', (string) $content['visits']);

            $region = $pdo->query("SELECT visits FROM daily_region_stats WHERE site_id = 'ext-site' AND country_code = 'DE' AND region = 'BE'")->fetch();
            $this->assertSame('1', (string) $region['visits']);

            $city = $pdo->query("SELECT visits FROM daily_city_stats WHERE site_id = 'ext-site' AND country_code = 'DE' AND city = 'Munich'")->fetch();
            $this->assertSame('1', (string) $city['visits']);

            $eur = $pdo->query("SELECT conversions, revenue_total FROM daily_revenue_stats WHERE site_id = 'ext-site' AND currency = 'EUR'")->fetch();
            $this->assertSame('1', (string) $eur['conversions']);
            $this->assertSame(49.9, (float) $eur['revenue_total']);

            $usd = $pdo->query("SELECT conversions, revenue_total FROM daily_revenue_stats WHERE site_id = 'ext-site' AND currency = 'USD'")->fetch();
            $this->assertSame('1', (string) $usd['conversions']);
            $this->assertSame(99.0, (float) $usd['revenue_total']);
        } finally {
            $pdo->exec("DELETE FROM events_raw WHERE site_id = 'ext-site'");
            $pdo->exec("DELETE FROM daily_campaign_term_stats WHERE site_id = 'ext-site'");
            $pdo->exec("DELETE FROM daily_campaign_content_stats WHERE site_id = 'ext-site'");
            $pdo->exec("DELETE FROM daily_region_stats WHERE site_id = 'ext-site'");
            $pdo->exec("DELETE FROM daily_city_stats WHERE site_id = 'ext-site'");
            $pdo->exec("DELETE FROM daily_revenue_stats WHERE site_id = 'ext-site'");
        }
    }
}
