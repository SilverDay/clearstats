#!/usr/bin/env php
<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';
$date = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--date=')) {
        $candidate = substr($argument, 7);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $candidate, new DateTimeZone('UTC'));
        if ($parsed === false || $parsed->format('Y-m-d') !== $candidate) {
            fwrite(STDERR, "rollup.php: --date must use YYYY-MM-DD\n");
            exit(2);
        }
        $date = $candidate;
    }
}

$database = new ClearStats\Db\Database($config['db']);
$pdo = $database->pdo();
$rollup = new ClearStats\Rollup\StatsRollup($database);
$sites = $pdo->query('SELECT id FROM sites WHERE active = 1 ORDER BY id')->fetchAll();

foreach ($sites as $site) {
    $rollup->aggregateDay((string) $site['id'], $date);
}

$purged = $rollup->purgeExpiredRawEvents();
fwrite(STDOUT, sprintf(
    "rollup.php: aggregated %d site(s) for %s; purged %d raw event(s)\n",
    count($sites),
    $date,
    $purged,
));
