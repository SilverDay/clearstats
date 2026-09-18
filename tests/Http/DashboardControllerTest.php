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

    public function testResolveRangeHandlesEachPreset(): void
    {
        $method = new ReflectionMethod(DashboardController::class, 'resolveRange');
        $method->setAccessible(true);
        $controller = new DashboardController();
        $today = '2026-09-18';

        [$start, $end, $label, $days] = $method->invoke($controller, 'today', $today, null, null);
        $this->assertSame(['2026-09-18', '2026-09-18', 'Today', 1], [$start, $end, $label, $days]);

        [$start, $end, $label, $days] = $method->invoke($controller, '7d', $today, null, null);
        $this->assertSame(['2026-09-12', '2026-09-18', 'Last 7 days', 7], [$start, $end, $label, $days]);

        [$start, $end, $label, $days] = $method->invoke($controller, '30d', $today, null, null);
        $this->assertSame('2026-08-20', $start);
        $this->assertSame(30, $days);

        [$start, $end, $label, $days] = $method->invoke($controller, 'month', $today, null, null);
        $this->assertSame('2026-09-01', $start);
        $this->assertSame(18, $days);

        [$start, $end, $label, $days] = $method->invoke($controller, 'custom', $today, '2026-09-01', '2026-09-05');
        $this->assertSame(['2026-09-01', '2026-09-05'], [$start, $end]);
        $this->assertSame(5, $days);

        // An invalid/missing custom start falls back to the requested end date.
        [$start, $end] = $method->invoke($controller, 'custom', $today, 'not-a-date', '2026-09-05');
        $this->assertSame(['2026-09-05', '2026-09-05'], [$start, $end]);
    }
}
