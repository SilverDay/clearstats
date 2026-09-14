<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Db;

use PDO;
use PDOException;

final class Database
{
    private PDO $pdo;

    /**
     * @param array{dsn: string, user: string, password: string} $config
     */
    public function __construct(array $config)
    {
        $this->pdo = new PDO(
            $config['dsn'],
            $config['user'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param list<string> $statements
     */
    public function executeStatements(array $statements): void
    {
        foreach ($statements as $statement) {
            $this->pdo->exec($statement);
        }
    }
}
