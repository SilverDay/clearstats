<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Domain;

use ClearStats\Domain\SiteAccessPolicy;
use PHPUnit\Framework\TestCase;

final class SiteAccessPolicyTest extends TestCase
{
    public function testAllowsAccessWhenUserHasSiteRole(): void
    {
        $policy = new SiteAccessPolicy();

        $this->assertTrue($policy->canAccess('user-1', 'site-1', [
            ['user_id' => 'user-1', 'site_id' => 'site-1', 'role' => 'admin'],
        ]));
    }

    public function testRejectsAccessWhenUserDoesNotHaveSiteRole(): void
    {
        $policy = new SiteAccessPolicy();

        $this->assertFalse($policy->canAccess('user-1', 'site-2', [
            ['user_id' => 'user-1', 'site_id' => 'site-1', 'role' => 'admin'],
        ]));
    }

    public function testRejectsAccessWhenUserRoleIsNotAllowed(): void
    {
        $policy = new SiteAccessPolicy();

        $this->assertFalse($policy->canAccess('user-1', 'site-1', [
            ['user_id' => 'user-1', 'site_id' => 'site-1', 'role' => 'viewer'],
        ]));
    }
}
