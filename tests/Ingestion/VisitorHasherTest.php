<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Ingestion;

use PHPUnit\Framework\TestCase;

/**
 * Per /CLAUDE.md: a fixed salt/domain/ip/ua -> known expected hash test is
 * required once VisitorHasher is implemented, so a future refactor can't
 * silently desync hashing logic (relevant once/if any hashing logic is
 * duplicated elsewhere — currently centralized here, which is intentional).
 */
final class VisitorHasherTest extends TestCase
{
    public function testHashIsDeterministicForFixedInputs(): void
    {
        $saltProvider = new \ClearStats\Ingestion\SaltProvider('daily-salt-123');
        $hasher = new \ClearStats\Ingestion\VisitorHasher($saltProvider);

        $siteDomain = 'example.com';
        $clientIp = '203.0.113.10';
        $userAgent = 'Mozilla/5.0 (X11; Linux x86_64)';

        $expected = hash_hmac(
            'sha256',
            $siteDomain . '|' . $clientIp . '|' . $userAgent,
            'daily-salt-123',
        );

        $this->assertSame($expected, $hasher->hash($siteDomain, $clientIp, $userAgent));
    }

    public function testSameVisitorKeepsOneHashPerRollupDay(): void
    {
        $database = \ClearStats\Tests\TestDatabase::create();
        $hasher = new \ClearStats\Ingestion\VisitorHasher(
            new \ClearStats\Ingestion\SaltProvider(null, $database->pdo(), 24),
        );

        $morning = (int) strtotime('2026-03-10 07:15:00 UTC');
        $evening = (int) strtotime('2026-03-10 22:40:00 UTC');
        $nextDay = (int) strtotime('2026-03-11 09:00:00 UTC');

        $first = $hasher->hash('example.com', '203.0.113.10', 'UA', $morning);
        $second = $hasher->hash('example.com', '203.0.113.10', 'UA', $evening);
        $third = $hasher->hash('example.com', '203.0.113.10', 'UA', $nextDay);

        $this->assertSame($first, $second, 'One visitor must not be counted twice within a rollup day.');
        $this->assertNotSame($second, $third, 'The hash must not be stable across days.');
    }
}
