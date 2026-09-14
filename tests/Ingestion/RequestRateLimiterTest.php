<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Ingestion;

use ClearStats\Ingestion\RequestRateLimiter;
use ClearStats\Ingestion\SaltProvider;
use PHPUnit\Framework\TestCase;

final class RequestRateLimiterTest extends TestCase
{
    public function testAllowsUpToLimitAndRejectsNextRequest(): void
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $prefix = 'clearstats:test-rate:';
        $limiter = new RequestRateLimiter($redis, new SaltProvider('test-rate-salt'), $prefix);

        $this->assertTrue($limiter->allow('site-rate', '203.0.113.10', 2));
        $this->assertTrue($limiter->allow('site-rate', '203.0.113.10', 2));
        $this->assertFalse($limiter->allow('site-rate', '203.0.113.10', 2));
        $this->assertTrue($limiter->allow('site-rate', '203.0.113.11', 2));

        $keys = $redis->keys($prefix . 'rate:*');
        $this->assertCount(2, $keys);
        $this->assertStringNotContainsString('203.0.113.10', implode(',', $keys));
        $redis->del(...$keys);
    }
}
