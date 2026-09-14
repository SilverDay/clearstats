<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Ingestion;

use PDO;

/**
 * Provides the current daily HMAC salt, per docs/analytics-platform-spec.md §3.2.
 *
 * - Rotated every 24h by a scheduled job.
 * - Never exposed via any API response, never logged, never sent to any client.
 * - Must handle the current + previous salt to avoid split-second boundary
 *   issues right after rotation (see spec §3.2 for the recommended approach:
 *   bucket by day using the salt's generation timestamp, not wall-clock).
 *
 * The production provider stores current/previous state in MariaDB and rotates
 * when the configured age is exceeded. Tests may inject a fixed salt.
 */
final class SaltProvider
{
    private ?string $processSalt = null;

    public function __construct(
        private readonly ?string $fixedSalt = null,
        private readonly ?PDO $pdo = null,
        private readonly int $rotationHours = 24,
    ) {}

    public function currentSalt(): string
    {
        if ($this->fixedSalt !== null) {
            return $this->fixedSalt;
        }
        if ($this->pdo === null) {
            return $this->processSalt ??= bin2hex(random_bytes(32));
        }

        $row = $this->pdo->query('SELECT current_salt, generated_at FROM salt_state WHERE id = 1')->fetch();
        if (!is_array($row)) {
            $salt = bin2hex(random_bytes(32));
            $statement = $this->pdo->prepare('INSERT INTO salt_state (id, current_salt, previous_salt, generated_at) VALUES (1, :current_salt, NULL, :generated_at)');
            $statement->execute(['current_salt' => $salt, 'generated_at' => gmdate('Y-m-d H:i:s')]);
            return $salt;
        }

        $generatedAt = new \DateTimeImmutable((string) $row['generated_at'], new \DateTimeZone('UTC'));
        if (time() - $generatedAt->getTimestamp() >= max(1, $this->rotationHours) * 3600) {
            $this->rotate();
            $row = $this->pdo->query('SELECT current_salt FROM salt_state WHERE id = 1')->fetch();
        }

        return (string) ($row['current_salt'] ?? '');
    }

    public function rotate(): void
    {
        if ($this->fixedSalt !== null) {
            return;
        }
        if ($this->pdo === null) {
            $this->processSalt = bin2hex(random_bytes(32));
            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE salt_state SET previous_salt = current_salt, current_salt = :current_salt, generated_at = :generated_at WHERE id = 1',
        );
        $statement->execute([
            'current_salt' => bin2hex(random_bytes(32)),
            'generated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
