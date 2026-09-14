<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Http;

final class AuthFailureMonitor
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $keyPrefix = 'clearstats:',
        private readonly int $limit = 10,
        private readonly int $windowSeconds = 900,
    ) {}

    public function allow(string $email): bool
    {
        $key = $this->keyPrefix . 'auth:failures:' . hash('sha256', strtolower(trim($email)));
        $count = (int) $this->redis->get($key);
        return $count < $this->limit;
    }

    public function recordFailure(string $email): int
    {
        $key = $this->keyPrefix . 'auth:failures:' . hash('sha256', strtolower(trim($email)));
        $count = $this->redis->incr($key);
        if ($count === false) {
            throw new \RuntimeException('Failed to record authentication failure.');
        }
        if ($count === 1 && !$this->redis->expire($key, $this->windowSeconds)) {
            throw new \RuntimeException('Failed to expire authentication failure counter.');
        }

        return (int) $count;
    }
}
