<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Http;

use ClearStats\Http\AuthFailureMonitor;
use PHPUnit\Framework\TestCase;

final class AuthFailureMonitorTest extends TestCase
{
    public function testThrottlesRepeatedFailuresWithoutRawEmailInKey(): void
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $prefix = 'clearstats:test-auth:';
        $monitor = new AuthFailureMonitor($redis, $prefix, 2, 60);
        $email = 'failure-' . bin2hex(random_bytes(6)) . '@example.test';

        $this->assertTrue($monitor->allow($email));
        $this->assertSame(1, $monitor->recordFailure($email));
        $this->assertTrue($monitor->allow($email));
        $this->assertSame(2, $monitor->recordFailure($email));
        $this->assertFalse($monitor->allow($email));
        $keys = $redis->keys($prefix . 'auth:failures:*');
        $this->assertCount(1, $keys);
        $this->assertStringNotContainsString($email, $keys[0]);
        $redis->del(...$keys);
    }
}
