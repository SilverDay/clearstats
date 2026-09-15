<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Ingestion;

use PDO;

/**
 * Provides the daily HMAC salt, per docs/analytics-platform-spec.md §3.2.
 *
 * - Never exposed via any API response, never logged, never sent to any client.
 * - Rotation is aligned to fixed period boundaries (the UTC day by default)
 *   rather than "N hours since the last rotation", so every event inside one
 *   calendar day hashes with the same salt. Without that alignment a visitor
 *   seen either side of a rotation produces two hashes and inflates
 *   COUNT(DISTINCT visitor_hash) in the daily rollup.
 * - Callers pass the timestamp they will also store as created_at, so the salt
 *   and the rollup bucket cannot disagree across a boundary.
 *
 * A rotation_hours value other than 24 splits a calendar day across two salts
 * and reintroduces that inflation; 24 is the supported setting.
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
        return $this->saltForTimestamp(time());
    }

    /** Salt covering the rotation period that $timestamp falls in. */
    public function saltForTimestamp(int $timestamp): string
    {
        if ($this->fixedSalt !== null) {
            return $this->fixedSalt;
        }
        if ($this->pdo === null) {
            return $this->processSalt ??= bin2hex(random_bytes(32));
        }

        $row = $this->readState();
        if (!is_array($row)) {
            $this->initialise($timestamp);
            $row = $this->readState();
        }

        $generatedAt = new \DateTimeImmutable((string) $row['generated_at'], new \DateTimeZone('UTC'));
        if ($this->period($timestamp) !== $this->period($generatedAt->getTimestamp())) {
            $this->rotateIfUnchanged((string) $row['generated_at'], $timestamp);
            $row = $this->readState();
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

    private function period(int $timestamp): int
    {
        return intdiv($timestamp, max(1, $this->rotationHours) * 3600);
    }

    private function readState(): mixed
    {
        $statement = $this->pdo?->query('SELECT current_salt, generated_at FROM salt_state WHERE id = 1');

        return $statement === false || $statement === null ? false : $statement->fetch();
    }

    private function initialise(int $timestamp): void
    {
        $sql = $this->pdo?->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO salt_state (id, current_salt, previous_salt, generated_at) VALUES (1, :current_salt, NULL, :generated_at) ON CONFLICT(id) DO NOTHING'
            : 'INSERT INTO salt_state (id, current_salt, previous_salt, generated_at) VALUES (1, :current_salt, NULL, :generated_at) ON DUPLICATE KEY UPDATE id = id';

        $statement = $this->pdo?->prepare($sql);
        $statement?->execute([
            'current_salt' => bin2hex(random_bytes(32)),
            'generated_at' => gmdate('Y-m-d H:i:s', $timestamp),
        ]);
    }

    private function rotateIfUnchanged(string $generatedAt, int $timestamp): void
    {
        $statement = $this->pdo?->prepare(
            'UPDATE salt_state SET previous_salt = current_salt, current_salt = :current_salt, generated_at = :new_generated_at WHERE id = 1 AND generated_at = :expected_generated_at',
        );
        $statement?->execute([
            'current_salt' => bin2hex(random_bytes(32)),
            'new_generated_at' => gmdate('Y-m-d H:i:s', $timestamp),
            'expected_generated_at' => $generatedAt,
        ]);
    }
}
