<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Ingestion;

use DateTimeImmutable;
use PDO;
use RuntimeException;

final class QueueWorker
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly EventQueue $queue,
    ) {}

    public function processBatch(int $limit = 100): int
    {
        if ($limit < 1) {
            throw new RuntimeException('Batch limit must be positive.');
        }

        $insertSql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO events_raw
             (event_id, site_id, session_id, session_started_at, visitor_hash, event_type, event_name, engagement_seconds, url_path, campaign_source, campaign_medium, campaign_name, referrer_domain, country_code, device_type, browser, operating_system, language_code, created_at)
             VALUES (:event_id, :site_id, :session_id, :session_started_at, :visitor_hash, :event_type, :event_name, :engagement_seconds, :url_path, :campaign_source, :campaign_medium, :campaign_name, :referrer_domain, :country_code, :device_type, :browser, :operating_system, :language_code, :created_at)
             ON CONFLICT(event_id) DO UPDATE SET event_id = excluded.event_id'
            : 'INSERT INTO events_raw
             (event_id, site_id, session_id, session_started_at, visitor_hash, event_type, event_name, engagement_seconds, url_path, campaign_source, campaign_medium, campaign_name, referrer_domain, country_code, device_type, browser, operating_system, language_code, created_at)
             VALUES (:event_id, :site_id, :session_id, :session_started_at, :visitor_hash, :event_type, :event_name, :engagement_seconds, :url_path, :campaign_source, :campaign_medium, :campaign_name, :referrer_domain, :country_code, :device_type, :browser, :operating_system, :language_code, :created_at)
             ON DUPLICATE KEY UPDATE event_id = VALUES(event_id)';
        $insert = $this->pdo->prepare($insertSql);

        $this->queue->recoverProcessing();
        $processed = 0;
        while ($processed < $limit) {
            $payload = $this->queue->reserve();
            if ($payload === null) {
                break;
            }

            try {
                $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($event)) {
                    throw new RuntimeException('Queued event must decode to an object.');
                }

                $insert->execute([
                    'event_id' => (string) ($event['event_id'] ?? hash('sha256', $payload)),
                    'site_id' => (string) ($event['site_id'] ?? ''),
                    'session_id' => $this->nullableString($event['session_id'] ?? null),
                    'session_started_at' => $this->nullableString($event['session_started_at'] ?? null),
                    'visitor_hash' => (string) ($event['visitor_hash'] ?? ''),
                    'event_type' => (string) ($event['event_type'] ?? 'pageview'),
                    'event_name' => $this->nullableString($event['event_name'] ?? null),
                    'engagement_seconds' => max(0, min(86400, (int) ($event['engagement_seconds'] ?? 0))),
                    'url_path' => (string) ($event['url_path'] ?? '/'),
                    'campaign_source' => $this->nullableString($event['campaign_source'] ?? null),
                    'campaign_medium' => $this->nullableString($event['campaign_medium'] ?? null),
                    'campaign_name' => $this->nullableString($event['campaign_name'] ?? null),
                    'referrer_domain' => $this->nullableString($event['referrer_domain'] ?? null),
                    'country_code' => $this->nullableString($event['country_code'] ?? null),
                    'device_type' => (string) ($event['device_type'] ?? 'other'),
                    'browser' => $this->nullableString($event['browser'] ?? null),
                    'operating_system' => $this->nullableString($event['operating_system'] ?? null),
                    'language_code' => $this->nullableString($event['language_code'] ?? null),
                    'created_at' => $this->createdAt($event['created_at'] ?? null),
                ]);
                $this->queue->acknowledge($payload);
                $processed++;
            } catch (\Throwable $exception) {
                $this->queue->reject($payload, $exception->getMessage());
            }
        }

        return $processed;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function createdAt(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            return gmdate('Y-m-d H:i:s');
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return gmdate('Y-m-d H:i:s');
        }
    }
}
