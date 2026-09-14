<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Domain;

use ClearStats\Domain\DomainNormalizer;
use PHPUnit\Framework\TestCase;

final class DomainNormalizerTest extends TestCase
{
    public function testNormalizesLeadingWww(): void
    {
        $this->assertSame('example.com', DomainNormalizer::normalize('www.example.com'));
        $this->assertSame('example.com', DomainNormalizer::normalize('example.com'));
    }

    public function testNormalizesCaseAndTrailingDot(): void
    {
        $this->assertSame('example.com', DomainNormalizer::normalize('WWW.EXAMPLE.COM.'));
    }

    public function testTreatsWwwAsSameSite(): void
    {
        $this->assertTrue(DomainNormalizer::matches('example.com', 'www.example.com'));
        $this->assertTrue(DomainNormalizer::matches('WWW.EXAMPLE.COM.', 'example.com'));
        $this->assertFalse(DomainNormalizer::matches('example.com', 'other.com'));
    }
}
