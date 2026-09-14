<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Http;

use PDO;

final class DatabaseAuthenticator implements Authenticator
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public function authenticate(string $email, string $password): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT id, password_hash FROM users WHERE email = :email LIMIT 1',
        );
        $statement->execute(['email' => strtolower(trim($email))]);
        $user = $statement->fetch();

        if (!is_array($user) || !is_string($user['password_hash'] ?? null)) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        return (string) $user['id'];
    }
}
