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

    public function allow(string $email, string $ipAddress = ''): bool
    {
        return $this->count($this->emailKey($email)) < $this->limit
            && ($ipAddress === '' || $this->count($this->ipKey($ipAddress)) < $this->limit);
    }

    public function recordFailure(string $email, string $ipAddress = ''): int
    {
        $count = $this->increment($this->emailKey($email));
        if ($ipAddress !== '') {
            $this->increment($this->ipKey($ipAddress));
        }

        return $count;
    }

    private function increment(string $key): int
    {
        $count = $this->redis->incr($key);
        if ($count === false) {
            throw new \RuntimeException('Failed to record authentication failure.');
        }
        if ($count === 1 && !$this->redis->expire($key, $this->windowSeconds)) {
            throw new \RuntimeException('Failed to expire authentication failure counter.');
        }

        return (int) $count;
    }

    private function count(string $key): int
    {
        return (int) $this->redis->get($key);
    }

    private function emailKey(string $email): string
    {
        return $this->keyPrefix . 'auth:failures:email:' . hash('sha256', strtolower(trim($email)));
    }

    private function ipKey(string $ipAddress): string
    {
        return $this->keyPrefix . 'auth:failures:ip:' . hash('sha256', trim($ipAddress));
    }
}
