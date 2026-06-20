<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\DemoPreset;
use App\Domain\Identity\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

/*
 * Demo-login rate limit (CONTRACT §8, spec §2.5 / scenario 3). A named Laravel
 * RateLimiter keyed by IP allows 10 demo logins per hour; the 11th is refused as
 * a 302 redirect-back with a session error on `preset` (NEVER a 429 JSON — the
 * project web-validation convention). It is a SEPARATE limiter from the per-email
 * LoginThrottle (login_attempts table) and must not consume its ledger. The
 * cache-backed limiter is reset per test by the array cache store + app reboot;
 * a clear() guards against any cross-test bleed. PostgreSQL 18 via RefreshDatabase.
 */

beforeEach(function (): void {
    RateLimiter::clear('demo-login:198.51.100.10');
    RateLimiter::clear('demo-login:203.0.113.20');
});

/** POST a demo login from a fixed source IP. */
function demoLoginFrom(string $ip, DemoPreset $preset = DemoPreset::Admin)
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->post(route('demo.store'), ['preset' => $preset->value]);
}

it('allows ten demo logins from one IP within the hour', function (): void {
    for ($i = 0; $i < 10; $i++) {
        demoLoginFrom('198.51.100.10')->assertRedirect();
    }

    expect(User::query()->count())->toBe(10);
});

it('refuses the eleventh demo login from the same IP with a 302 + session error on preset', function (): void {
    for ($i = 0; $i < 10; $i++) {
        demoLoginFrom('198.51.100.10')->assertRedirect();
    }

    demoLoginFrom('198.51.100.10')
        ->assertRedirect()
        ->assertSessionHasErrors('preset');

    // The eleventh attempt minted no user — the limiter blocks before provisioning.
    expect(User::query()->count())->toBe(10);
});

it('does not surface the rate limit as a 429 JSON response', function (): void {
    for ($i = 0; $i < 10; $i++) {
        demoLoginFrom('198.51.100.10')->assertRedirect();
    }

    // Web path must be a friendly redirect, never the default 429 throttle JSON.
    demoLoginFrom('198.51.100.10')->assertStatus(302);
});

it('keys the limiter by IP — a different IP is unaffected', function (): void {
    for ($i = 0; $i < 10; $i++) {
        demoLoginFrom('198.51.100.10')->assertRedirect();
    }

    // The blocked IP is exhausted, but a fresh IP still gets its full allowance.
    demoLoginFrom('198.51.100.10')->assertSessionHasErrors('preset');
    demoLoginFrom('203.0.113.20')->assertRedirect()->assertSessionHasNoErrors();

    expect(User::query()->count())->toBe(11);
});

it('never touches the per-email LoginThrottle ledger', function (): void {
    for ($i = 0; $i < 11; $i++) {
        demoLoginFrom('198.51.100.10');
    }

    // Demo logins are password-less and per-IP — the per-email brute-force ledger
    // (login_attempts) is a SEPARATE limiter and stays empty (CONTRACT §8).
    expect(LoginAttempt::query()->count())->toBe(0);
});
