<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Rollup;

use ClearStats\Db\Database;

final class DashboardQuery
{
    public function __construct(
        private readonly Database $database,
    ) {}

    /**
     * Aggregate totals across every active site, regardless of who (if
     * anyone) is logged in — used by the public homepage and login screen,
     * which have no per-user site assignment to scope a site list to. Same
     * shape and approximation caveats as portfolioOverview(), just always
     * "every active site" instead of a caller-supplied list.
     *
     * @return array{pageviews: int, unique_visitor_hashes_count: int, sessions: int, bounces: int, avg_engagement_seconds: int, bounce_rate: float|null}
     */
    public function publicOverview(string $endDate, ?string $startDate = null): array
    {
        $startDate ??= $endDate;
        $statement = $this->database->pdo()->prepare(
            'SELECT COALESCE(SUM(dss.pageviews), 0) AS pageviews,
                    COALESCE(SUM(dss.unique_visitor_hashes_count), 0) AS unique_visitor_hashes_count,
                    COALESCE(SUM(dss.sessions), 0) AS sessions,
                    COALESCE(SUM(dss.bounces), 0) AS bounces,
                    COALESCE(SUM(dss.avg_engagement_seconds * dss.sessions), 0) AS engagement_total
             FROM daily_site_stats dss
             INNER JOIN sites ON sites.id = dss.site_id
             WHERE sites.active = 1 AND dss.date BETWEEN :start_date AND :end_date',
        );
        $statement->execute(['start_date' => $startDate, 'end_date' => $endDate]);

        $row = $statement->fetch() ?: [];
        $sessions = (int) ($row['sessions'] ?? 0);
        $bounces = (int) ($row['bounces'] ?? 0);

        return [
            'pageviews' => (int) ($row['pageviews'] ?? 0),
            'unique_visitor_hashes_count' => (int) ($row['unique_visitor_hashes_count'] ?? 0),
            'sessions' => $sessions,
            'bounces' => $bounces,
            'avg_engagement_seconds' => $sessions > 0 ? (int) round(((int) ($row['engagement_total'] ?? 0)) / $sessions) : 0,
            'bounce_rate' => $sessions > 0 ? ($bounces / $sessions) * 100 : null,
        ];
    }

    /**
     * @return list<array{date: string, pageviews: int}>
     */
    public function publicDailyPageviews(string $endDate, int $days = 7): array
    {
        $days = max(1, min($days, 31));
        $start = (new \DateTimeImmutable($endDate, new \DateTimeZone('UTC')))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
        $statement = $this->database->pdo()->prepare(
            'SELECT dss.date, SUM(dss.pageviews) AS pageviews
             FROM daily_site_stats dss
             INNER JOIN sites ON sites.id = dss.site_id
             WHERE sites.active = 1 AND dss.date BETWEEN :start_date AND :end_date
             GROUP BY dss.date
             ORDER BY dss.date ASC',
        );
        $statement->execute(['start_date' => $start, 'end_date' => $endDate]);

        return array_map(
            static fn(array $row): array => ['date' => (string) $row['date'], 'pageviews' => (int) $row['pageviews']],
            $statement->fetchAll(),
        );
    }

