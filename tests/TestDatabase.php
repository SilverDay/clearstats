<?php

declare(strict_types=1);

namespace ClearStats\Tests;

use ClearStats\Db\Database;

final class TestDatabase
{
    public static function create(): Database
    {
        $database = new Database([
            'dsn' => 'sqlite::memory:',
            'user' => '',
            'password' => '',
        ]);
        $schema = file_get_contents(__DIR__ . '/sqlite-schema.sql');
        if ($schema === false) {
            throw new \RuntimeException('Unable to read SQLite test schema.');
        }
        $database->pdo()->exec($schema);

        return $database;
    }
}
