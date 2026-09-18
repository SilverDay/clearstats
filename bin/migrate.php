#!/usr/bin/env php
<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

/**
 * bin/migrate.php — applies pending SQL migrations from migrations/, tracked
 * in the schema_migrations table.
 *
 *   php bin/migrate.php            # apply everything pending
 *   php bin/migrate.php --status   # list applied/pending without running anything
 *
 * Each file is a plain .sql file named `NNNN_description.sql` (zero-padded,
 * sorts correctly), applied in order and never edited once committed and
 * applied anywhere — a schema change after the fact is a new numbered file,
 * not an edit to an existing one. (migrations/0001_initial_schema.sql
 * predates this script and was grown in place with idempotent
 * `ADD COLUMN IF NOT EXISTS` blocks; treat that as closed history now — the
 * next schema change is 0002_....)
 *
 * MariaDB DDL causes an implicit commit per statement, so a migration file
 * is NOT atomic across multiple statements — keep each file to one focused
 * change. If a file fails partway through, it is NOT marked applied;
 * inspect the database by hand, fix forward with a new migration file, and
 * re-run — never re-edit a file that may have partially applied.
 *
 * Adoption: on the very first run against a database that predates this
 * script (tables already exist, schema_migrations is empty), the baseline
 * migration (0001) is recorded as already applied rather than re-executed.
 * --status stays read-only: it reports what adoption *would* do without
 * writing anything.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use ClearStats\Db\Database;
use ClearStats\Db\MigrationRunner;

$config = require dirname(__DIR__) . '/config/config.php';
$database = new Database($config['db']);
$pdo = $database->pdo();
$runner = new MigrationRunner($database);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(255) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (migration)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4',
);

$migrationsDir = dirname(__DIR__) . '/migrations';
$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files, SORT_STRING);

/** @var list<string> $appliedList */
$appliedList = array_map('strval', $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
$applied = array_flip($appliedList);

$statusOnly = in_array('--status', $argv, true);

// Adopt an existing, pre-migrations database: if nothing has been recorded
// yet but the baseline's tables are already present, mark the baseline
// applied without re-running its CREATE TABLE / ADD COLUMN statements.
if ($applied === [] && $files !== []) {
    $baseline = basename($files[0]);
    $exists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sites'",
    )->fetchColumn();

    if ($exists > 0 && $statusOnly) {
        printf("[adopt-pending] %s -- tables already exist; will be recorded as applied (not re-run) on next non-status run\n", $baseline);
        $applied[$baseline] = true;
    } elseif ($exists > 0) {
        $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)')->execute([$baseline]);
        $applied[$baseline] = true;
        printf("[adopt] %s already applied on this database -- recording without re-running\n", $baseline);
    }
}

$ranAny = false;

foreach ($files as $file) {
    $name = basename($file);

    if ($statusOnly) {
        printf("%s  %s\n", isset($applied[$name]) ? '[applied]' : '[pending]', $name);

        continue;
    }

    if (isset($applied[$name])) {
        continue;
    }

    printf("[migrate] applying %s\n", $name);

    try {
        $runner->run($file);
    } catch (\Throwable $e) {
        fwrite(STDERR, "FATAL: $name failed: " . $e->getMessage() . "\n");
        fwrite(STDERR, "Not marked as applied. Inspect the database, fix forward with a new migration, and re-run.\n");
        exit(1);
    }

    $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)')->execute([$name]);
    $ranAny = true;
}

if ($statusOnly) {
    exit(0);
}

echo $ranAny ? "done: migrations applied\n" : "done: nothing to apply\n";
exit(0);
