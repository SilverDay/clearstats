<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Db;

final class MigrationRunner
{
    public function __construct(
        private readonly Database $database,
    ) {}

    public function run(string $sqlFile): void
    {
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new \RuntimeException('Unable to read migration file: ' . $sqlFile);
        }

        $this->database->pdo()->exec($sql);
    }
}
