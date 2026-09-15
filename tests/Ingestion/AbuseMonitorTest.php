<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Ingestion;

use ClearStats\Ingestion\AbuseMonitor;
use ClearStats\Ingestion\SaltProvider;
use PHPUnit\Framework\TestCase;

final class AbuseMonitorTest extends TestCase
{
    private \Redis $redis;

    protected function setUp(): void
    {
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1', 6379);
        $this->clearKeys();
    }

    protected function tearDown(): void
    {
        $this->clearKeys();
    }

    private function clearKeys(): void
    {
        $keys = $this->redis->keys('clearstats:abuse:*');
        if (is_array($keys) && $keys !== []) {
            $this->redis->del(...$keys);
        }
    }

    private function monitor(int $limit = 3): AbuseMonitor
    {
        return new AbuseMonitor($this->redis, new SaltProvider('test-salt'), 'clearstats:', $limit, 900);
    }

    public function testBlocksASourceOnlyAfterTheLimitIsReached(): void
    {
        $monitor = $this->monitor(3);

        $this->assertTrue($monitor->allow('203.0.113.10'));

        $monitor->recordRejection('203.0.113.10');
        $monitor->recordRejection('203.0.113.10');
        $this->assertTrue($monitor->allow('203.0.113.10'), 'Below the limit the source must still be served.');

        $monitor->recordRejection('203.0.113.10');
        $this->assertFalse($monitor->allow('203.0.113.10'));
    }

    public function testBlockingOneSourceLeavesOthersUnaffected(): void
    {
        $monitor = $this->monitor(2);

        $monitor->recordRejection('203.0.113.10');
        $monitor->recordRejection('203.0.113.10');

        $this->assertFalse($monitor->allow('203.0.113.10'));
        $this->assertTrue($monitor->allow('198.51.100.7'));
    }

    public function testCounterExpiresSoTheBlockLiftsByItself(): void
    {
        $monitor = $this->monitor(1);
        $monitor->recordRejection('203.0.113.10');

        $keys = $this->redis->keys('clearstats:abuse:*');
        $this->assertCount(1, $keys);
        $this->assertGreaterThan(0, $this->redis->ttl($keys[0]), 'A block must expire rather than persist.');
    }

    public function testRawIpIsNeverUsedAsAKey(): void
    {
        $monitor = $this->monitor();
        $monitor->recordRejection('203.0.113.10');

        foreach ((array) $this->redis->keys('clearstats:abuse:*') as $key) {
            $this->assertStringNotContainsString('203.0.113.10', (string) $key);
        }
    }

    public function testDisabledLimitNeverBlocks(): void
    {
        $monitor = $this->monitor(0);
        $monitor->recordRejection('203.0.113.10');

        $this->assertTrue($monitor->allow('203.0.113.10'));
    }
}
