<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Ingestion;

use ClearStats\Ingestion\BotDetector;
use PHPUnit\Framework\TestCase;

final class BotDetectorTest extends TestCase
{
    public function testRecognizesCommonAutomatedUserAgents(): void
    {
        $detector = new BotDetector();

        $this->assertTrue($detector->isLikelyBot('Mozilla/5.0 Googlebot/2.1'));
        $this->assertTrue($detector->isLikelyBot('Mozilla/5.0 HeadlessChrome/120'));
        $this->assertFalse($detector->isLikelyBot('Mozilla/5.0 AppleWebKit/605.1.15 Safari/17.0'));
    }
}
