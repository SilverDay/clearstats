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
}
