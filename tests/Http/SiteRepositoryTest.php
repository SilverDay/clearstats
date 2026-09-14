<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Http;

use ClearStats\Db\Database;
use ClearStats\Db\MigrationRunner;
use ClearStats\Http\SiteRepository;
use ClearStats\Tests\TestDatabase;
use PHPUnit\Framework\TestCase;

final class SiteRepositoryTest extends TestCase
{
    public function testCreatesCanonicalSiteAndOwnerAccessAndListsOnlyAssignedSites(): void
    {
        $database = TestDatabase::create();

        $pdo = $database->pdo();
        $ownerEmail = 'site-owner-' . bin2hex(random_bytes(6)) . '@example.test';
        $otherEmail = 'site-other-' . bin2hex(random_bytes(6)) . '@example.test';
        $userStatement = $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (:email, :password_hash)');
        $userStatement->execute(['email' => $ownerEmail, 'password_hash' => 'not-used']);
        $ownerId = (string) $pdo->lastInsertId();
        $userStatement->execute(['email' => $otherEmail, 'password_hash' => 'not-used']);
        $otherId = (string) $pdo->lastInsertId();

        $repository = new SiteRepository($pdo);
        $site = $repository->create('Example site', 'WWW.Example.com.', $ownerId, 'direct', 45);

        try {
            $this->assertSame('example.com', $site['domain']);
            $this->assertSame($site['id'], (string) $pdo->query("SELECT id FROM sites WHERE domain = 'example.com'")->fetchColumn());
            $this->assertCount(1, $repository->forUser($ownerId));
            $this->assertCount(0, $repository->forUser($otherId));
            $this->assertSame('admin', $repository->forUser($ownerId)[0]['role']);
        } finally {
            $pdo->prepare('DELETE FROM user_site_access WHERE site_id = :site_id')->execute(['site_id' => $site['id']]);
            $pdo->prepare('DELETE FROM sites WHERE id = :site_id')->execute(['site_id' => $site['id']]);
            $pdo->prepare('DELETE FROM users WHERE id IN (:owner_id, :other_id)')->execute([
                'owner_id' => $ownerId,
                'other_id' => $otherId,
            ]);
        }
    }

    public function testNonAdminCannotCreateSite(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $email = 'site-viewer-' . bin2hex(random_bytes(6)) . '@example.test';
        $statement = $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)');
        $statement->execute(['email' => $email, 'password_hash' => 'not-used', 'role' => 'viewer']);
        $userId = (string) $pdo->lastInsertId();

        try {
            $this->expectException(\RuntimeException::class);
            (new SiteRepository($pdo))->create('Forbidden site', 'forbidden.example.test', $userId);
        } finally {
            $pdo->prepare('DELETE FROM users WHERE id = :user_id')->execute(['user_id' => $userId]);
        }
    }

    public function testAdminCanUpdateSiteAndNormalizeDomain(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $email = 'site-editor-' . bin2hex(random_bytes(6)) . '@example.test';
        $statement = $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)');
        $statement->execute(['email' => $email, 'password_hash' => 'not-used', 'role' => 'admin']);
        $userId = (string) $pdo->lastInsertId();
        $siteId = 'site-edit-' . bin2hex(random_bytes(6));
        $pdo->prepare('INSERT INTO sites (id, name, domain, owner_user_id) VALUES (:id, :name, :domain, :owner_user_id)')->execute([
            'id' => $siteId,
            'name' => 'Before edit',
            'domain' => 'before.example.test',
            'owner_user_id' => $userId,
        ]);
        $pdo->prepare('INSERT INTO user_site_access (user_id, site_id, role) VALUES (:user_id, :site_id, :role)')->execute([
            'user_id' => $userId,
            'site_id' => $siteId,
            'role' => 'admin',
        ]);

        try {
            $repository = new SiteRepository($pdo);
            $repository->update($userId, $siteId, 'After edit', 'WWW.After.example.test.', 'x-forwarded-for', 90, false);
            $site = $repository->findForAdmin($userId, $siteId);

            $this->assertSame('After edit', $site['name']);
            $this->assertSame('after.example.test', $site['domain']);
            $this->assertSame('x-forwarded-for', $site['ip_source']);
            $this->assertSame('90', (string) $site['raw_event_retention_days']);
            $this->assertSame('0', (string) $site['active']);
        } finally {
            $pdo->prepare('DELETE FROM user_site_access WHERE site_id = :site_id')->execute(['site_id' => $siteId]);
            $pdo->prepare('DELETE FROM sites WHERE id = :site_id')->execute(['site_id' => $siteId]);
            $pdo->prepare('DELETE FROM users WHERE id = :user_id')->execute(['user_id' => $userId]);
        }
    }
}
