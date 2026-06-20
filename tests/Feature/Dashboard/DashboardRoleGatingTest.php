<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/*
 * RBAC gating for the two dashboards (CONTRACT §1, §8.4; SPEC §10.1).
 *
 * Both dashboards sit behind the established ['auth', 'demo', 'role:*'] groups:
 *   - student.dashboard  → role:student
 *   - admin.dashboard    → role:admin,super_admin,secretary
 *
 * A guest is bounced to /login with a 302 (never a 403 — that is reserved for a
 * *wrong-role* authenticated user from EnsureRole). assistant_secretary and
 * school_services are deliberately NOT granted the admin dashboard this slice
 * (they land on `landing`), so they must hit 403. Boots the app + PostgreSQL 18.
 */

it('redirects a guest hitting the student dashboard to login (302, not 403)', function (): void {
    get(route('student.dashboard'))
        ->assertRedirect(route('login'))
        ->assertStatus(302);
});

it('redirects a guest hitting the admin dashboard to login (302, not 403)', function (): void {
    get(route('admin.dashboard'))
        ->assertRedirect(route('login'))
        ->assertStatus(302);
});

it('serves the student dashboard to a student with a 200', function (): void {
    actingAs(User::factory()->student()->create())
        ->get(route('student.dashboard'))
        ->assertOk();
});

it('forbids a non-student (admin) on the student dashboard with a 403', function (): void {
    actingAs(User::factory()->admin()->create())
        ->get(route('student.dashboard'))
        ->assertForbidden();
});

it('forbids a student on the admin dashboard with a 403, not a redirect', function (): void {
    actingAs(User::factory()->student()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

it('grants the admin dashboard to admin, super_admin and secretary', function (string $state): void {
    actingAs(User::factory()->{$state}()->create())
        ->get(route('admin.dashboard'))
        ->assertOk();
})->with([
    'admin' => ['admin'],
    'super_admin' => ['superAdmin'],
    'secretary' => ['secretary'],
]);

it('forbids assistant_secretary and school_services on the admin dashboard with a 403', function (string $state): void {
    actingAs(User::factory()->{$state}()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
})->with([
    'assistant_secretary' => ['assistantSecretary'],
    'school_services' => ['schoolServices'],
]);
