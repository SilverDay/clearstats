#!/usr/bin/env php
<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';
$limit = 100;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--limit=')) {
        $limit = (int) substr($argument, 8);
    }
}

if ($limit < 1) {
    fwrite(STDERR, "queue-worker.php: --limit must be positive\n");
    exit(2);
}

$database = new ClearStats\Db\Database($config['db']);
$redisConfig = $config['redis'];
$redis = new Redis();
$redis->connect((string) $redisConfig['host'], (int) $redisConfig['port']);
if ($redisConfig['database'] !== null) {
    $redis->select((int) $redisConfig['database']);
}

$queue = new ClearStats\Ingestion\EventQueue($redis);
$worker = new ClearStats\Ingestion\QueueWorker($database->pdo(), $queue);
$processed = $worker->processBatch($limit);

fwrite(STDOUT, sprintf(
    "queue-worker.php: processed %d event(s); queued=%d processing=%d\n",
    $processed,
    $queue->pendingCount(),
    $queue->processingCount(),
));
