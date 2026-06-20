<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\from;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/*
 * Session authentication contract (SPEC §3.1 AUTH-01, §10.3). The login endpoint
 * is driven by Laravel's session guard via AuthenticateUserAction, with the
 * brute-force throttle persisted in login_attempts (the LoginThrottle service);
 * LoginData (Spatie Data) is the single source of validation truth. These tests
 * boot the app and run against PostgreSQL 18 via RefreshDatabase, which truncates
 * login_attempts per test — no manual reset needed.
 *
 * Web validation surfaces as a 302 redirect-back with session errors, never 422.
 */

it('authenticates a student and redirects to the student dashboard', function (): void {
    // Slice 007 re-points the student landing from student.status to the new
    // read-only overview home (RoleLandingRoute::for, CONTRACT §2).
    $user = User::factory()->student()->create([
        'email' => 'student@uniges.test',
        'password' => 'password',
    ]);

    post(route('login.store'), [
        'email' => 'student@uniges.test',
        'password' => 'password',
    ])->assertRedirect(route('student.dashboard'));

    assertAuthenticatedAs($user);
});

it('authenticates a staff admin and redirects to the admin dashboard', function (): void {
    // Slice 007 re-points the staff landing from admin.graduation.review to the
    // new admin dashboard (RoleLandingRoute::for, CONTRACT §2).
    $user = User::factory()->admin()->create([
        'email' => 'admin@uniges.test',
        'password' => 'password',
    ]);

    post(route('login.store'), [
        'email' => 'admin@uniges.test',
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    assertAuthenticatedAs($user);
});

it('redirects ancillary staff roles with no console to the public landing', function (UserRole $role): void {
    $user = User::factory()->create([
        'role' => $role,
        'email' => 'ancillary@uniges.test',
        'password' => 'password',
    ]);

    post(route('login.store'), [
        'email' => 'ancillary@uniges.test',
        'password' => 'password',
    ])->assertRedirect(route('landing'));

    assertAuthenticatedAs($user);
})->with([
    'assistant secretary' => [UserRole::AssistantSecretary],
    'school services' => [UserRole::SchoolServices],
]);

it('redirects a super admin to the same admin dashboard', function (): void {
    $user = User::factory()->superAdmin()->create([
        'email' => 'super@uniges.test',
        'password' => 'password',
    ]);

    post(route('login.store'), [
        'email' => 'super@uniges.test',
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    assertAuthenticatedAs($user);
});

it('redirects a secretary to the admin dashboard', function (): void {
    $user = User::factory()->secretary()->create([
        'email' => 'secretary@uniges.test',
        'password' => 'password',
    ]);

    post(route('login.store'), [
        'email' => 'secretary@uniges.test',
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    assertAuthenticatedAs($user);
});

it('rejects a wrong password with a 302 + session error on email and stays guest', function (): void {
    User::factory()->student()->create([
        'email' => 'student@uniges.test',
        'password' => 'password',
    ]);

    from(route('login'))
        ->post(route('login.store'), [
            'email' => 'student@uniges.test',
            'password' => 'wrong-password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    assertGuest();
});

it('rejects an unknown email with the same 302 + session error on email and stays guest', function (): void {
    from(route('login'))
        ->post(route('login.store'), [
            'email' => 'nobody@uniges.test',
            'password' => 'password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    assertGuest();
});

it('does not reveal whether the email exists: same error for wrong password and unknown email', function (): void {
    User::factory()->student()->create([
        'email' => 'student@uniges.test',
        'password' => 'password',
    ]);

    $expected = __('auth.failed');

    // A wrong password against an existing user flashes the generic message...
    from(route('login'))
        ->post(route('login.store'), [
            'email' => 'student@uniges.test',
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors(['email' => $expected]);

    session()->forget('errors');

    // ...and so does an email that does not exist at all — identical message,
    // so the response never leaks whether the account is registered.
    from(route('login'))
        ->post(route('login.store'), [
            'email' => 'nobody@uniges.test',
            'password' => 'password',
        ])
        ->assertSessionHasErrors(['email' => $expected]);
});

it('rejects a malformed email via LoginData with a 302 + session error, never a 422', function (): void {
    from(route('login'))
        ->post(route('login.store'), [
            'email' => 'not-an-email',
            'password' => 'password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    assertGuest();
});

it('rejects a too-short password via LoginData with a 302 + session error', function (): void {
    from(route('login'))
        ->post(route('login.store'), [
            'email' => 'student@uniges.test',
            'password' => 'short',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('password');

    assertGuest();
});

it('locks out after five failed attempts and surfaces a throttle error on email', function (): void {
    User::factory()->student()->create([
        'email' => 'a@b.test',
        'password' => 'password',
    ]);

    // Five failures consume the allowance (SPEC §10.3: 5 attempts → block).
    for ($attempt = 0; $attempt < 5; $attempt++) {
        post(route('login.store'), [
            'email' => 'a@b.test',
            'password' => 'wrong-password',
        ]);
    }

    // The sixth request is throttled even though the password is now correct.
    from(route('login'))
        ->post(route('login.store'), [
            'email' => 'a@b.test',
            'password' => 'password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    assertGuest();
});