    /** Distinct country codes seen across every active site in the window. */
    public function publicRegionCount(string $endDate, ?string $startDate = null): int
    {
        $startDate ??= $endDate;
        $statement = $this->database->pdo()->prepare(
            'SELECT COUNT(DISTINCT dcs.country_code) AS regions
             FROM daily_country_stats dcs
             INNER JOIN sites ON sites.id = dcs.site_id
             WHERE sites.active = 1 AND dcs.date BETWEEN :start_date AND :end_date',
        );
        $statement->execute(['start_date' => $startDate, 'end_date' => $endDate]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array{pageviews: int, unique_visitor_hashes_count: int, sessions: int, bounces: int, avg_engagement_seconds: int, bounce_rate: float|null}
     */
    public function overview(string $siteId, string $endDate, ?string $startDate = null): array
    {
        $startDate ??= $endDate;
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(pageviews), 0) AS pageviews,
                    COALESCE(SUM(unique_visitor_hashes_count), 0) AS unique_visitor_hashes_count,
                    COALESCE(SUM(sessions), 0) AS sessions,
                    COALESCE(SUM(bounces), 0) AS bounces,
                    COALESCE(SUM(avg_engagement_seconds * sessions), 0) AS engagement_total
             FROM daily_site_stats
             WHERE site_id = :site_id AND date BETWEEN :start_date AND :end_date'
        );
        $stmt->execute(['site_id' => $siteId, 'start_date' => $startDate, 'end_date' => $endDate]);

        $row = $stmt->fetch() ?: [];
        $sessions = (int) ($row['sessions'] ?? 0);
        $bounces = (int) ($row['bounces'] ?? 0);

        return [
            'pageviews' => (int) ($row['pageviews'] ?? 0),
            // Sum of each day's distinct-visitor count, not a true distinct count
            // across the whole range — a visitor seen on two days in the range is
            // counted twice. Rollup tables never store the underlying hash set, so
            // an exact cross-day distinct count isn't available. Same approximation
            // portfolioOverview() already makes when summing across sites.
            'unique_visitor_hashes_count' => (int) ($row['unique_visitor_hashes_count'] ?? 0),
            'sessions' => $sessions,
            'bounces' => $bounces,
            'avg_engagement_seconds' => $sessions > 0 ? (int) round(((int) ($row['engagement_total'] ?? 0)) / $sessions) : 0,
            'bounce_rate' => $sessions > 0 ? ($bounces / $sessions) * 100 : null,
        ];
    }

    /** @param list<string> $siteIds */
    public function portfolioOverview(array $siteIds, string $endDate, ?string $startDate = null): array
    {
        if ($siteIds === []) return ['pageviews' => 0, 'unique_visitor_hashes_count' => 0, 'sessions' => 0, 'bounces' => 0, 'avg_engagement_seconds' => 0, 'bounce_rate' => null];
        $startDate ??= $endDate;
        $params = ['start_date' => $startDate, 'end_date' => $endDate];
        $in = $this->inClause($siteIds, $params, 'site');
        $statement = $this->database->pdo()->prepare("SELECT COALESCE(SUM(pageviews),0) pageviews, COALESCE(SUM(unique_visitor_hashes_count),0) unique_visitor_hashes_count, COALESCE(SUM(sessions),0) sessions, COALESCE(SUM(bounces),0) bounces, COALESCE(SUM(avg_engagement_seconds * sessions),0) engagement_total FROM daily_site_stats WHERE date BETWEEN :start_date AND :end_date AND site_id IN ({$in})");
        $statement->execute($params);
        $row = $statement->fetch() ?: [];
        $sessions = (int) ($row['sessions'] ?? 0);
        return ['pageviews' => (int) $row['pageviews'], 'unique_visitor_hashes_count' => (int) $row['unique_visitor_hashes_count'], 'sessions' => $sessions, 'bounces' => (int) $row['bounces'], 'avg_engagement_seconds' => $sessions > 0 ? (int) round((int) $row['engagement_total'] / $sessions) : 0, 'bounce_rate' => $sessions > 0 ? ((int) $row['bounces'] / $sessions) * 100 : null];
    }

    /** @param list<string> $siteIds */
    public function portfolioDailyPageviews(array $siteIds, string $endDate, int $days = 7): array
    {
        if ($siteIds === []) return [];
        $days = max(1, min($days, 31));
        $start = (new \DateTimeImmutable($endDate, new \DateTimeZone('UTC')))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
        $params = ['start_date' => $start, 'end_date' => $endDate];
        $in = $this->inClause($siteIds, $params, 'site');
        $statement = $this->database->pdo()->prepare("SELECT date, SUM(pageviews) pageviews FROM daily_site_stats WHERE date BETWEEN :start_date AND :end_date AND site_id IN ({$in}) GROUP BY date ORDER BY date ASC");
        $statement->execute($params);
        return array_map(static fn(array $row): array => ['date' => (string) $row['date'], 'pageviews' => (int) $row['pageviews']], $statement->fetchAll());
    }

