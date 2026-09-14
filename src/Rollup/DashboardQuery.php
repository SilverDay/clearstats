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
     * @return array{pageviews: int, unique_visitor_hashes_count: int, sessions: int, bounces: int, avg_engagement_seconds: int, bounce_rate: float|null}
     */
    public function overview(string $siteId, string $date): array
    {
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            'SELECT pageviews, unique_visitor_hashes_count, sessions, bounces, avg_engagement_seconds, bounce_rate
             FROM daily_site_stats
             WHERE site_id = :site_id AND date = :date'
        );
        $stmt->execute(['site_id' => $siteId, 'date' => $date]);

        $row = $stmt->fetch();
        if ($row === false) {
            return ['pageviews' => 0, 'unique_visitor_hashes_count' => 0, 'sessions' => 0, 'bounces' => 0, 'avg_engagement_seconds' => 0, 'bounce_rate' => null];
        }

        return [
            'pageviews' => (int) $row['pageviews'],
            'unique_visitor_hashes_count' => (int) $row['unique_visitor_hashes_count'],
            'sessions' => (int) $row['sessions'],
            'bounces' => (int) $row['bounces'],
            'avg_engagement_seconds' => (int) $row['avg_engagement_seconds'],
            'bounce_rate' => $row['bounce_rate'] === null ? null : (float) $row['bounce_rate'],
        ];
    }

    /** @param list<string> $siteIds */
    public function portfolioOverview(array $siteIds, string $date): array
    {
        if ($siteIds === []) return ['pageviews' => 0, 'unique_visitor_hashes_count' => 0, 'sessions' => 0, 'bounces' => 0, 'avg_engagement_seconds' => 0, 'bounce_rate' => null];
        $params = ['date' => $date];
        $in = $this->inClause($siteIds, $params, 'site');
        $statement = $this->database->pdo()->prepare("SELECT COALESCE(SUM(pageviews),0) pageviews, COALESCE(SUM(unique_visitor_hashes_count),0) unique_visitor_hashes_count, COALESCE(SUM(sessions),0) sessions, COALESCE(SUM(bounces),0) bounces, COALESCE(SUM(avg_engagement_seconds * sessions),0) engagement_total FROM daily_site_stats WHERE date = :date AND site_id IN ({$in})");
        $statement->execute($params);
        $row = $statement->fetch() ?: [];
        $sessions = (int) ($row['sessions'] ?? 0);
        return ['pageviews' => (int) $row['pageviews'], 'unique_visitor_hashes_count' => (int) $row['unique_visitor_hashes_count'], 'sessions' => $sessions, 'bounces' => (int) $row['bounces'], 'avg_engagement_seconds' => $sessions > 0 ? (int) $row['engagement_total'] / $sessions : 0, 'bounce_rate' => $sessions > 0 ? ((int) $row['bounces'] / $sessions) * 100 : null];
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

    /** @param list<string> $siteIds */
    public function portfolioTopPages(array $siteIds, string $date, int $limit = 10): array
    {
        return $this->portfolioDimensionRows('daily_page_stats', 'url_path', 'pageviews', 'url_path', 'pageviews', $siteIds, $date, $limit);
    }

    /** @param list<string> $siteIds */
    public function portfolioTopReferrers(array $siteIds, string $date, int $limit = 10): array
    {
        return $this->portfolioDimensionRows('daily_referrer_stats', 'referrer_domain', 'visits', 'referrer_domain', 'visits', $siteIds, $date, $limit);
    }

    /** @param list<string> $siteIds */
    public function portfolioTopCountries(array $siteIds, string $date, int $limit = 10): array
    {
        return $this->portfolioDimensionRows('daily_country_stats', 'country_code', 'visits', 'country_code', 'visits', $siteIds, $date, $limit);
    }

    /** @param list<string> $siteIds */
    public function portfolioDevices(array $siteIds, string $date): array
    {
        return $this->portfolioDimensionRows('daily_device_stats', 'device_type', 'visits', 'device_type', 'visits', $siteIds, $date, 100);
    }

    private function portfolioDimensionRows(string $table, string $column, string $countColumn, string $outputColumn, string $outputCount, array $siteIds, string $date, int $limit): array
    {
        $params = ['date' => $date];
        $in = $this->inClause($siteIds, $params, 'site');
        $statement = $this->database->pdo()->prepare("SELECT {$column} value, SUM({$countColumn}) count_value FROM {$table} WHERE date = :date AND site_id IN ({$in}) GROUP BY {$column} ORDER BY count_value DESC, {$column} ASC LIMIT :limit");
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
     * @return list<array{url_path: string, pageviews: int}>
     */
    public function topPages(string $siteId, string $date, int $limit = 10): array
    {
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            'SELECT url_path, pageviews
             FROM daily_page_stats
             WHERE site_id = :site_id AND date = :date
             ORDER BY pageviews DESC, url_path ASC
             LIMIT :limit'
        );

        $stmt->bindValue(':site_id', $siteId, \PDO::PARAM_STR);
        $stmt->bindValue(':date', $date, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'url_path' => (string) $row['url_path'],
                'pageviews' => (int) $row['pageviews'],
            ];
        }

        return $result;
    }

    /**
     * @return list<array{referrer_domain: string, visits: int}>
     */
    public function topReferrers(string $siteId, string $date, int $limit = 10): array
    {
        return $this->dimensionRows('daily_referrer_stats', 'referrer_domain', 'referrer_domain', $siteId, $date, $limit);
    }

    /**
     * @return list<array{country_code: string, visits: int}>
     */
    public function topCountries(string $siteId, string $date, int $limit = 10): array
    {
        return $this->dimensionRows('daily_country_stats', 'country_code', 'country_code', $siteId, $date, $limit);
    }

    /**
     * @return list<array{device_type: string, visits: int}>
     */
    public function devices(string $siteId, string $date): array
    {
        return $this->dimensionRows('daily_device_stats', 'device_type', 'device_type', $siteId, $date, 100);
    }

    public function topEvents(string $siteId, string $date, int $limit = 10): array
    {
        $statement = $this->database->pdo()->prepare('SELECT event_name, events FROM daily_event_stats WHERE site_id = :site_id AND date = :date ORDER BY events DESC, event_name ASC LIMIT :limit');
        $statement->bindValue(':site_id', $siteId, \PDO::PARAM_STR);
        $statement->bindValue(':date', $date, \PDO::PARAM_STR);
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->execute();
        return array_map(static fn(array $row): array => ['event_name' => (string) $row['event_name'], 'events' => (int) $row['events']], $statement->fetchAll());
    }

    public function campaigns(string $siteId, string $date, int $limit = 10): array
    {
        $statement = $this->database->pdo()->prepare('SELECT campaign_source, campaign_medium, campaign_name, visits FROM daily_campaign_stats WHERE site_id = :site_id AND date = :date ORDER BY visits DESC, campaign_source ASC LIMIT :limit');
        $statement->bindValue(':site_id', $siteId, \PDO::PARAM_STR);
        $statement->bindValue(':date', $date, \PDO::PARAM_STR);
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    private function dimensionRows(string $table, string $column, string $outputColumn, string $siteId, string $date, int $limit): array
    {
        $allowed = [
            'daily_referrer_stats' => 'referrer_domain',
            'daily_country_stats' => 'country_code',
            'daily_device_stats' => 'device_type',
        ];
        if (($allowed[$table] ?? null) !== $column) {
            return [];
        }

        $statement = $this->database->pdo()->prepare(
            "SELECT {$column} AS dimension_value, visits
             FROM {$table}
             WHERE site_id = :site_id AND date = :date
             ORDER BY visits DESC, {$column} ASC
             LIMIT :limit",
        );
        $statement->bindValue(':site_id', $siteId, \PDO::PARAM_STR);
        $statement->bindValue(':date', $date, \PDO::PARAM_STR);
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
