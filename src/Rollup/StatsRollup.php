<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Rollup;

use ClearStats\Db\Database;
use DateTimeImmutable;

final class StatsRollup
{
    public function __construct(
        private readonly Database $database,
    ) {}

    public function aggregateDay(string $siteId, string $date): void
    {
        $pdo = $this->database->pdo();

        foreach (['daily_page_stats', 'daily_referrer_stats', 'daily_country_stats', 'daily_device_stats', 'daily_os_stats', 'daily_language_stats', 'daily_event_stats', 'daily_campaign_stats'] as $table) {
            $clear = $pdo->prepare("DELETE FROM {$table} WHERE site_id = :site_id AND date = :date");
            $clear->execute(['site_id' => $siteId, 'date' => $date]);
        }

        $siteStats = $pdo->prepare(
            'SELECT COUNT(CASE WHEN event_type = \'pageview\' THEN 1 END) AS pageviews,
                COUNT(DISTINCT visitor_hash) AS unique_visitor_hashes_count
             FROM events_raw
             WHERE site_id = :site_id AND DATE(created_at) = :date'
        );
        $siteStats->execute(['site_id' => $siteId, 'date' => $date]);
        $siteRow = $siteStats->fetch();

        $upsertSite = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO daily_site_stats (site_id, date, pageviews, unique_visitor_hashes_count, sessions, bounces, avg_engagement_seconds, bounce_rate) VALUES (:site_id, :date, :pageviews, :unique_visitor_hashes_count, :sessions, :bounces, :avg_engagement_seconds, :bounce_rate) ON CONFLICT(site_id, date) DO UPDATE SET pageviews = excluded.pageviews, unique_visitor_hashes_count = excluded.unique_visitor_hashes_count'
            : 'INSERT INTO daily_site_stats (site_id, date, pageviews, unique_visitor_hashes_count, sessions, bounces, avg_engagement_seconds, bounce_rate)
                 VALUES (:site_id, :date, :pageviews, :unique_visitor_hashes_count, :sessions, :bounces, :avg_engagement_seconds, :bounce_rate)
                 ON DUPLICATE KEY UPDATE
                pageviews = VALUES(pageviews),
                     unique_visitor_hashes_count = VALUES(unique_visitor_hashes_count),
                     sessions = VALUES(sessions), bounces = VALUES(bounces),
                    avg_engagement_seconds = VALUES(avg_engagement_seconds), bounce_rate = VALUES(bounce_rate)';
        $pdo->prepare($upsertSite)->execute([
            'site_id' => $siteId,
            'date' => $date,
            'pageviews' => (int) ($siteRow['pageviews'] ?? 0),
            'unique_visitor_hashes_count' => (int) ($siteRow['unique_visitor_hashes_count'] ?? 0),
            'sessions' => 0,
            'bounces' => 0,
            'avg_engagement_seconds' => 0,
            'bounce_rate' => null,
        ]);

        $sessionStats = $pdo->prepare(
            'SELECT COUNT(*) AS sessions,
                    SUM(CASE WHEN pageviews = 1 THEN 1 ELSE 0 END) AS bounces,
                    COALESCE(AVG(engagement_seconds), 0) AS avg_engagement_seconds
             FROM (
                 SELECT session_id,
                        SUM(CASE WHEN event_type = \'pageview\' THEN 1 ELSE 0 END) AS pageviews,
                        MAX(CASE WHEN event_type = \'session_end\' THEN engagement_seconds ELSE 0 END) AS engagement_seconds
                 FROM events_raw
                 WHERE site_id = :site_id AND DATE(created_at) = :date AND session_id IS NOT NULL
                 GROUP BY session_id
             ) AS session_rows',
        );
        $sessionStats->execute(['site_id' => $siteId, 'date' => $date]);
        $sessionRow = $sessionStats->fetch() ?: [];
        $sessions = (int) ($sessionRow['sessions'] ?? 0);
        $bounces = (int) ($sessionRow['bounces'] ?? 0);
        $avgEngagement = (int) ($sessionRow['avg_engagement_seconds'] ?? 0);
        $pdo->prepare('UPDATE daily_site_stats SET sessions = :sessions, bounces = :bounces, avg_engagement_seconds = :avg_engagement_seconds, bounce_rate = :bounce_rate WHERE site_id = :site_id AND date = :date')->execute([
            'sessions' => $sessions,
            'bounces' => $bounces,
            'avg_engagement_seconds' => $avgEngagement,
            'bounce_rate' => $sessions > 0 ? ($bounces / $sessions) * 100 : null,
            'site_id' => $siteId,
            'date' => $date,
        ]);

        // event_type = 'pageview' matters here: events_raw also carries 'custom' and
        // 'session_end' rows against the same url_path, which would otherwise inflate
        // the pageview count for that page.
        $pageStats = $pdo->prepare(
            'SELECT url_path, COUNT(*) AS pageviews, COUNT(DISTINCT visitor_hash) AS visitors
             FROM events_raw
             WHERE site_id = :site_id AND DATE(created_at) = :date AND event_type = \'pageview\'
             GROUP BY url_path'
        );
        $pageStats->execute(['site_id' => $siteId, 'date' => $date]);

        $pageRows = [];
        while (($row = $pageStats->fetch()) !== false) {
            $pageRows[(string) $row['url_path']] = [
                'pageviews' => (int) $row['pageviews'],
                'visitors' => (int) $row['visitors'],
                'entrances' => 0,
                'bounces' => 0,
            ];
        }

        // Entrances/bounces are attributed to the first pageview of each session
        // (rn = 1), not every page a session touched, matching how the dashboard
        // reports a page's own bounce rate rather than the site's.
        $entryStats = $pdo->prepare(
            'SELECT entry_url_path, COUNT(*) AS entrances,
                    SUM(CASE WHEN total_pageviews = 1 THEN 1 ELSE 0 END) AS bounces
             FROM (
                 SELECT url_path AS entry_url_path, total_pageviews
                 FROM (
                     SELECT url_path,
                            ROW_NUMBER() OVER (PARTITION BY session_id ORDER BY created_at, id) AS rn,
                            COUNT(*) OVER (PARTITION BY session_id) AS total_pageviews
                     FROM events_raw
                     WHERE site_id = :site_id AND DATE(created_at) = :date
                       AND event_type = \'pageview\' AND session_id IS NOT NULL
                 ) ranked
                 WHERE rn = 1
             ) entries
             GROUP BY entry_url_path',
        );
        $entryStats->execute(['site_id' => $siteId, 'date' => $date]);
        while (($row = $entryStats->fetch()) !== false) {
            $urlPath = (string) $row['entry_url_path'];
            $pageRows[$urlPath] ??= ['pageviews' => 0, 'visitors' => 0, 'entrances' => 0, 'bounces' => 0];
            $pageRows[$urlPath]['entrances'] = (int) $row['entrances'];
            $pageRows[$urlPath]['bounces'] = (int) $row['bounces'];
        }

        foreach ($pageRows as $urlPath => $stats) {
            $upsertPage = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? 'INSERT INTO daily_page_stats (site_id, date, url_path, pageviews, visitors, entrances, bounces) VALUES (:site_id, :date, :url_path, :pageviews, :visitors, :entrances, :bounces) ON CONFLICT(site_id, date, url_path) DO UPDATE SET pageviews = excluded.pageviews, visitors = excluded.visitors, entrances = excluded.entrances, bounces = excluded.bounces'
                : 'INSERT INTO daily_page_stats (site_id, date, url_path, pageviews, visitors, entrances, bounces)
                 VALUES (:site_id, :date, :url_path, :pageviews, :visitors, :entrances, :bounces)
                  ON DUPLICATE KEY UPDATE pageviews = VALUES(pageviews), visitors = VALUES(visitors), entrances = VALUES(entrances), bounces = VALUES(bounces)';
            $pdo->prepare($upsertPage)->execute([
                'site_id' => $siteId,
                'date' => $date,
                'url_path' => $urlPath,
                'pageviews' => $stats['pageviews'],
                'visitors' => $stats['visitors'],
                'entrances' => $stats['entrances'],
                'bounces' => $stats['bounces'],
            ]);
        }

        $dimensions = [
            ['column' => 'referrer_domain', 'table' => 'daily_referrer_stats', 'label' => 'referrer_domain'],
            ['column' => 'country_code', 'table' => 'daily_country_stats', 'label' => 'country_code'],
            ['column' => 'device_type', 'table' => 'daily_device_stats', 'label' => 'device_type'],
            ['column' => 'operating_system', 'table' => 'daily_os_stats', 'label' => 'operating_system'],
            ['column' => 'language_code', 'table' => 'daily_language_stats', 'label' => 'language_code'],
        ];
        foreach ($dimensions as $dimension) {
            $column = $dimension['column'];
            $grouped = $pdo->prepare(
                "SELECT {$column} AS dimension_value, COUNT(*) AS visits
                 FROM events_raw
                 WHERE site_id = :site_id AND DATE(created_at) = :date
                   AND {$column} IS NOT NULL AND {$column} <> ''
                 GROUP BY {$column}",
            );
            $grouped->execute(['site_id' => $siteId, 'date' => $date]);

            while (($row = $grouped->fetch()) !== false) {
                $insert = match ($dimension['table']) {
                    'daily_referrer_stats' => 'INSERT INTO daily_referrer_stats (site_id, date, referrer_domain, visits) VALUES (:site_id, :date, :dimension_value, :visits)',
                    'daily_country_stats' => 'INSERT INTO daily_country_stats (site_id, date, country_code, visits) VALUES (:site_id, :date, :dimension_value, :visits)',
                    'daily_os_stats' => 'INSERT INTO daily_os_stats (site_id, date, operating_system, visits) VALUES (:site_id, :date, :dimension_value, :visits)',
                    'daily_language_stats' => 'INSERT INTO daily_language_stats (site_id, date, language_code, visits) VALUES (:site_id, :date, :dimension_value, :visits)',
                    default => 'INSERT INTO daily_device_stats (site_id, date, device_type, visits) VALUES (:site_id, :date, :dimension_value, :visits)',
                };
                $pdo->prepare($insert)->execute([
                    'site_id' => $siteId,
                    'date' => $date,
                    'dimension_value' => (string) $row['dimension_value'],
                    'visits' => (int) $row['visits'],
                ]);
            }
        }

        $eventStats = $pdo->prepare(
            'SELECT event_name, COUNT(*) AS events FROM events_raw
             WHERE site_id = :site_id AND DATE(created_at) = :date
               AND event_type = \'custom\' AND event_name IS NOT NULL AND event_name <> \'\'
             GROUP BY event_name',
        );
        $eventStats->execute(['site_id' => $siteId, 'date' => $date]);
        while (($row = $eventStats->fetch()) !== false) {
            $pdo->prepare('INSERT INTO daily_event_stats (site_id, date, event_name, events) VALUES (:site_id, :date, :event_name, :events)')->execute([
                'site_id' => $siteId,
                'date' => $date,
                'event_name' => (string) $row['event_name'],
                'events' => (int) $row['events'],
            ]);
        }

        $campaignStats = $pdo->prepare(
            'SELECT campaign_source, campaign_medium, campaign_name, COUNT(*) AS visits FROM events_raw
             WHERE site_id = :site_id AND DATE(created_at) = :date AND event_type = \'pageview\'
               AND campaign_source IS NOT NULL AND campaign_source <> \'\'
             GROUP BY campaign_source, campaign_medium, campaign_name',
        );
        $campaignStats->execute(['site_id' => $siteId, 'date' => $date]);
        while (($row = $campaignStats->fetch()) !== false) {
            $pdo->prepare('INSERT INTO daily_campaign_stats (site_id, date, campaign_source, campaign_medium, campaign_name, visits) VALUES (:site_id, :date, :source, :medium, :name, :visits)')->execute([
                'site_id' => $siteId,
                'date' => $date,
                'source' => (string) $row['campaign_source'],
                'medium' => (string) ($row['campaign_medium'] ?? ''),
                'name' => (string) ($row['campaign_name'] ?? ''),
                'visits' => (int) $row['visits'],
            ]);
        }
    }

    public function purgeExpiredRawEvents(?DateTimeImmutable $now = null): int
    {
        $cutoffReference = ($now ?? new DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $sql = $this->database->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'DELETE FROM events_raw WHERE created_at < datetime(:cutoff_reference, \'-\' || (SELECT raw_event_retention_days FROM sites WHERE sites.id = events_raw.site_id) || \' days\')'
            : 'DELETE events
             FROM events_raw events
             INNER JOIN sites sites ON sites.id = events.site_id
             WHERE events.created_at < DATE_SUB(:cutoff_reference, INTERVAL sites.raw_event_retention_days DAY)';
        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute(['cutoff_reference' => $cutoffReference]);

        return $statement->rowCount();
    }
}
