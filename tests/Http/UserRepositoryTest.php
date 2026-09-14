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

final class UserRepositoryTest extends TestCase
{
    public function testOnlyAdminCanListUsersAndSiteCountsAreIncluded(): void
    {
        $database = TestDatabase::create();

        $pdo = $database->pdo();
        $email = 'user-admin-' . bin2hex(random_bytes(6)) . '@example.test';
        $otherEmail = 'user-viewer-' . bin2hex(random_bytes(6)) . '@example.test';
        $insertUser = $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)');
        $insertUser->execute(['email' => $email, 'password_hash' => 'not-used', 'role' => 'admin']);
        $adminId = (string) $pdo->lastInsertId();
        $insertUser->execute(['email' => $otherEmail, 'password_hash' => 'not-used', 'role' => 'viewer']);
        $viewerId = (string) $pdo->lastInsertId();
        $siteId = 'user-test-' . bin2hex(random_bytes(8));

        $pdo->prepare('INSERT INTO sites (id, name, domain, owner_user_id) VALUES (:id, :name, :domain, :owner_user_id)')->execute([
            'id' => $siteId,
            'name' => 'User test site',
            'domain' => $siteId . '.example.test',
            'owner_user_id' => $adminId,
        ]);
        $pdo->prepare('INSERT INTO user_site_access (user_id, site_id, role) VALUES (:user_id, :site_id, :role)')->execute([
            'user_id' => $adminId,
            'site_id' => $siteId,
            'role' => 'admin',
        ]);

        try {
            $repository = new UserRepository($pdo);
            $users = $repository->allForAdmin($adminId);

            $this->assertTrue($repository->isAdmin($adminId));
            $this->assertFalse($repository->isAdmin($viewerId));
            $userIds = array_map(static fn(array $user): string => (string) $user['id'], $users);
            $this->assertContains($adminId, $userIds);
            $this->assertContains($viewerId, $userIds);
            $adminRow = array_values(array_filter($users, static fn(array $user): bool => (string) $user['id'] === $adminId))[0];
            $this->assertSame('1', (string) $adminRow['site_count']);
            $this->assertSame([], $repository->allForAdmin($viewerId));
        } finally {
            $pdo->prepare('DELETE FROM user_site_access WHERE site_id = :site_id')->execute(['site_id' => $siteId]);
            $pdo->prepare('DELETE FROM sites WHERE id = :site_id')->execute(['site_id' => $siteId]);
            $pdo->prepare('DELETE FROM users WHERE id IN (:admin_id, :viewer_id)')->execute([
                'admin_id' => $adminId,
                'viewer_id' => $viewerId,
            ]);
        }
    }
}
