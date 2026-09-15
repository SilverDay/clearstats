<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Ingestion;

/**
 * Blocks sources that repeatedly send payloads the endpoint cannot accept.
 *
 * Attribution has to happen here rather than in the queue worker: by the time a
 * payload reaches the worker the client IP has already been discarded, and an
 * undecodable payload carries no site_id either.
 *
 * The IP is only ever used as an HMAC input, never stored. Counters are keyed by
 * that hash and expire with the window, so a block lifts by itself and nothing
 * durable identifies the source. The daily salt rotation also retires old keys.
 */
final class AbuseMonitor
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly SaltProvider $saltProvider = new SaltProvider(),
        private readonly string $keyPrefix = 'clearstats:',
        private readonly int $limit = 20,
        private readonly int $windowSeconds = 900,
    ) {}

    public function allow(string $clientIp): bool
    {
        if ($clientIp === '' || $this->limit < 1) {
            return true;
        }

        return $this->count($this->key($clientIp)) < $this->limit;
    }

    public function recordRejection(string $clientIp): int
    {
        if ($clientIp === '') {
            return 0;
        }

        $key = $this->key($clientIp);
        $count = (int) $this->redis->incr($key);
        if ($count === 1) {
            $this->redis->expire($key, $this->windowSeconds);
        }

        return $count;
    }

    private function count(string $key): int
    {
        return (int) $this->redis->get($key);
    }

    private function key(string $clientIp): string
    {
        return $this->keyPrefix . 'abuse:' . hash_hmac(
            'sha256',
            'abuse|' . trim($clientIp),
            $this->saltProvider->currentSalt(),
        );
    }
}
