<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Domain;

use ClearStats\Domain\SitePermissionPolicy;
use PHPUnit\Framework\TestCase;

final class SitePermissionPolicyTest extends TestCase
{
    public function testAdminCanManageAndViewDashboard(): void
    {
        $policy = new SitePermissionPolicy();

        $this->assertTrue($policy->canManageSite('admin'));
        $this->assertTrue($policy->canViewDashboard('admin'));
    }

    public function testEditorCanViewDashboardButNotManageUsers(): void
    {
        $policy = new SitePermissionPolicy();

        $this->assertTrue($policy->canViewDashboard('editor'));
        $this->assertFalse($policy->canManageUsers('editor'));
    }

    public function testViewerCanOnlyViewDashboard(): void
    {
        $policy = new SitePermissionPolicy();

        $this->assertTrue($policy->canViewDashboard('viewer'));
        $this->assertFalse($policy->canManageSite('viewer'));
        $this->assertFalse($policy->canManageUsers('viewer'));
    }
}
