<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Ingestion;

use ClearStats\Db\Database;
use ClearStats\Db\MigrationRunner;
use ClearStats\Ingestion\EventQueue;
use ClearStats\Ingestion\QueueWorker;
use ClearStats\Tests\TestDatabase;
use PHPUnit\Framework\TestCase;

final class QueueWorkerTest extends TestCase
{
    public function testProcessesQueuedEventBeforeAcknowledgingIt(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $pdo->exec("DELETE FROM events_raw WHERE site_id = 'worker-test-site'");

        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->del('clearstats:events', 'clearstats:events:processing');
        $queue = new EventQueue($redis);
        $queue->push([
            'site_id' => 'worker-test-site',
            'visitor_hash' => str_repeat('a', 64),
            'event_type' => 'pageview',
            'event_name' => '',
            'url_path' => '/worker',
            'referrer_domain' => 'example.test',
            'country_code' => '',
            'device_type' => 'desktop',
            'browser' => 'unknown',
            'created_at' => '2026-09-14T12:00:00Z',
        ]);

        $worker = new QueueWorker($pdo, $queue);
        $processed = $worker->processBatch(10);

        $row = $pdo->query("SELECT url_path, created_at FROM events_raw WHERE site_id = 'worker-test-site'")->fetch();
        $this->assertSame(1, $processed);
        $this->assertSame('/worker', $row['url_path']);
        $this->assertSame('2026-09-14 12:00:00', $row['created_at']);
        $this->assertSame(0, (int) $redis->llen('clearstats:events'));
        $this->assertSame(0, (int) $redis->llen('clearstats:events:processing'));

        $pdo->exec("DELETE FROM events_raw WHERE site_id = 'worker-test-site'");
    }

    public function testDuplicateEventIdIsInsertedOnlyOnce(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $pdo->exec("DELETE FROM events_raw WHERE site_id = 'duplicate-test-site'");
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->del('clearstats:events', 'clearstats:events:processing');
        $queue = new EventQueue($redis);
        $event = [
            'event_id' => str_repeat('e', 64),
            'site_id' => 'duplicate-test-site',
            'visitor_hash' => str_repeat('f', 64),
            'event_type' => 'pageview',
            'url_path' => '/duplicate',
            'created_at' => '2026-09-14T12:00:00Z',
        ];
        $queue->push($event);
        $queue->push($event);

        try {
            $this->assertSame(2, (new QueueWorker($pdo, $queue))->processBatch(10));
            $count = $pdo->query("SELECT COUNT(*) FROM events_raw WHERE site_id = 'duplicate-test-site'")->fetchColumn();
            $this->assertSame('1', (string) $count);
        } finally {
            $pdo->exec("DELETE FROM events_raw WHERE site_id = 'duplicate-test-site'");
        }
    }

    public function testRecoversReservedEventAfterWorkerRestart(): void
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->del('clearstats:events', 'clearstats:events:processing');
        $queue = new EventQueue($redis);
        $queue->push(['site_id' => 'recovery-site', 'visitor_hash' => str_repeat('d', 64)]);

        $this->assertNotNull($queue->reserve());
        $this->assertSame(1, $queue->recoverProcessing());
        $this->assertSame(1, $queue->pendingCount());
        $this->assertSame(0, (int) $redis->llen('clearstats:events:processing'));
        $redis->del('clearstats:events', 'clearstats:events:processing');
    }

    public function testRejectsMalformedEventAndContinuesWithLaterValidEvent(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->del('clearstats:events', 'clearstats:events:processing', 'clearstats:events:dead-letter');
        $queue = new EventQueue($redis);
        $redis->lPush('clearstats:events', '{malformed');
        $queue->push([
            'site_id' => 'after-poison-site',
            'visitor_hash' => str_repeat('b', 64),
            'event_type' => 'pageview',
            'url_path' => '/after-poison',
            'created_at' => '2026-09-14T12:00:00Z',
        ]);

        try {
            $this->assertSame(1, (new QueueWorker($pdo, $queue))->processBatch(10));
            $this->assertSame(1, (int) $redis->lLen('clearstats:events:dead-letter'));
            $this->assertSame(0, (int) $redis->lLen('clearstats:events:processing'));
            $this->assertSame('/after-poison', $pdo->query("SELECT url_path FROM events_raw WHERE site_id = 'after-poison-site'")->fetchColumn());
        } finally {
            $pdo->exec("DELETE FROM events_raw WHERE site_id = 'after-poison-site'");
            $redis->del('clearstats:events', 'clearstats:events:processing', 'clearstats:events:dead-letter');
        }
    }
}
