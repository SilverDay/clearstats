<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Ingestion;

use ClearStats\Ingestion\EventQueue;
use PHPUnit\Framework\TestCase;

final class EventQueueTest extends TestCase
{
    public function testPushAddsEventToRedisQueue(): void
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->del('clearstats:events');

        $queue = new EventQueue($redis);
        $queue->push([
            'site_id' => 'site-1',
            'visitor_hash' => 'abc123',
            'event_type' => 'pageview',
            'url_path' => '/home',
            'created_at' => '2026-09-14T00:00:00Z',
        ]);

        $payload = $redis->lpop('clearstats:events');

        $this->assertNotFalse($payload);

        $decoded = json_decode((string) $payload, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('site-1', $decoded['site_id']);
        $this->assertSame('pageview', $decoded['event_type']);
        $this->assertSame('/home', $decoded['url_path']);
    }
}
