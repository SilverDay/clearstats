<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Http;

use PDO;
use RuntimeException;

final class UserRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public function isAdmin(string $userId): bool
    {
        $statement = $this->pdo->prepare('SELECT role FROM users WHERE id = :user_id LIMIT 1');
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchColumn() === 'admin';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allForAdmin(string $userId): array
    {
        if (!$this->isAdmin($userId)) {
            return [];
        }

        $statement = $this->pdo->query(
            'SELECT u.id, u.email, u.role, COUNT(usa.site_id) AS site_count
             FROM users u
             LEFT JOIN user_site_access usa ON usa.user_id = u.id
             GROUP BY u.id, u.email, u.role
             ORDER BY u.email ASC',
        );

        return $statement->fetchAll();
    }

    public function create(string $actingUserId, string $email, string $password, string $role = 'viewer'): string
    {
        if (!$this->isAdmin($actingUserId)) {
            throw new RuntimeException('Only administrators can create users.');
        }

        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('A valid email address is required.');
        }

        if (strlen($password) < 12) {
            throw new RuntimeException('Passwords must contain at least 12 characters.');
        }

        if (!in_array($role, ['admin', 'editor', 'viewer'], true)) {
            throw new RuntimeException('Invalid user role.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash, role)
             VALUES (:email, :password_hash, :role)',
        );
        $statement->execute([
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
        ]);

        return (string) $this->pdo->lastInsertId();
    }

    public function changePassword(string $userId, string $currentPassword, string $newPassword): void
    {
        $statement = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = :user_id LIMIT 1');
        $statement->execute(['user_id' => $userId]);
        $hash = $statement->fetchColumn();

        if (!is_string($hash) || !password_verify($currentPassword, $hash)) {
            throw new RuntimeException('Current password is incorrect.');
        }
        if (strlen($newPassword) < 12) {
            throw new RuntimeException('Passwords must contain at least 12 characters.');
        }

        $update = $this->pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :user_id');
        $update->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'user_id' => $userId,
        ]);
    }

    public function assignSite(string $actingUserId, string $userId, string $siteId, string $role): void
    {
        if (!$this->isAdmin($actingUserId)) {
            throw new RuntimeException('Only administrators can assign sites.');
        }

        if (!in_array($role, ['admin', 'editor', 'viewer'], true)) {
            throw new RuntimeException('Invalid site role.');
        }

        $sql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO user_site_access (user_id, site_id, role) VALUES (:user_id, :site_id, :role) ON CONFLICT(user_id, site_id) DO UPDATE SET role = excluded.role'
            : 'INSERT INTO user_site_access (user_id, site_id, role) VALUES (:user_id, :site_id, :role) ON DUPLICATE KEY UPDATE role = VALUES(role)';
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'user_id' => $userId,
            'site_id' => $siteId,
            'role' => $role,
        ]);
    }

    /**
     * @return list<array{id: string, name: string, domain: string}>
     */
    public function sitesForAdmin(string $userId): array
    {
        if (!$this->isAdmin($userId)) {
            return [];
        }

        $statement = $this->pdo->query('SELECT id, name, domain FROM sites ORDER BY name ASC');

        return $statement->fetchAll();
    }
}
