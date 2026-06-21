<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\Professor;
use App\Domain\Academic\Models\Program;
use App\Domain\Academic\Models\StudyPlan;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Feature coverage for the student-facing Formato B intake (SPEC §7).
 * Runs against PostgreSQL 18 via RefreshDatabase — never SQLite. The Action
 * is exercised end-to-end through the route + EnsureRole middleware.
 */

/**
 * Minimal, internally-consistent Formato B payload referencing freshly seeded
 * catalog rows. Caller may override any field.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function formBPayload(array $overrides = []): array
{
    $program = Program::factory()->create();

    return array_merge([
        'control_number' => '20231234',
        'first_name' => 'Ana',
        'last_name' => 'García',
        'mother_last_name' => 'López',
        'gender' => 'female',
        'gpa' => 88.5,
        'enrollment_date' => '2019-08-19',
        'program_id' => $program->id,
        // Default to a type that does NOT require an advisor so these payloads
        // (which omit advisor_id) stay valid regardless of the random factory
        // flag; the requires_advisor branches are covered explicitly below.
        'graduation_type_id' => GraduationType::factory()->withoutAdvisor()->create()->id,
        'study_plan_id' => StudyPlan::factory()->for($program)->create()->id,
        'thesis_title' => 'Sistema de titulación en tiempo real',
        'phone' => '6181234567',
        'mobile' => '6189876543',
        'age' => 24,
        'address_street' => 'Av. Tecnológico',
        'address_neighborhood' => 'Centro',
        'address_ext_number' => '1500',
        'address_postal_code' => 34080,
    ], $overrides);
}

it('moves a student to Form B review, dispatches the event and persists the data', function (): void {
    Event::fake([StudentStatusChanged::class]);

    $user = User::factory()->student()->create();
    $student = Student::factory()->for($user)->create([
        'status' => GraduationStatus::FormBPending->value,
    ]);

    actingAs($user)
        ->post(route('student.form-b.store'), formBPayload([
            'control_number' => '20231234',
            'first_name' => 'Ana',
            'gpa' => 88.5,
        ]))
        ->assertRedirect();

    $student->refresh();

    expect($student->status)->toBe(GraduationStatus::FormBReview)
        ->and($student->first_name)->toBe('Ana')
        ->and($student->control_number)->toBe('20231234')
        ->and((float) $student->gpa)->toBe(88.5)
        ->and($student->form_b_submitted_at)->not->toBeNull();

    Event::assertDispatched(
        StudentStatusChanged::class,
        fn (StudentStatusChanged $event): bool => $event->student->is($student)
            && $event->from === GraduationStatus::FormBPending
            && $event->to === GraduationStatus::FormBReview,
    );
});

it('rejects an out-of-range GPA with a 422 and no status change', function (): void {
    Event::fake([StudentStatusChanged::class]);

    $user = User::factory()->student()->create();
    Student::factory()->for($user)->create([
        'status' => GraduationStatus::FormBPending->value,
    ]);

    actingAs($user)
        ->post(route('student.form-b.store'), formBPayload(['gpa' => 50]))
        ->assertRedirect()
        ->assertSessionHasErrors('gpa');

    Event::assertNotDispatched(StudentStatusChanged::class);
});

it('rejects a malformed control number with a 422', function (): void {
    $user = User::factory()->student()->create();
    Student::factory()->for($user)->create([
        'status' => GraduationStatus::FormBPending->value,
    ]);

    actingAs($user)
        ->post(route('student.form-b.store'), formBPayload(['control_number' => 'ABC123']))
        ->assertRedirect()
        ->assertSessionHasErrors('control_number');
});

it('forbids a non-student role from submitting Form B', function (): void {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->post(route('student.form-b.store'), formBPayload())
        ->assertForbidden();
});

it('updates data only without changing status once the student is past Form B (SPEC §3.3)', function (): void {
    Event::fake([StudentStatusChanged::class]);

    $user = User::factory()->student()->create();
    $student = Student::factory()->for($user)->create([
        'status' => GraduationStatus::AnnexesPending->value,
        'first_name' => 'Original',
    ]);

    actingAs($user)
        ->put(route('student.form-b.update'), formBPayload(['first_name' => 'Editado']))
        ->assertRedirect();

    $student->refresh();

    expect($student->status)->toBe(GraduationStatus::AnnexesPending)
        ->and($student->first_name)->toBe('Editado');

    Event::assertNotDispatched(StudentStatusChanged::class);
});

it('rejects a control number already registered to another student with a field error', function (): void {
    $user = User::factory()->student()->create();
    Student::factory()->for($user)->create([
        'status' => GraduationStatus::FormBPending->value,
    ]);

    Student::factory()->create(['control_number' => '20231234']);

    actingAs($user)
        ->post(route('student.form-b.store'), formBPayload(['control_number' => '20231234']))
        ->assertRedirect()
        ->assertSessionHasErrors('control_number');
});

/*
 * WARN 2 — requires_advisor enforcement. A GraduationType flagged
 * requires_advisor cannot have its Form B submitted with advisor_id = null;
 * a type that does not require one accepts a null advisor.
 */

