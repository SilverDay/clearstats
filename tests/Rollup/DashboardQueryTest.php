<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Rollup;

use ClearStats\Db\Database;
use ClearStats\Db\MigrationRunner;
use ClearStats\Rollup\DashboardQuery;
use ClearStats\Tests\TestDatabase;
use PHPUnit\Framework\TestCase;

final class DashboardQueryTest extends TestCase
{
    public function testLoadsDashboardOverviewAndTopPages(): void
    {
        $database = TestDatabase::create();

        $pdo = $database->pdo();
        $pdo->exec("DELETE FROM daily_site_stats");
        $pdo->exec("DELETE FROM daily_page_stats");
        $pdo->exec("DELETE FROM daily_referrer_stats");
        $pdo->exec("DELETE FROM daily_country_stats");
        $pdo->exec("DELETE FROM daily_device_stats");
        $pdo->exec("INSERT INTO daily_site_stats (site_id, date, pageviews, unique_visitor_hashes_count) VALUES
            ('site-a', '2026-09-14', 41, 12)");
        $pdo->exec("INSERT INTO daily_page_stats (site_id, date, url_path, pageviews) VALUES
            ('site-a', '2026-09-14', '/home', 20),
            ('site-a', '2026-09-14', '/pricing', 8),
            ('site-a', '2026-09-14', '/about', 13)");
        $pdo->exec("INSERT INTO daily_referrer_stats (site_id, date, referrer_domain, visits) VALUES
            ('site-a', '2026-09-14', 'search.example', 15),
            ('site-a', '2026-09-14', 'social.example', 5)");
        $pdo->exec("INSERT INTO daily_country_stats (site_id, date, country_code, visits) VALUES
            ('site-a', '2026-09-14', 'DE', 20),
            ('site-a', '2026-09-14', 'FR', 10)");
        $pdo->exec("INSERT INTO daily_device_stats (site_id, date, device_type, visits) VALUES
            ('site-a', '2026-09-14', 'desktop', 22),
            ('site-a', '2026-09-14', 'mobile', 8)");

        $query = new DashboardQuery($database);
        $overview = $query->overview('site-a', '2026-09-14');
        $topPages = $query->topPages('site-a', '2026-09-14', 5);
        $topReferrers = $query->topReferrers('site-a', '2026-09-14', 5);
        $topCountries = $query->topCountries('site-a', '2026-09-14', 5);
        $devices = $query->devices('site-a', '2026-09-14');

        $this->assertSame(41, $overview['pageviews']);
        $this->assertSame(12, $overview['unique_visitor_hashes_count']);
        $this->assertSame('/home', $topPages[0]['url_path']);
        $this->assertSame(20, $topPages[0]['pageviews']);
        $this->assertSame('search.example', $topReferrers[0]['referrer_domain']);
        $this->assertSame(15, $topReferrers[0]['visits']);
        $this->assertSame('DE', $topCountries[0]['country_code']);
        $this->assertSame('desktop', $devices[0]['device_type']);
    }

    public function testTopPagesReturnsVisitorsAndBounceRateSummedAcrossARange(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $pdo->exec("DELETE FROM daily_page_stats WHERE site_id = 'range-site'");
        $pdo->exec("INSERT INTO daily_page_stats (site_id, date, url_path, pageviews, visitors, entrances, bounces) VALUES
            ('range-site', '2026-09-13', '/home', 10, 8, 6, 2),
            ('range-site', '2026-09-14', '/home', 15, 12, 9, 3)");

        $query = new DashboardQuery($database);
        $rows = $query->topPages('range-site', '2026-09-14', 5, '2026-09-13');

        $this->assertSame('/home', $rows[0]['url_path']);
        $this->assertSame(25, $rows[0]['pageviews']);
        $this->assertSame(20, $rows[0]['visitors']);
        $this->assertSame(15, $rows[0]['entrances']);
        $this->assertSame(5, $rows[0]['bounces']);
        $this->assertSame(33.3, $rows[0]['bounce_rate']);

        $pdo->exec("DELETE FROM daily_page_stats WHERE site_id = 'range-site'");
    }

    public function testOverviewSumsAcrossACustomDateRange(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $pdo->exec("DELETE FROM daily_site_stats WHERE site_id = 'range-overview'");
        $pdo->exec("INSERT INTO daily_site_stats (site_id, date, pageviews, unique_visitor_hashes_count, sessions, bounces, avg_engagement_seconds) VALUES
            ('range-overview', '2026-09-12', 10, 4, 5, 2, 20),
            ('range-overview', '2026-09-13', 20, 8, 5, 1, 40),
            ('range-overview', '2026-09-14', 5, 2, 0, 0, 0)");

        $query = new DashboardQuery($database);

        // Only the first two days requested — the third day must not be included.
        $overview = $query->overview('range-overview', '2026-09-13', '2026-09-12');

        $this->assertSame(30, $overview['pageviews']);
        $this->assertSame(12, $overview['unique_visitor_hashes_count']);
        $this->assertSame(10, $overview['sessions']);
        $this->assertSame(3, $overview['bounces']);
        $this->assertSame(30, $overview['avg_engagement_seconds']);
        $this->assertSame(30.0, $overview['bounce_rate']);

        $pdo->exec("DELETE FROM daily_site_stats WHERE site_id = 'range-overview'");
    }

    public function testLoadsPortfolioOverviewAcrossAssignedSites(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $pdo->exec("DELETE FROM daily_site_stats WHERE date = '2026-09-14'");
        $pdo->exec("INSERT INTO daily_site_stats (site_id, date, pageviews, unique_visitor_hashes_count, sessions, bounces, avg_engagement_seconds) VALUES
            ('portfolio-a', '2026-09-14', 10, 4, 3, 1, 20),
            ('portfolio-b', '2026-09-14', 7, 3, 2, 0, 10)");

        $query = new DashboardQuery($database);
        $overview = $query->portfolioOverview(['portfolio-a', 'portfolio-b'], '2026-09-14');

        $this->assertSame(17, $overview['pageviews']);
        $this->assertSame(7, $overview['unique_visitor_hashes_count']);
        $this->assertSame(5, $overview['sessions']);
        $this->assertSame(1, $overview['bounces']);

        $pdo->exec("DELETE FROM daily_site_stats WHERE site_id IN ('portfolio-a', 'portfolio-b') AND date = '2026-09-14'");
    }

    public function testLoadsPortfolioOperatingSystemsAndLanguages(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $pdo->exec("INSERT INTO daily_os_stats (site_id, date, operating_system, visits) VALUES ('site-a', '2026-09-14', 'linux', 4), ('site-b', '2026-09-14', 'linux', 3)");
        $pdo->exec("INSERT INTO daily_language_stats (site_id, date, language_code, visits) VALUES ('site-a', '2026-09-14', 'de-de', 2), ('site-b', '2026-09-14', 'de-de', 1)");

        $query = new DashboardQuery($database);

        $this->assertSame('linux', $query->portfolioOperatingSystems(['site-a', 'site-b'], '2026-09-14')[0]['operating_system']);
        $this->assertSame(7, $query->portfolioOperatingSystems(['site-a', 'site-b'], '2026-09-14')[0]['visits']);
        $this->assertSame('de-de', $query->portfolioLanguages(['site-a', 'site-b'], '2026-09-14')[0]['language_code']);
        $this->assertSame(3, $query->portfolioLanguages(['site-a', 'site-b'], '2026-09-14')[0]['visits']);
    }
}