    /**
     * @param list<string> $siteIds
     * @return list<array{url_path: string, pageviews: int, visitors: int, bounce_rate: float|null}>
     */
    public function portfolioTopPages(array $siteIds, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        if ($siteIds === []) return [];
        $startDate ??= $endDate;
        $params = ['start_date' => $startDate, 'end_date' => $endDate];
        $in = $this->inClause($siteIds, $params, 'site');
        $statement = $this->database->pdo()->prepare(
            "SELECT url_path, SUM(pageviews) AS pageviews, SUM(visitors) AS visitors, SUM(entrances) AS entrances, SUM(bounces) AS bounces
             FROM daily_page_stats
             WHERE date BETWEEN :start_date AND :end_date AND site_id IN ({$in})
             GROUP BY url_path
             ORDER BY pageviews DESC, url_path ASC
             LIMIT :limit",
        );
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        foreach ($params as $key => $value) $statement->bindValue(':' . $key, $value, \PDO::PARAM_STR);
        $statement->execute();

        return array_map($this->mapPageRow(...), $statement->fetchAll());
    }

    /** @param list<string> $siteIds */
    public function portfolioTopReferrers(array $siteIds, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        return $this->portfolioDimensionRows('daily_referrer_stats', 'referrer_domain', 'visits', 'referrer_domain', 'visits', $siteIds, $endDate, $limit, $startDate);
    }

    /** @param list<string> $siteIds */
    public function portfolioTopCountries(array $siteIds, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        return $this->portfolioDimensionRows('daily_country_stats', 'country_code', 'visits', 'country_code', 'visits', $siteIds, $endDate, $limit, $startDate);
    }

    /** @param list<string> $siteIds */
    public function portfolioDevices(array $siteIds, string $endDate, ?string $startDate = null): array
    {
        return $this->portfolioDimensionRows('daily_device_stats', 'device_type', 'visits', 'device_type', 'visits', $siteIds, $endDate, 100, $startDate);
    }

    /** @param list<string> $siteIds */
    public function portfolioOperatingSystems(array $siteIds, string $endDate, ?string $startDate = null): array
    {
        return $this->portfolioDimensionRows('daily_os_stats', 'operating_system', 'visits', 'operating_system', 'visits', $siteIds, $endDate, 20, $startDate);
    }

    /** @param list<string> $siteIds */
    public function portfolioBrowsers(array $siteIds, string $endDate, ?string $startDate = null): array
    {
        return $this->portfolioDimensionRows('daily_browser_stats', 'browser', 'visits', 'browser', 'visits', $siteIds, $endDate, 20, $startDate);
    }

    /** @param list<string> $siteIds */
    public function portfolioLanguages(array $siteIds, string $endDate, ?string $startDate = null): array
    {
        return $this->portfolioDimensionRows('daily_language_stats', 'language_code', 'visits', 'language_code', 'visits', $siteIds, $endDate, 20, $startDate);
    }

    /** @param list<string> $siteIds */
    public function portfolioTopEvents(array $siteIds, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        return $this->portfolioDimensionRows('daily_event_stats', 'event_name', 'events', 'event_name', 'events', $siteIds, $endDate, $limit, $startDate);
    }

