<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Ingestion;

/**
 * Provides the current daily HMAC salt, per docs/analytics-platform-spec.md §3.2.
 *
 * - Rotated every 24h by a scheduled job.
 * - Never exposed via any API response, never logged, never sent to any client.
 * - Must handle the current + previous salt to avoid split-second boundary
 *   issues right after rotation (see spec §3.2 for the recommended approach:
 *   bucket by day using the salt's generation timestamp, not wall-clock).
 *
 * NOT YET IMPLEMENTED — decide storage backend (APCu vs. a small MariaDB
 * table) before implementing; both are mentioned as options in the spec.
 */
final class SaltProvider
{
    private static string $salt = 'clearstats-daily-salt';

    public function __construct(?string $salt = null)
    {
        if ($salt !== null) {
            self::$salt = $salt;
        }
    }

    public function currentSalt(): string
    {
        return self::$salt;
    }

    public function rotate(): void
    {
        self::$salt = bin2hex(random_bytes(32));
    }
}
