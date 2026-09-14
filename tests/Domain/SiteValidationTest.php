<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Domain;

use ClearStats\Domain\SiteValidation;
use PHPUnit\Framework\TestCase;

final class SiteValidationTest extends TestCase
{
    public function testAcceptsCanonicalHostAndWwwEquivalent(): void
    {
        $validator = new SiteValidation();

        $this->assertTrue($validator->isValidSiteDomain('example.com', 'example.com'));
        $this->assertTrue($validator->isValidSiteDomain('example.com', 'www.example.com'));
        $this->assertTrue($validator->isValidSiteDomain('www.example.com', 'example.com'));
    }

    public function testRejectsDifferentDomain(): void
    {
        $validator = new SiteValidation();

        $this->assertFalse($validator->isValidSiteDomain('example.com', 'other.com'));
    }
}
