<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/*
 * Logout contract (SPEC §3.1 AUTH-06): the session guard is cleared, the
 * session is invalidated and the CSRF token regenerated, then the user is
 * redirected to the login screen. Boots the app + PostgreSQL 18.
 */

it('logs the authenticated user out and redirects to login', function (): void {
    $user = User::factory()->admin()->create();

    actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    assertGuest();
});

it('logs a student out and redirects to login', function (): void {
    $user = User::factory()->student()->create();

    actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    assertGuest();
});

it('rotates the CSRF token on logout so the old session token no longer applies', function (): void {
    $user = User::factory()->admin()->create();

    actingAs($user);
    $tokenBefore = session()->token();

    post(route('logout'))->assertRedirect(route('login'));

    expect(session()->token())->not->toBe($tokenBefore);
    assertGuest();
});

it('forbids logout for a guest by routing them through the auth gate, never a 403', function (): void {
    post(route('logout'))
        ->assertRedirect(route('login'))
        ->assertStatus(302);
});
