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

        $columns = 'event_id, site_id, session_id, session_started_at, visitor_hash, event_type, event_name, event_props, engagement_seconds, url_path, campaign_source, campaign_medium, campaign_name, campaign_term, campaign_content, revenue_amount, revenue_currency, referrer_domain, country_code, region, city, device_type, browser, operating_system, language_code, created_at';
        $placeholders = ':event_id, :site_id, :session_id, :session_started_at, :visitor_hash, :event_type, :event_name, :event_props, :engagement_seconds, :url_path, :campaign_source, :campaign_medium, :campaign_name, :campaign_term, :campaign_content, :revenue_amount, :revenue_currency, :referrer_domain, :country_code, :region, :city, :device_type, :browser, :operating_system, :language_code, :created_at';
        $insertSql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "INSERT INTO events_raw ({$columns}) VALUES ({$placeholders}) ON CONFLICT(event_id) DO UPDATE SET event_id = excluded.event_id"
            : "INSERT INTO events_raw ({$columns}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE event_id = VALUES(event_id)";
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
                    throw new \UnexpectedValueException('Queued event must decode to an object.');
                }

                $insert->execute([
                    'event_id' => (string) ($event['event_id'] ?? hash('sha256', $payload)),
                    'site_id' => (string) ($event['site_id'] ?? ''),
                    'session_id' => $this->nullableString($event['session_id'] ?? null),
                    'session_started_at' => $this->nullableString($event['session_started_at'] ?? null),
                    'visitor_hash' => (string) ($event['visitor_hash'] ?? ''),
                    'event_type' => (string) ($event['event_type'] ?? 'pageview'),
                    'event_name' => $this->nullableString($event['event_name'] ?? null),
                    'event_props' => $this->nullableString($event['event_props'] ?? null),
                    'engagement_seconds' => max(0, min(86400, (int) ($event['engagement_seconds'] ?? 0))),
                    'url_path' => (string) ($event['url_path'] ?? '/'),
                    'campaign_source' => $this->nullableString($event['campaign_source'] ?? null),
                    'campaign_medium' => $this->nullableString($event['campaign_medium'] ?? null),
                    'campaign_name' => $this->nullableString($event['campaign_name'] ?? null),
                    'campaign_term' => $this->nullableString($event['campaign_term'] ?? null),
                    'campaign_content' => $this->nullableString($event['campaign_content'] ?? null),
                    'revenue_amount' => $this->nullableString($event['revenue_amount'] ?? null),
                    'revenue_currency' => $this->nullableString($event['revenue_currency'] ?? null),
                    'referrer_domain' => $this->nullableString($event['referrer_domain'] ?? null),
                    'country_code' => $this->nullableString($event['country_code'] ?? null),
                    'region' => $this->nullableString($event['region'] ?? null),
                    'city' => $this->nullableString($event['city'] ?? null),
                    'device_type' => (string) ($event['device_type'] ?? 'other'),
                    'browser' => $this->nullableString($event['browser'] ?? null),
                    'operating_system' => $this->nullableString($event['operating_system'] ?? null),
                    'language_code' => $this->nullableString($event['language_code'] ?? null),
                    'created_at' => $this->createdAt($event['created_at'] ?? null),
                ]);
                $this->queue->acknowledge($payload);
                $processed++;
            } catch (\JsonException | \UnexpectedValueException $exception) {
                $this->queue->discard($payload, $this->reasonFor($exception));
            } catch (\PDOException $exception) {
                if ($this->isTransient($exception)) {
                    // Leave it reserved: recoverProcessing() requeues it next run.
                    break;
                }

                $this->queue->discard($payload, $this->reasonFor($exception));
            } catch (\Throwable $exception) {
                $this->queue->discard($payload, $this->reasonFor($exception));
            }
        }

        return $processed;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    /** Connection loss and lock contention are retryable; a data error is not. */
    private function isTransient(\PDOException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        if (str_starts_with($sqlState, '08') || str_starts_with($sqlState, '40')) {
            return true;
        }

        return in_array((int) ($exception->errorInfo[1] ?? 0), [1205, 1213, 2006, 2013], true);
    }

    /** Exception messages can embed SQL and bound values, so only the type and SQLSTATE are kept. */
    private function reasonFor(\Throwable $exception): string
    {
        $sqlState = $exception instanceof \PDOException ? (string) $exception->getCode() : '';

        return $sqlState === '' ? $exception::class : $exception::class . ' [' . $sqlState . ']';
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
