<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GraduationType;
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
        'graduation_type_id' => GraduationType::factory()->create()->id,
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
