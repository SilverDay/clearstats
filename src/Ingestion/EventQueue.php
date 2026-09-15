<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Ingestion;

/**
 * Pushes validated events onto the Redis-backed queue, per
 * docs/analytics-platform-spec.md §5.3.
 *
 * IMPORTANT: Redis instance is SHARED with the skyggn project on the same
 * host. All keys used by this class MUST be prefixed 'clearstats:' (see
 * config/config.example.php 'redis.key_prefix'). Do not assume exclusive
 * access to the Redis instance — check skyggn's maxmemory/eviction policy
 * before relying on this for anything where data loss is unacceptable
 * (see spec §12, open item).
 *
 * Use BRPOPLPUSH / Redis Streams consumer groups on the consuming side
 * (bin/queue-worker.php), not plain LPOP, so a crashed worker doesn't
 * silently lose events mid-batch.
 */
final class EventQueue
{
    private const QUEUE_KEY = 'clearstats:events';
    private const PROCESSING_KEY = 'clearstats:events:processing';
    private const REJECTED_KEY = 'clearstats:events:rejected';
    private const REJECTED_LOG_LENGTH = 1000;

    public function __construct(
        private readonly \Redis $redis,
    ) {}

    /**
     * @param array<string, mixed> $event Already-validated, already-hashed event data.
     *                                     MUST NOT contain raw IP or raw User-Agent.
     */
    public function push(array $event): void
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $enqueued = $this->redis->lPush(self::QUEUE_KEY, $payload);
        if ($enqueued === false) {
            throw new \RuntimeException('Failed to enqueue event into Redis.');
        }
    }

    public function reserve(): ?string
    {
        $payload = $this->redis->rPopLPush(self::QUEUE_KEY, self::PROCESSING_KEY);

        return $payload === false ? null : (string) $payload;
    }

    public function acknowledge(string $payload): void
    {
        $removed = $this->redis->lRem(self::PROCESSING_KEY, $payload, 1);
        if ($removed === false) {
            throw new \RuntimeException('Failed to acknowledge queued event.');
        }
    }

    /**
     * Drop a payload the worker can never process and keep a bounded, payload-free
     * record of why.
     *
     * The event body is deliberately not retained: it would outlive
     * sites.raw_event_retention_days and can carry operator-supplied values
     * (url_path, campaign fields, event_name). The fingerprint is enough to
     * correlate repeats without storing the data itself.
     */
    public function discard(string $payload, string $reason): void
    {
        $record = json_encode([
            'fingerprint' => hash('sha256', $payload),
            'reason' => $reason,
            'bytes' => strlen($payload),
            'rejected_at' => gmdate('c'),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $this->redis->lPush(self::REJECTED_KEY, $record);
        $this->redis->lTrim(self::REJECTED_KEY, 0, self::REJECTED_LOG_LENGTH - 1);
        $this->acknowledge($payload);
    }

    public function rejectedCount(): int
    {
        return (int) $this->redis->lLen(self::REJECTED_KEY);
    }
    public function recoverProcessing(): int
    {
        $recovered = 0;
        while (($payload = $this->redis->rPopLPush(self::PROCESSING_KEY, self::QUEUE_KEY)) !== false) {
            $recovered++;
        }

        return $recovered;
    }

    public function pendingCount(): int
    {
        return (int) $this->redis->lLen(self::QUEUE_KEY);
    }

    public function processingCount(): int
    {
        return (int) $this->redis->lLen(self::PROCESSING_KEY);
    }
}
