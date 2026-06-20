<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Runtime prop-contract test for the read-only student dashboard (CONTRACT §3,
 * §5; SPEC §7). Inertia props are untyped at the wire, so the static gates
 * (tsc / PHPStan) cannot catch a controller that serialises a shape the React
 * page does not consume. This locks DashboardController::index() to the EXACT
 * snake_case payload Student/Dashboard.tsx reads — every key, the enum-as-value
 * serialisation, the derived `step`/`total_steps`/`is_graduated`, and the pure
 * `match`-on-status CTA (route name resolved to a URL, label_key). Boots the app
 * + PostgreSQL 18 (Spatie-Data/Auth/DB needs the app → tests/Feature).
 */

it('renders Student/Dashboard with the full snake_case prop contract for a documents-stage student', function (): void {
    $user = User::factory()->student()->create();
    $student = Student::factory()->for($user)->documentsStage()->create([
        'control_number' => '20231234',
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
    ]);

    // documentsStage() seats the student at AnnexIiiPending (step 5).
    expect($student->status)->toBe(GraduationStatus::AnnexIiiPending);

    actingAs($user)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Student/Dashboard')
                ->where('student_id', $student->id)
                ->where('control_number', '20231234')
                ->where('full_name', 'Ana Reyes')
                // status serialises as the value string, never the enum object.
                ->where('status', 'annex_iii_pending')
                ->where('status', GraduationStatus::AnnexIiiPending->value)
                ->where('step', 5)
                ->where('total_steps', 9)
                ->where('is_graduated', false)
                // CTA is a resolved URL (route name → href) + a label key.
                ->where('cta.route', route('student.documents.index'))
                ->where('cta.label_key', 'dashboard.student.cta.documents')
                ->where('form_b_observations', null)
                ->hasAll([
                    'student_id', 'control_number', 'full_name', 'status', 'step',
                    'total_steps', 'is_graduated', 'cta', 'form_b_observations',
                ]),
        );
});

it('surfaces the observations and a fix-CTA for a rejected Format B', function (): void {
    $user = User::factory()->student()->create();
    $student = Student::factory()->for($user)->formBRejected()->create([
        'form_b_observations' => 'Corrige el título de la tesis.',
    ]);

    expect($student->status)->toBe(GraduationStatus::FormBRejected);

    actingAs($user)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Student/Dashboard')
                ->where('status', 'form_b_rejected')
                ->where('step', GraduationStatus::FormBRejected->step())
                ->where('is_graduated', false)
                ->where('cta.route', route('student.form-b.create'))
                ->where('cta.label_key', 'dashboard.student.cta.form_b_fix')
                ->where('form_b_observations', 'Corrige el título de la tesis.'),
        );
});

it('shows no CTA and flags graduation once the pipeline is terminal', function (): void {
    $user = User::factory()->student()->create();
    // ceremonyPassed() carries a passed ceremony; flip to the terminal state so
    // the dashboard renders its celebration block (cta === null).
    $student = Student::factory()->for($user)->ceremonyPassed()->create([
        'status' => GraduationStatus::Graduated,
        'graduation_date' => now()->toDateString(),
    ]);

    expect($student->status)->toBe(GraduationStatus::Graduated);

    actingAs($user)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Student/Dashboard')
                ->where('status', 'graduated')
                ->where('step', 9)
                ->where('total_steps', 9)
                ->where('is_graduated', true)
                ->where('cta', null),
        );
});

it('renders for an authenticated student with no Student row (null prop shape, no crash)', function (): void {
    // A freshly-registered student that has not yet been provisioned a Student
    // record must still get a stable, null-filled payload — never a 500.
    $user = User::factory()->student()->create();

    actingAs($user)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Student/Dashboard')
                ->where('student_id', null)
                ->where('status', null)
                ->where('step', null)
                ->where('total_steps', 9)
                ->where('is_graduated', false)
                ->where('cta', null)
                ->where('full_name', null),
        );
});
