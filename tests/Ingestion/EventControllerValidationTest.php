<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Ingestion;

use ClearStats\Domain\SiteAccessPolicy;
use ClearStats\Domain\SiteValidation;
use ClearStats\Ingestion\EventController;
use PHPUnit\Framework\TestCase;

final class EventControllerValidationTest extends TestCase
{
    public function testAcceptsMatchingSiteAndAccess(): void
    {
        $controller = new EventController(
            new SiteValidation(),
            new SiteAccessPolicy(),
        );

        $this->assertTrue($controller->canProcessRequest(
            'user-1',
            'site-1',
            'example.com',
            'example.com',
            [
                ['user_id' => 'user-1', 'site_id' => 'site-1', 'role' => 'admin'],
            ],
        ));
    }

    public function testRejectsDifferentHostForSameSite(): void
    {
        $controller = new EventController(
            new SiteValidation(),
            new SiteAccessPolicy(),
        );

        $this->assertFalse($controller->canProcessRequest(
            'user-1',
            'site-1',
            'example.com',
            'other.com',
            [
                ['user_id' => 'user-1', 'site_id' => 'site-1', 'role' => 'admin'],
            ],
        ));
    }

    public function testRejectsMissingAccessRecords(): void
    {
        $controller = new EventController(
            new SiteValidation(),
            new SiteAccessPolicy(),
        );

        $this->assertFalse($controller->canProcessRequest(
            'user-1',
            'site-1',
            'example.com',
            'example.com',
            [],
        ));
    }

    public function testRejectsUnauthorizedUser(): void
    {
        $controller = new EventController(
            new SiteValidation(),
            new SiteAccessPolicy(),
        );

        $this->assertFalse($controller->canProcessRequest(
            'user-2',
            'site-1',
            'example.com',
            'example.com',
            [
                ['user_id' => 'user-1', 'site_id' => 'site-1', 'role' => 'admin'],
            ],
        ));
    }
}