it('rejects a Form B with no advisor when the graduation type requires one (302, nothing persisted)', function (): void {
    Event::fake([StudentStatusChanged::class]);

    $user = User::factory()->student()->create();
    $student = Student::factory()->for($user)->create([
        'status' => GraduationStatus::FormBPending->value,
        'advisor_id' => null,
    ]);

    $typeRequiringAdvisor = GraduationType::factory()->requiresAdvisor()->create();

    actingAs($user)
        ->post(route('student.form-b.store'), formBPayload([
            'graduation_type_id' => $typeRequiringAdvisor->id,
            'advisor_id' => null,
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors('advisor_id');

    $student->refresh();

    expect($student->status)->toBe(GraduationStatus::FormBPending)
        ->and($student->advisor_id)->toBeNull();

    Event::assertNotDispatched(StudentStatusChanged::class);
});

it('accepts a Form B with an advisor when the graduation type requires one', function (): void {
    $user = User::factory()->student()->create();
    Student::factory()->for($user)->create([
        'status' => GraduationStatus::FormBPending->value,
    ]);

    $typeRequiringAdvisor = GraduationType::factory()->requiresAdvisor()->create();
    $advisor = Professor::factory()->create();

    actingAs($user)
        ->post(route('student.form-b.store'), formBPayload([
            'graduation_type_id' => $typeRequiringAdvisor->id,
            'advisor_id' => $advisor->id,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('accepts a Form B with no advisor when the graduation type does not require one', function (): void {
    $user = User::factory()->student()->create();
    $student = Student::factory()->for($user)->create([
        'status' => GraduationStatus::FormBPending->value,
    ]);

    $typeWithoutAdvisor = GraduationType::factory()->withoutAdvisor()->create();

    actingAs($user)
        ->post(route('student.form-b.store'), formBPayload([
            'graduation_type_id' => $typeWithoutAdvisor->id,
            'advisor_id' => null,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($student->fresh()->status)->toBe(GraduationStatus::FormBReview);
});

/*
 * NIT — sane bounds on age and thesis_abstract. Out-of-range values are a
 * graceful 302 field error, never an unbounded write or a 500.
 */

it('rejects an out-of-range age with a session error', function (int $age): void {
    $user = User::factory()->student()->create();
    Student::factory()->for($user)->create([
        'status' => GraduationStatus::FormBPending->value,
    ]);

    actingAs($user)
        ->post(route('student.form-b.store'), formBPayload(['age' => $age]))
        ->assertRedirect()
        ->assertSessionHasErrors('age');
})->with([14, 121, 999]);

it('rejects an over-long thesis abstract with a session error', function (): void {
    $user = User::factory()->student()->create();
    Student::factory()->for($user)->create([
        'status' => GraduationStatus::FormBPending->value,
    ]);

    actingAs($user)
        ->post(route('student.form-b.store'), formBPayload([
            'thesis_abstract' => str_repeat('a', 5001),
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors('thesis_abstract');
});
