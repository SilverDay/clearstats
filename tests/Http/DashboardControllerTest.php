<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Http;

use ClearStats\Http\DashboardController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class DashboardControllerTest extends TestCase
{
    public function testFormatsDurationsWithoutWrappingAtOneHour(): void
    {
        $method = new ReflectionMethod(DashboardController::class, 'formatDuration');
        $method->setAccessible(true);
        $controller = new DashboardController();

        $this->assertSame('59:59', $method->invoke($controller, 3599));
        $this->assertSame('1:00:00', $method->invoke($controller, 3600));
        $this->assertSame('24:00:00', $method->invoke($controller, 86400));
    }
}