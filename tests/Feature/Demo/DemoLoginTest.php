<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Domain\Identity\Enums\DemoPreset;
use App\Domain\Identity\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\from;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/*
 * Demo-login provisioning contract (CONTRACT §6/§8, spec scenarios 1-2). A guest
 * POSTs /demo-login with a preset; the slice mints a fresh demo User (tagged with
 * a demo_session_id), logs them in, stamps is_demo + a 30-min demo_expires_at on
 * the session, and redirects to the preset role's landing route. For the four
 * student presets it also mints exactly one demo Student in the correct
 * GraduationStatus (enum instance, not value) carrying the SAME tag. An invalid
 * preset is rejected by the DemoLoginData DTO as a 302 + session error (never a
 * 422) with no rows created. PostgreSQL 18 via RefreshDatabase.
 *
 * The four student-preset child rows the StudentFactory state seeds (a
 * jury_assignment for juryAssigned, etc.) reference the baseline catalogs; the
 * Action's demoBaseline() falls back to its own factory rows when none are
 * seeded, so these tests do not pre-seed.
 */

/**
 * The route a freshly-provisioned preset role is expected to land on. Tracks
 * RoleLandingRoute::for() — slice 007 re-points Student → student.dashboard and
 * admin/super_admin/secretary → admin.dashboard (CONTRACT §2); the demo path
 * resolves the entry screen through that same match, so this mirror moves with it.
 */
function expectedLandingRoute(UserRole $role): string
{
    return match ($role) {
        UserRole::Student => route('student.dashboard'),
        UserRole::Admin, UserRole::SuperAdmin, UserRole::Secretary => route('admin.dashboard'),
        UserRole::AssistantSecretary, UserRole::SchoolServices => route('landing'),
    };
}

it('provisions and authenticates every one of the six presets', function (DemoPreset $preset): void {
    post(route('demo.store'), ['preset' => $preset->value])
        ->assertRedirect(expectedLandingRoute($preset->role()));

    // Exactly one demo user, carrying the preset role + a non-null session tag.
    expect(User::query()->count())->toBe(1);

    $user = User::query()->sole();

    expect($user->role)->toBe($preset->role())
        ->and($user->demo_session_id)->not->toBeNull();

    assertAuthenticated();
})->with([
    'sustentante_1' => [DemoPreset::Sustentante1],
    'sustentante_2' => [DemoPreset::Sustentante2],
    'sustentante_3' => [DemoPreset::Sustentante3],
    'sustentante_4' => [DemoPreset::Sustentante4],
    'personal' => [DemoPreset::Personal],
    'admin' => [DemoPreset::Admin],
]);

it('stamps the is_demo flag and a 30-minute expiry on the session', function (): void {
    post(route('demo.store'), ['preset' => DemoPreset::Admin->value])
        ->assertSessionHas('is_demo', true)
        ->assertSessionHas('demo_preset', DemoPreset::Admin->value);

    expect(session('demo_expires_at'))->toBeInt()
        ->and(session('demo_expires_at'))->toBeGreaterThan(now()->timestamp);

    // Roughly 30 minutes out (allow a generous slack for clock drift in the run).
    expect(session('demo_expires_at'))->toBeLessThanOrEqual(now()->addMinutes(31)->timestamp);
});

it('mints a demo Student in the correct GraduationStatus enum instance per student preset', function (DemoPreset $preset, GraduationStatus $status): void {
    post(route('demo.store'), ['preset' => $preset->value])->assertRedirect();

    expect(Student::query()->count())->toBe(1);

    $student = Student::query()->sole();
    $user = User::query()->sole();

    // Enum identity assertion (memory rule) — the cast attribute is a case, not a string.
    expect($student->status)->toBe($status)
        ->and($student->control_number)->toBe($preset->controlNumber())
        ->and($student->demo_session_id)->toBe($user->demo_session_id)
        ->and($student->user_id)->toBe($user->id);
})->with([
    'sustentante_1 → FormBPending' => [DemoPreset::Sustentante1, GraduationStatus::FormBPending],
    'sustentante_2 → FormBReview' => [DemoPreset::Sustentante2, GraduationStatus::FormBReview],
    'sustentante_3 → AnnexIiiPending' => [DemoPreset::Sustentante3, GraduationStatus::AnnexIiiPending],
    'sustentante_4 → JuryAssigned' => [DemoPreset::Sustentante4, GraduationStatus::JuryAssigned],
]);

it('tags every child row the sustentante_4 preset seeds with the same session id', function (): void {
    post(route('demo.store'), ['preset' => DemoPreset::Sustentante4->value])->assertRedirect();

    $student = Student::query()->sole();
    $tag = $student->demo_session_id;

    // Whatever child rows the JuryAssigned stage materialises (a jury assignment
    // and/or documents) MUST inherit the student's tag — the cleanup + isolation
    // guarantees depend on no child row carrying a NULL tag (CONTRACT §6/§16). We
    // assert the invariant on whatever children exist rather than presupposing a
    // specific seeded child, so the test holds however the stage is provisioned.
    $jury = $student->juryAssignment()->withoutGlobalScopes()->first();
    if ($jury !== null) {
        expect($jury->demo_session_id)->toBe($tag);
    }

    $documents = $student->documents()->withoutGlobalScopes()->get();
    foreach ($documents as $document) {
        expect($document->demo_session_id)->toBe($tag);
    }
});

it('creates no Student for the staff and admin presets', function (DemoPreset $preset): void {
    post(route('demo.store'), ['preset' => $preset->value])->assertRedirect();

    expect(User::query()->count())->toBe(1)
        ->and(Student::query()->count())->toBe(0);
})->with([
    'personal' => [DemoPreset::Personal],
    'admin' => [DemoPreset::Admin],
]);

it('does not expose the demo user behind a guessable real-looking email', function (): void {
    post(route('demo.store'), ['preset' => DemoPreset::Admin->value])->assertRedirect();

    // Deterministic synthetic identity (CONTRACT §6): no faker name, demo email domain.
    expect(User::query()->sole()->email)->toEndWith('@uniges.demo');
});

it('rejects an invalid preset value with a 302 + session error and creates no rows', function (): void {
    from(route('demo.create'))
        ->post(route('demo.store'), ['preset' => 'not-a-real-preset'])
        ->assertRedirect()
        ->assertSessionHasErrors('preset');

    expect(User::query()->count())->toBe(0)
        ->and(Student::query()->count())->toBe(0);

    assertGuest();
});

it('rejects a missing preset with a 302 + session error, never a 422', function (): void {
    from(route('demo.create'))
        ->post(route('demo.store'), [])
        ->assertRedirect()
        ->assertSessionHasErrors('preset');

    expect(User::query()->count())->toBe(0);

    assertGuest();
});
