<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Ingestion;

use PDO;

final class DatabaseSiteResolver implements SiteResolver
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public function resolve(string $siteId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, domain, ip_source FROM sites WHERE id = :site_id AND active = 1 LIMIT 1',
        );
        $statement->execute(['site_id' => $siteId]);
        $site = $statement->fetch();

        if (!is_array($site)) {
            return null;
        }

        return [
            'id' => (string) $site['id'],
            'domain' => (string) $site['domain'],
            'ip_source' => (string) $site['ip_source'],
        ];
    }
}
