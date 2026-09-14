<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Db;

use ClearStats\Db\Database;
use ClearStats\Tests\TestDatabase;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    public function testCreatesPdoConnectionFromConfig(): void
    {
        $database = TestDatabase::create();

        $this->assertSame(1, (int) $database->pdo()->query('SELECT 1')->fetchColumn());
    }

    public function testRunsInitialSchemaMigration(): void
    {
        $database = TestDatabase::create();

        $this->assertNotFalse($database->pdo()->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'sites'")->fetchColumn());
        $this->assertNotFalse($database->pdo()->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn());
    }
}
