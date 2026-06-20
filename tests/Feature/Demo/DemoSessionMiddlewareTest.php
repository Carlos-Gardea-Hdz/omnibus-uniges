<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Domain\Identity\Enums\DemoPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertGuest;

uses(RefreshDatabase::class);

/*
 * DemoSessionMiddleware behavior (CONTRACT §9, spec §2.4 / scenarios 4-7). The
 * middleware wraps the authenticated pipeline route groups via the `demo` alias.
 * For a demo session it: (1) logs the visitor out + 302 → landing with
 * error=demo.expired once now() passes demo_expires_at; (2) slides
 * demo_expires_at forward on a live request; (3) blocks the folio-minting
 * graduate (8→9) route with a 302 + error=demo.blocked, never advancing status
 * nor minting a folio. For a NON-demo authenticated user it is a strict no-op.
 * PostgreSQL 18 via RefreshDatabase; web responses are 302 + session flash/errors.
 *
 * The demo session is crafted directly (a tagged demo user + the is_demo session
 * keys) so each behavior is isolated from the provisioning/login path.
 */

/**
 * A demo user tagged with a fresh session id, plus the session payload the
 * DemoSessionMiddleware reads. Returns [user, sessionId, sessionPayload].
 *
 * @return array{0: User, 1: string, 2: array<string, mixed>}
 */
function demoSession(DemoPreset $preset, int $expiresInMinutes = 30): array
{
    $sessionId = (string) Str::uuid7();

    $user = User::factory()->create(['role' => $preset->role()]);
    $user->forceFill(['demo_session_id' => $sessionId])->save();

    $payload = [
        'is_demo' => true,
        'demo_session_id' => $sessionId,
        'demo_preset' => $preset->value,
        'demo_expires_at' => now()->addMinutes($expiresInMinutes)->timestamp,
    ];

    return [$user, $sessionId, $payload];
}

it('is a strict no-op for a non-demo authenticated user', function (): void {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->get(route('admin.graduation.review'))
        ->assertOk();

    // No demo session was ever established, so nothing about the real user changes.
    assertAuthenticated();
    expect(session('is_demo'))->toBeNull();
});

it('logs out an expired demo session and redirects to landing with the expired flash', function (): void {
    [$admin, , $payload] = demoSession(DemoPreset::Admin);
    // The TTL has already elapsed for this request.
    $payload['demo_expires_at'] = now()->subMinute()->timestamp;

    actingAs($admin)
        ->withSession($payload)
        ->get(route('admin.graduation.review'))
        ->assertRedirect(route('landing'))
        ->assertSessionHas('error', __('demo.expired'));

    assertGuest();
});

it('slides the demo expiry forward on a live in-window request', function (): void {
    [$admin, , $payload] = demoSession(DemoPreset::Admin);
    // An expiry that is valid now but close, so a slide is observable.
    $payload['demo_expires_at'] = now()->addMinutes(2)->timestamp;

    actingAs($admin)
        ->withSession($payload)
        ->get(route('admin.graduation.review'))
        ->assertOk()
        // Slid to ~30 minutes out — comfortably past the original 2-minute mark.
        ->assertSessionHas(
            'demo_expires_at',
            fn (int $value): bool => $value >= now()->addMinutes(20)->timestamp,
        );

    assertAuthenticated();
});

it('blocks the folio-minting graduate route in demo without advancing the student', function (): void {
    [$admin, $sessionId, $payload] = demoSession(DemoPreset::Admin);

    $student = Student::factory()->ceremonyPassed()->create();
    $student->forceFill(['demo_session_id' => $sessionId])->save();

    actingAs($admin)
        ->withSession($payload)
        ->post(route('admin.graduation.ceremony.graduate', $student))
        ->assertRedirect()
        ->assertSessionHas('error', __('demo.blocked'));

    $student->refresh();

    // No graduation occurred and no diploma folio was minted.
    expect($student->status)->toBe(GraduationStatus::CeremonyScheduled)
        ->and($student->diploma_folio)->toBeNull()
        ->and($student->graduation_date)->toBeNull();
});

it('still allows a non-destructive demo request through the same middleware', function (): void {
    [$admin, , $payload] = demoSession(DemoPreset::Admin);

    // The review queue is read-only and must remain reachable inside the sandbox.
    actingAs($admin)
        ->withSession($payload)
        ->get(route('admin.graduation.review'))
        ->assertOk();

    assertAuthenticated();
});
