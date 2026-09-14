<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Http;

use ClearStats\Db\Database;
use ClearStats\Db\MigrationRunner;
use ClearStats\Http\UserRepository;
use ClearStats\Tests\TestDatabase;
use PHPUnit\Framework\TestCase;

final class UserRepositoryMutationTest extends TestCase
{
    public function testAdminCanCreateUserAndAssignSiteRole(): void
    {
        $database = TestDatabase::create();

        $pdo = $database->pdo();
        $insertUser = $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)');
        $adminEmail = 'mutation-admin-' . bin2hex(random_bytes(6)) . '@example.test';
        $insertUser->execute(['email' => $adminEmail, 'password_hash' => 'not-used', 'role' => 'admin']);
        $adminId = (string) $pdo->lastInsertId();
        $siteId = 'mutation-site-' . bin2hex(random_bytes(8));
        $pdo->prepare('INSERT INTO sites (id, name, domain, owner_user_id) VALUES (:id, :name, :domain, :owner_user_id)')->execute([
            'id' => $siteId,
            'name' => 'Mutation test site',
            'domain' => $siteId . '.example.test',
            'owner_user_id' => $adminId,
        ]);

        $repository = new UserRepository($pdo);
        $email = 'mutation-user-' . bin2hex(random_bytes(6)) . '@example.test';
        $createdId = $repository->create($adminId, $email, 'a sufficiently long password', 'editor');

        try {
            $this->assertNotSame('', $createdId);
            $hash = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
            $hash->execute(['id' => $createdId]);
            $this->assertTrue(password_verify('a sufficiently long password', (string) $hash->fetchColumn()));

            $repository->assignSite($adminId, $createdId, $siteId, 'editor');
            $access = $pdo->prepare('SELECT role FROM user_site_access WHERE user_id = :user_id AND site_id = :site_id');
            $access->execute(['user_id' => $createdId, 'site_id' => $siteId]);
            $this->assertSame('editor', $access->fetchColumn());
        } finally {
            $pdo->prepare('DELETE FROM user_site_access WHERE site_id = :site_id')->execute(['site_id' => $siteId]);
            $pdo->prepare('DELETE FROM sites WHERE id = :site_id')->execute(['site_id' => $siteId]);
            $pdo->prepare('DELETE FROM users WHERE id = :admin_id OR email = :email')->execute([
                'admin_id' => $adminId,
                'email' => $email,
            ]);
        }
    }

    public function testUserCanChangePasswordOnlyWithCurrentPassword(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $email = 'password-change-' . bin2hex(random_bytes(6)) . '@example.test';
        $statement = $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)');
        $statement->execute(['email' => $email, 'password_hash' => password_hash('old password 123', PASSWORD_DEFAULT), 'role' => 'viewer']);
        $userId = (string) $pdo->lastInsertId();

        try {
            $repository = new UserRepository($pdo);
            $this->expectException(\RuntimeException::class);
            $repository->changePassword($userId, 'wrong password', 'new password 123');
        } finally {
            $pdo->prepare('DELETE FROM users WHERE id = :user_id')->execute(['user_id' => $userId]);
        }
    }
}
