<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Ingestion;

use ClearStats\Db\Database;
use ClearStats\Db\MigrationRunner;
use ClearStats\Ingestion\DatabaseSiteResolver;
use ClearStats\Tests\TestDatabase;
use PHPUnit\Framework\TestCase;

final class DatabaseSiteResolverTest extends TestCase
{
    public function testResolvesOnlyActiveSiteConfiguration(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $activeId = 'resolver-active-' . bin2hex(random_bytes(6));
        $inactiveId = 'resolver-inactive-' . bin2hex(random_bytes(6));
        $email = 'resolver-owner-' . bin2hex(random_bytes(6)) . '@example.test';
        $user = $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)');
        $user->execute(['email' => $email, 'password_hash' => 'not-used', 'role' => 'admin']);
        $userId = (string) $pdo->lastInsertId();

        $insert = $pdo->prepare('INSERT INTO sites (id, name, domain, owner_user_id, ip_source, active) VALUES (:id, :name, :domain, :owner_user_id, :ip_source, :active)');
        $insert->execute(['id' => $activeId, 'name' => 'Resolver active', 'domain' => 'active.example.test', 'owner_user_id' => $userId, 'ip_source' => 'direct', 'active' => 1]);
        $insert->execute(['id' => $inactiveId, 'name' => 'Resolver inactive', 'domain' => 'inactive.example.test', 'owner_user_id' => $userId, 'ip_source' => 'direct', 'active' => 0]);

        try {
            $resolver = new DatabaseSiteResolver($pdo);
            $this->assertSame([
                'id' => $activeId,
                'domain' => 'active.example.test',
                'ip_source' => 'direct',
            ], $resolver->resolve($activeId));
            $this->assertNull($resolver->resolve($inactiveId));
            $this->assertNull($resolver->resolve('missing-site'));
        } finally {
            $pdo->prepare('DELETE FROM sites WHERE id IN (:active_id, :inactive_id)')->execute([
                'active_id' => $activeId,
                'inactive_id' => $inactiveId,
            ]);
            $pdo->prepare('DELETE FROM users WHERE id = :user_id')->execute(['user_id' => $userId]);
        }
    }
}