    /** @param list<string> $siteIds */
    public function portfolioCampaigns(array $siteIds, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        if ($siteIds === []) return [];
        $startDate ??= $endDate;
        $params = ['start_date' => $startDate, 'end_date' => $endDate];
        $in = $this->inClause($siteIds, $params, 'site');
        $statement = $this->database->pdo()->prepare(
            "SELECT campaign_source, campaign_medium, campaign_name, SUM(visits) AS visits
             FROM daily_campaign_stats
             WHERE date BETWEEN :start_date AND :end_date AND site_id IN ({$in})
             GROUP BY campaign_source, campaign_medium, campaign_name
             ORDER BY visits DESC, campaign_source ASC
             LIMIT :limit",
        );
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        foreach ($params as $key => $value) $statement->bindValue(':' . $key, $value, \PDO::PARAM_STR);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** @param list<string> $siteIds */
    public function portfolioTopCampaignTerms(array $siteIds, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        return $this->portfolioDimensionRows('daily_campaign_term_stats', 'campaign_term', 'visits', 'campaign_term', 'visits', $siteIds, $endDate, $limit, $startDate);
    }

    /** @param list<string> $siteIds */
    public function portfolioTopCampaignContent(array $siteIds, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        return $this->portfolioDimensionRows('daily_campaign_content_stats', 'campaign_content', 'visits', 'campaign_content', 'visits', $siteIds, $endDate, $limit, $startDate);
    }

    /**
     * @param list<string> $siteIds
     * @return list<array{country_code: string, region: string, visits: int}>
     */
    public function portfolioTopRegions(array $siteIds, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        if ($siteIds === []) return [];
        $startDate ??= $endDate;
        $params = ['start_date' => $startDate, 'end_date' => $endDate];
        $in = $this->inClause($siteIds, $params, 'site');
        $statement = $this->database->pdo()->prepare(
            "SELECT country_code, region, SUM(visits) AS visits
             FROM daily_region_stats
             WHERE date BETWEEN :start_date AND :end_date AND site_id IN ({$in})
             GROUP BY country_code, region
             ORDER BY visits DESC, country_code ASC, region ASC
             LIMIT :limit",
        );
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        foreach ($params as $key => $value) $statement->bindValue(':' . $key, $value, \PDO::PARAM_STR);
        $statement->execute();

        return array_map(static fn(array $row): array => ['country_code' => (string) $row['country_code'], 'region' => (string) $row['region'], 'visits' => (int) $row['visits']], $statement->fetchAll());
    }

    /**
     * @param list<string> $siteIds
     * @return list<array{country_code: string, city: string, visits: int}>
     */
    public function portfolioTopCities(array $siteIds, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        if ($siteIds === []) return [];
        $startDate ??= $endDate;
        $params = ['start_date' => $startDate, 'end_date' => $endDate];
        $in = $this->inClause($siteIds, $params, 'site');
        $statement = $this->database->pdo()->prepare(
            "SELECT country_code, city, SUM(visits) AS visits
             FROM daily_city_stats
             WHERE date BETWEEN :start_date AND :end_date AND site_id IN ({$in})
             GROUP BY country_code, city
             ORDER BY visits DESC, city ASC
             LIMIT :limit",
        );
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        foreach ($params as $key => $value) $statement->bindValue(':' . $key, $value, \PDO::PARAM_STR);
        $statement->execute();

        return array_map(static fn(array $row): array => ['country_code' => (string) $row['country_code'], 'city' => (string) $row['city'], 'visits' => (int) $row['visits']], $statement->fetchAll());
    }

    /**
     * @param list<string> $siteIds
     * @return list<array{event_name: string, currency: string, conversions: int, revenue_total: float}>
     */
    public function portfolioRevenue(array $siteIds, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        if ($siteIds === []) return [];
        $startDate ??= $endDate;
        $params = ['start_date' => $startDate, 'end_date' => $endDate];
        $in = $this->inClause($siteIds, $params, 'site');
        $statement = $this->database->pdo()->prepare(
            "SELECT event_name, currency, SUM(conversions) AS conversions, SUM(revenue_total) AS revenue_total
             FROM daily_revenue_stats
             WHERE date BETWEEN :start_date AND :end_date AND site_id IN ({$in})
             GROUP BY event_name, currency
             ORDER BY revenue_total DESC
             LIMIT :limit",
        );
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        foreach ($params as $key => $value) $statement->bindValue(':' . $key, $value, \PDO::PARAM_STR);
        $statement->execute();

        return array_map(static fn(array $row): array => [
            'event_name' => (string) $row['event_name'],
            'currency' => (string) $row['currency'],
            'conversions' => (int) $row['conversions'],
            'revenue_total' => (float) $row['revenue_total'],
        ], $statement->fetchAll());
    }

    private function portfolioDimensionRows(string $table, string $column, string $countColumn, string $outputColumn, string $outputCount, array $siteIds, string $endDate, int $limit, ?string $startDate = null): array
    {
        $startDate ??= $endDate;
        $params = ['start_date' => $startDate, 'end_date' => $endDate];
        $in = $this->inClause($siteIds, $params, 'site');
        $statement = $this->database->pdo()->prepare("SELECT {$column} value, SUM({$countColumn}) count_value FROM {$table} WHERE date BETWEEN :start_date AND :end_date AND site_id IN ({$in}) GROUP BY {$column} ORDER BY count_value DESC, {$column} ASC LIMIT :limit");
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        foreach ($params as $key => $value) $statement->bindValue(':' . $key, $value, \PDO::PARAM_STR);
        $statement->execute();
        return array_map(static fn(array $row): array => [$outputColumn => (string) $row['value'], $outputCount => (int) $row['count_value']], $statement->fetchAll());
    }

    /** @param list<string> $siteIds @param array<string, string> $params */
    private function inClause(array $siteIds, array &$params, string $prefix): string
    {
        $placeholders = [];
        foreach (array_values($siteIds) as $index => $siteId) {
            $key = $prefix . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $siteId;
        }
        return implode(', ', $placeholders);
    }

    /**
     * @return list<array{date: string, pageviews: int}>
     */
    public function dailyPageviews(string $siteId, string $endDate, int $days = 7): array
    {
        $days = max(1, min($days, 31));
        $end = new \DateTimeImmutable($endDate, new \DateTimeZone('UTC'));
        $start = $end->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
        $statement = $this->database->pdo()->prepare(
            'SELECT date, pageviews
             FROM daily_site_stats
             WHERE site_id = :site_id AND date BETWEEN :start_date AND :end_date
             ORDER BY date ASC',
        );
        $statement->execute(['site_id' => $siteId, 'start_date' => $start, 'end_date' => $endDate]);

        $rows = [];
        while (($row = $statement->fetch()) !== false) {
            $rows[] = ['date' => (string) $row['date'], 'pageviews' => (int) $row['pageviews']];
        }

        return $rows;
    }

    /**
     * @return list<array{url_path: string, pageviews: int, visitors: int, entrances: int, bounces: int, bounce_rate: float|null}>
     */
    public function topPages(string $siteId, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        $startDate ??= $endDate;
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            'SELECT url_path, SUM(pageviews) AS pageviews, SUM(visitors) AS visitors, SUM(entrances) AS entrances, SUM(bounces) AS bounces
             FROM daily_page_stats
             WHERE site_id = :site_id AND date BETWEEN :start_date AND :end_date
             GROUP BY url_path
             ORDER BY pageviews DESC, url_path ASC
             LIMIT :limit'
        );

        $stmt->bindValue(':site_id', $siteId, \PDO::PARAM_STR);
        $stmt->bindValue(':start_date', $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(':end_date', $endDate, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->mapPageRow(...), $stmt->fetchAll());
    }

    /**
     * @param array<string, int|string> $row
     * @return array{url_path: string, pageviews: int, visitors: int, entrances: int, bounces: int, bounce_rate: float|null}
     */
    private function mapPageRow(array $row): array
    {
        $entrances = (int) ($row['entrances'] ?? 0);
        $bounces = (int) ($row['bounces'] ?? 0);

        return [
            'url_path' => (string) $row['url_path'],
            'pageviews' => (int) $row['pageviews'],
            'visitors' => (int) ($row['visitors'] ?? 0),
            'entrances' => $entrances,
            'bounces' => $bounces,
            'bounce_rate' => $entrances > 0 ? round(($bounces / $entrances) * 100, 1) : null,
        ];
    }

    /**
     * @return list<array{referrer_domain: string, visits: int}>
     */
    public function topReferrers(string $siteId, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        return $this->dimensionRows('daily_referrer_stats', 'referrer_domain', 'referrer_domain', $siteId, $endDate, $limit, $startDate);
    }

    /**
     * @return list<array{country_code: string, visits: int}>
     */
    public function topCountries(string $siteId, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        return $this->dimensionRows('daily_country_stats', 'country_code', 'country_code', $siteId, $endDate, $limit, $startDate);
    }

    /**
     * @return list<array{device_type: string, visits: int}>
     */
    public function devices(string $siteId, string $endDate, ?string $startDate = null): array
    {
        return $this->dimensionRows('daily_device_stats', 'device_type', 'device_type', $siteId, $endDate, 100, $startDate);
    }

    public function operatingSystems(string $siteId, string $endDate, ?string $startDate = null): array
    {
        return $this->dimensionRows('daily_os_stats', 'operating_system', 'operating_system', $siteId, $endDate, 20, $startDate);
    }

    public function browsers(string $siteId, string $endDate, ?string $startDate = null): array
    {
        return $this->dimensionRows('daily_browser_stats', 'browser', 'browser', $siteId, $endDate, 20, $startDate);
    }

    public function languages(string $siteId, string $endDate, ?string $startDate = null): array
    {
        return $this->dimensionRows('daily_language_stats', 'language_code', 'language_code', $siteId, $endDate, 20, $startDate);
    }

    public function topEvents(string $siteId, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        $startDate ??= $endDate;
        $statement = $this->database->pdo()->prepare('SELECT event_name, SUM(events) AS events FROM daily_event_stats WHERE site_id = :site_id AND date BETWEEN :start_date AND :end_date GROUP BY event_name ORDER BY events DESC, event_name ASC LIMIT :limit');
        $statement->bindValue(':site_id', $siteId, \PDO::PARAM_STR);
        $statement->bindValue(':start_date', $startDate, \PDO::PARAM_STR);
        $statement->bindValue(':end_date', $endDate, \PDO::PARAM_STR);
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->execute();
        return array_map(static fn(array $row): array => ['event_name' => (string) $row['event_name'], 'events' => (int) $row['events']], $statement->fetchAll());
    }

    public function campaigns(string $siteId, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        $startDate ??= $endDate;
        $statement = $this->database->pdo()->prepare('SELECT campaign_source, campaign_medium, campaign_name, SUM(visits) AS visits FROM daily_campaign_stats WHERE site_id = :site_id AND date BETWEEN :start_date AND :end_date GROUP BY campaign_source, campaign_medium, campaign_name ORDER BY visits DESC, campaign_source ASC LIMIT :limit');
        $statement->bindValue(':site_id', $siteId, \PDO::PARAM_STR);
        $statement->bindValue(':start_date', $startDate, \PDO::PARAM_STR);
        $statement->bindValue(':end_date', $endDate, \PDO::PARAM_STR);
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function topCampaignTerms(string $siteId, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        return $this->dimensionRows('daily_campaign_term_stats', 'campaign_term', 'campaign_term', $siteId, $endDate, $limit, $startDate);
    }

    public function topCampaignContent(string $siteId, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        return $this->dimensionRows('daily_campaign_content_stats', 'campaign_content', 'campaign_content', $siteId, $endDate, $limit, $startDate);
    }

    /**
     * @return list<array{country_code: string, region: string, visits: int}>
     */
    public function topRegions(string $siteId, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        $startDate ??= $endDate;
        $statement = $this->database->pdo()->prepare(
            'SELECT country_code, region, SUM(visits) AS visits
             FROM daily_region_stats
             WHERE site_id = :site_id AND date BETWEEN :start_date AND :end_date
             GROUP BY country_code, region
             ORDER BY visits DESC, country_code ASC, region ASC
             LIMIT :limit',
        );
        $statement->bindValue(':site_id', $siteId, \PDO::PARAM_STR);
        $statement->bindValue(':start_date', $startDate, \PDO::PARAM_STR);
        $statement->bindValue(':end_date', $endDate, \PDO::PARAM_STR);
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->execute();

        return array_map(static fn(array $row): array => ['country_code' => (string) $row['country_code'], 'region' => (string) $row['region'], 'visits' => (int) $row['visits']], $statement->fetchAll());
    }

    /**
     * @return list<array{country_code: string, city: string, visits: int}>
     */
    public function topCities(string $siteId, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        $startDate ??= $endDate;
        $statement = $this->database->pdo()->prepare(
            'SELECT country_code, city, SUM(visits) AS visits
             FROM daily_city_stats
             WHERE site_id = :site_id AND date BETWEEN :start_date AND :end_date
             GROUP BY country_code, city
             ORDER BY visits DESC, city ASC
             LIMIT :limit',
        );
        $statement->bindValue(':site_id', $siteId, \PDO::PARAM_STR);
        $statement->bindValue(':start_date', $startDate, \PDO::PARAM_STR);
        $statement->bindValue(':end_date', $endDate, \PDO::PARAM_STR);
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->execute();

        return array_map(static fn(array $row): array => ['country_code' => (string) $row['country_code'], 'city' => (string) $row['city'], 'visits' => (int) $row['visits']], $statement->fetchAll());
    }

    /**
     * @return list<array{event_name: string, currency: string, conversions: int, revenue_total: float}>
     */
    public function revenueByGoal(string $siteId, string $endDate, int $limit = 10, ?string $startDate = null): array
    {
        $startDate ??= $endDate;
        $statement = $this->database->pdo()->prepare(
            'SELECT event_name, currency, SUM(conversions) AS conversions, SUM(revenue_total) AS revenue_total
             FROM daily_revenue_stats
             WHERE site_id = :site_id AND date BETWEEN :start_date AND :end_date
             GROUP BY event_name, currency
             ORDER BY revenue_total DESC
             LIMIT :limit',
        );
        $statement->bindValue(':site_id', $siteId, \PDO::PARAM_STR);
        $statement->bindValue(':start_date', $startDate, \PDO::PARAM_STR);
        $statement->bindValue(':end_date', $endDate, \PDO::PARAM_STR);
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->execute();

        return array_map(static fn(array $row): array => [
            'event_name' => (string) $row['event_name'],
            'currency' => (string) $row['currency'],
            'conversions' => (int) $row['conversions'],
            'revenue_total' => (float) $row['revenue_total'],
        ], $statement->fetchAll());
    }

    private function dimensionRows(string $table, string $column, string $outputColumn, string $siteId, string $endDate, int $limit, ?string $startDate = null): array
    {
        $allowed = [
            'daily_referrer_stats' => 'referrer_domain',
            'daily_country_stats' => 'country_code',
            'daily_device_stats' => 'device_type',
            'daily_os_stats' => 'operating_system',
            'daily_language_stats' => 'language_code',
            'daily_browser_stats' => 'browser',
            'daily_campaign_term_stats' => 'campaign_term',
            'daily_campaign_content_stats' => 'campaign_content',
        ];
        if (($allowed[$table] ?? null) !== $column) {
            return [];
        }

        $startDate ??= $endDate;
        $statement = $this->database->pdo()->prepare(
            "SELECT {$column} AS dimension_value, SUM(visits) AS visits
             FROM {$table}
             WHERE site_id = :site_id AND date BETWEEN :start_date AND :end_date
             GROUP BY {$column}
             ORDER BY visits DESC, {$column} ASC
             LIMIT :limit",
        );
        $statement->bindValue(':site_id', $siteId, \PDO::PARAM_STR);
        $statement->bindValue(':start_date', $startDate, \PDO::PARAM_STR);
        $statement->bindValue(':end_date', $endDate, \PDO::PARAM_STR);
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->execute();

        $rows = [];
        while (($row = $statement->fetch()) !== false) {
            $rows[] = [
                $outputColumn => (string) $row['dimension_value'],
                'visits' => (int) $row['visits'],
            ];
        }

        return $rows;
    }
}
