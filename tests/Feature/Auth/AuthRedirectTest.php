<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/*
 * Access-control redirect contract (SPEC §7, §10.1). A guest that reaches an
 * authenticated route is bounced to the login screen — a 302, never a 403
 * (only a *wrong-role* authenticated user gets the 403 from EnsureRole). An
 * already-authenticated user that hits /login is kept off the guest-only
 * screen. Boots the app + PostgreSQL 18.
 */

it('redirects a guest hitting a student route to login, not a 403', function (): void {
    get(route('student.status'))
        ->assertRedirect(route('login'))
        ->assertStatus(302);
});

it('redirects a guest hitting a staff route to login, not a 403', function (): void {
    get(route('admin.graduation.review'))
        ->assertRedirect(route('login'))
        ->assertStatus(302);
});

it('keeps an authenticated student off the login screen with a 302 redirect', function (): void {
    $user = User::factory()->student()->create();

    actingAs($user)
        ->get(route('login'))
        ->assertRedirect()
        ->assertStatus(302);
});

it('keeps an authenticated staff member off the login screen with a 302 redirect', function (): void {
    $user = User::factory()->admin()->create();

    actingAs($user)
        ->get(route('login'))
        ->assertRedirect()
        ->assertStatus(302);
});

it('serves the login screen to a guest with a 200', function (): void {
    get(route('login'))->assertOk();
});

it('returns a 403, not a redirect, when an authenticated student reaches a staff route', function (): void {
    $user = User::factory()->student()->create();

    actingAs($user)
        ->get(route('admin.graduation.review'))
        ->assertForbidden();
});
