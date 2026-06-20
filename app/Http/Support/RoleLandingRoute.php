<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Domain\Identity\Enums\UserRole;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

/**
 * Single source of truth for the post-authentication landing route of each
 * {@see UserRole} (SPEC §10.1 RBAC).
 *
 * Extracted from {@see AuthenticatedSessionController}
 * so the real-login path and the demo-login path resolve a role's entry screen
 * through the SAME match — the mapping is never duplicated. The match is
 * exhaustive over the 6-level RBAC enum, so adding a role is a compile-time
 * obligation here.
 */
final class RoleLandingRoute
{
    public static function for(UserRole $role): string
    {
        return match ($role) {
            UserRole::Student => 'student.dashboard',
            UserRole::Admin, UserRole::SuperAdmin, UserRole::Secretary => 'admin.dashboard',
            UserRole::AssistantSecretary, UserRole::SchoolServices => 'landing',
        };
    }
}
