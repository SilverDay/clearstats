<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Domain;

final class SiteAccessPolicy
{
    /**
     * @param list<array{user_id: string, site_id: string, role: string}> $accessRecords
     */
    public function canAccess(string $userId, string $siteId, array $accessRecords): bool
    {
        foreach ($accessRecords as $record) {
            if (($record['user_id'] ?? null) !== $userId) {
                continue;
            }

            if (($record['site_id'] ?? null) !== $siteId) {
                continue;
            }

            $role = (string) ($record['role'] ?? '');

            if ($role === 'admin' || $role === 'editor') {
                return true;
            }

            return false;
        }

        return false;
    }
}
