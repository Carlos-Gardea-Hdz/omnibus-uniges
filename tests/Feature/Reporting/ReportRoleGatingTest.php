<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/*
 * RBAC gating for the five report routes (spec 008 §3 scenario 10, §2.1). Every
 * report sits behind the established ['auth','demo','role:admin,super_admin,
 * secretary'] group — identical to admin.dashboard:
 *   - a guest is bounced to /login with a 302 (never 403 — that is reserved for
 *     a wrong-role authenticated user from EnsureRole);
 *   - student / assistant_secretary / school_services → 403;
 *   - admin / super_admin / secretary → 200.
 * Boots the app + PostgreSQL 18 (RefreshDatabase).
 */

/** The five report routes under the staff gate. */
function reportRoutes(): array
{
    return [
        'index' => ['admin.reports.index'],
        'graduates' => ['admin.reports.graduates'],
        'terminal-efficiency' => ['admin.reports.terminal-efficiency'],
        'cohorts' => ['admin.reports.cohorts'],
        'judge-certificates' => ['admin.reports.judge-certificates'],
    ];
}

it('redirects a guest to login (302, not 403) on every report route', function (string $routeName): void {
    get(route($routeName))
        ->assertRedirect(route('login'))
        ->assertStatus(302);
})->with(reportRoutes());

it('forbids a student on every report route with a 403, not a redirect', function (string $routeName): void {
    actingAs(User::factory()->student()->create())
        ->get(route($routeName))
        ->assertForbidden();
})->with(reportRoutes());

it('forbids assistant_secretary on every report route with a 403', function (string $routeName): void {
    actingAs(User::factory()->assistantSecretary()->create())
        ->get(route($routeName))
        ->assertForbidden();
})->with(reportRoutes());

it('forbids school_services on every report route with a 403', function (string $routeName): void {
    actingAs(User::factory()->schoolServices()->create())
        ->get(route($routeName))
        ->assertForbidden();
})->with(reportRoutes());

it('grants every report route to admin', function (string $routeName): void {
    actingAs(User::factory()->admin()->create())
        ->get(route($routeName))
        ->assertOk();
})->with(reportRoutes());

it('grants every report route to super_admin', function (string $routeName): void {
    actingAs(User::factory()->superAdmin()->create())
        ->get(route($routeName))
        ->assertOk();
})->with(reportRoutes());

it('grants every report route to secretary', function (string $routeName): void {
    actingAs(User::factory()->secretary()->create())
        ->get(route($routeName))
        ->assertOk();
})->with(reportRoutes());
