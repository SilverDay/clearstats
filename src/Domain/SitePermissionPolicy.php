<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Domain;

final class SitePermissionPolicy
{
    /**
     * Admin users can manage site configuration and assignments.
     */
    public function canManageSite(string $role): bool
    {
        return $role === 'admin';
    }

    /**
     * Editor users can view dashboard data but not user management.
     */
    public function canViewDashboard(string $role): bool
    {
        return in_array($role, ['admin', 'editor', 'viewer'], true);
    }

    /**
     * Only admin users may manage other users and roles.
     */
    public function canManageUsers(string $role): bool
    {
        return $role === 'admin';
    }
}
