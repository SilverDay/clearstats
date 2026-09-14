<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Ingestion;

final class RequestRateLimiter
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly SaltProvider $saltProvider = new SaltProvider(),
        private readonly string $keyPrefix = 'clearstats:',
    ) {}

    public function allow(string $siteId, string $clientIp, int $limit): bool
    {
        if ($limit < 1) {
            return false;
        }

        $ipHash = hash_hmac('sha256', $siteId . '|' . $clientIp, $this->saltProvider->currentSalt());
        $key = $this->keyPrefix . 'rate:' . $ipHash;
        $count = $this->redis->incr($key);
        if ($count === false) {
            throw new \RuntimeException('Failed to update ingestion rate limit.');
        }

        if ($count === 1 && !$this->redis->expire($key, 60)) {
            throw new \RuntimeException('Failed to expire ingestion rate limit.');
        }

        return $count <= $limit;
    }
}
