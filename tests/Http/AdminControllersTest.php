<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Http;

use ClearStats\Http\AuthController;
use ClearStats\Http\DashboardController;
use PHPUnit\Framework\TestCase;

final class AdminControllersTest extends TestCase
{
    public function testAuthLoginPageRendersExpectedMarkup(): void
    {
        $controller = new AuthController();

        ob_start();
        $controller->login();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('ClearStats Login', $output);
        $this->assertStringContainsString('Login', $output);
    }

    public function testDashboardPageRendersExpectedMarkup(): void
    {
        $controller = new DashboardController();

        ob_start();
        $controller->index();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('ClearStats Dashboard', $output);
        $this->assertStringContainsString('Dashboard', $output);
        $this->assertStringContainsString('Pageviews', $output);
    }

    public function testForgotPasswordPageDoesNotLoopBackToLogin(): void
    {
        $controller = new AuthController();

        ob_start();
        $controller->forgotPassword();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Forgot password?', $output);
        $this->assertStringContainsString('action="/forgot-password"', $output);
    }
}
