<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Http;

use ClearStats\Domain\DomainNormalizer;
use PDO;
use RuntimeException;

final class SiteRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(string $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.id, s.name, s.domain, s.ip_source, s.raw_event_retention_days, s.active,
                    s.track_outbound_links, s.track_file_downloads, s.track_404, usa.role
             FROM sites s
             INNER JOIN user_site_access usa ON usa.site_id = s.id
             WHERE usa.user_id = :user_id
             ORDER BY s.name ASC',
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    /**
     * @return array{id: string, name: string, domain: string, owner_user_id: string}
     */
    public function create(
        string $name,
        string $domain,
        string $ownerUserId,
        string $ipSource = 'direct',
        int $retentionDays = 30,
        bool $trackOutboundLinks = false,
        bool $trackFileDownloads = false,
        bool $track404 = false,
    ): array {
        if (!$this->isAdmin($ownerUserId)) {
            throw new RuntimeException('Only administrators can create sites.');
        }

        $name = trim($name);
        $domain = DomainNormalizer::normalize($domain);

        if ($name === '' || $domain === '' || $ownerUserId === '') {
            throw new RuntimeException('Site name, domain, and owner are required.');
        }

        if (!in_array($ipSource, ['direct', 'x-forwarded-for', 'cf-connecting-ip'], true)) {
            throw new RuntimeException('Invalid IP source.');
        }

        if ($retentionDays < 1 || $retentionDays > 3650) {
            throw new RuntimeException('Retention days must be between 1 and 3650.');
        }

        $siteId = bin2hex(random_bytes(16));

        $this->pdo->beginTransaction();
        try {
            $site = $this->pdo->prepare(
                'INSERT INTO sites (id, name, domain, owner_user_id, ip_source, raw_event_retention_days, track_outbound_links, track_file_downloads, track_404)
                 VALUES (:id, :name, :domain, :owner_user_id, :ip_source, :retention_days, :track_outbound_links, :track_file_downloads, :track_404)',
            );
            $site->execute([
                'id' => $siteId,
                'name' => $name,
                'domain' => $domain,
                'owner_user_id' => $ownerUserId,
                'ip_source' => $ipSource,
                'retention_days' => $retentionDays,
                'track_outbound_links' => $trackOutboundLinks ? 1 : 0,
                'track_file_downloads' => $trackFileDownloads ? 1 : 0,
                'track_404' => $track404 ? 1 : 0,
            ]);

            $access = $this->pdo->prepare(
                'INSERT INTO user_site_access (user_id, site_id, role)
                 VALUES (:user_id, :site_id, :role)',
            );
            $access->execute([
                'user_id' => $ownerUserId,
                'site_id' => $siteId,
                'role' => 'admin',
            ]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        return [
            'id' => $siteId,
            'name' => $name,
            'domain' => $domain,
            'owner_user_id' => $ownerUserId,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForAdmin(string $userId, string $siteId): ?array
    {
        if (!$this->isAdmin($userId)) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT s.id, s.name, s.domain, s.ip_source, s.raw_event_retention_days, s.active,
                    s.track_outbound_links, s.track_file_downloads, s.track_404
             FROM sites s
             INNER JOIN user_site_access usa ON usa.site_id = s.id
             WHERE s.id = :site_id AND usa.user_id = :user_id AND usa.role = \'admin\'
             LIMIT 1',
        );
        $statement->execute(['site_id' => $siteId, 'user_id' => $userId]);
        $site = $statement->fetch();

        return is_array($site) ? $site : null;
    }

    public function update(
        string $actingUserId,
        string $siteId,
        string $name,
        string $domain,
        string $ipSource,
        int $retentionDays,
        bool $active,
        bool $trackOutboundLinks = false,
        bool $trackFileDownloads = false,
        bool $track404 = false,
    ): void {
        if ($this->findForAdmin($actingUserId, $siteId) === null) {
            throw new RuntimeException('Only site administrators can edit this site.');
        }

        $name = trim($name);
        $domain = DomainNormalizer::normalize($domain);
        if ($name === '' || $domain === '') {
            throw new RuntimeException('Site name and domain are required.');
        }
        if (!in_array($ipSource, ['direct', 'x-forwarded-for', 'cf-connecting-ip'], true)) {
            throw new RuntimeException('Invalid IP source.');
        }
        if ($retentionDays < 1 || $retentionDays > 3650) {
            throw new RuntimeException('Retention days must be between 1 and 3650.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE sites
             SET name = :name, domain = :domain, ip_source = :ip_source,
                 raw_event_retention_days = :retention_days, active = :active,
                 track_outbound_links = :track_outbound_links, track_file_downloads = :track_file_downloads, track_404 = :track_404
             WHERE id = :site_id',
        );
        $statement->execute([
            'name' => $name,
            'domain' => $domain,
            'ip_source' => $ipSource,
            'retention_days' => $retentionDays,
            'active' => $active ? 1 : 0,
            'track_outbound_links' => $trackOutboundLinks ? 1 : 0,
            'track_file_downloads' => $trackFileDownloads ? 1 : 0,
            'track_404' => $track404 ? 1 : 0,
            'site_id' => $siteId,
        ]);
    }

    private function isAdmin(string $userId): bool
    {
        $statement = $this->pdo->prepare('SELECT role FROM users WHERE id = :user_id LIMIT 1');
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchColumn() === 'admin';
    }
}
