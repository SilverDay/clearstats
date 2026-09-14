<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Http;

use ClearStats\Db\Database;
use ClearStats\Db\MigrationRunner;
use ClearStats\Http\DatabaseAuthenticator;
use ClearStats\Tests\TestDatabase;
use PHPUnit\Framework\TestCase;

final class DatabaseAuthenticatorTest extends TestCase
{
    public function testAuthenticatesUserWithStoredPasswordHash(): void
    {
        $database = TestDatabase::create();

        $email = 'auth-test-' . bin2hex(random_bytes(6)) . '@example.test';
        $password = 'correct horse battery staple';
        $statement = $database->pdo()->prepare(
            'INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)',
        );
        $statement->execute([
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'admin',
        ]);

        try {
            $authenticator = new DatabaseAuthenticator($database->pdo());

            $userId = $authenticator->authenticate($email, $password);

            $this->assertIsString($userId);
            $this->assertNotSame('', $userId);
            $this->assertNull($authenticator->authenticate($email, 'wrong password'));
        } finally {
            $cleanup = $database->pdo()->prepare('DELETE FROM users WHERE email = :email');
            $cleanup->execute(['email' => $email]);
        }
    }
}
