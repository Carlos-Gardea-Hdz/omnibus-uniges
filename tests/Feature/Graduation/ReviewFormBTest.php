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
 * Feature coverage for the reviewer-facing Formato B approval / rejection flow
 * and the rejection → resubmission loop (SPEC §7). Authorization is enforced by
 * EnsureRole middleware; the domain stays role-agnostic.
 */

/** A student parked in Form B review, ready for a reviewer's decision. */
function studentInReview(): Student
{
    return Student::factory()
        ->for(User::factory()->student())
        ->create([
            'status' => GraduationStatus::FormBReview->value,
            'form_b_approved' => false,
        ]);
}

it('approves a Form B: status advances and the approval flag is set', function (): void {
    Event::fake([StudentStatusChanged::class]);

    $admin = User::factory()->admin()->create();
    $student = studentInReview();

    actingAs($admin)
        ->post(route('admin.graduation.approve', $student))
        ->assertRedirect();

    $student->refresh();

    expect($student->status)->toBe(GraduationStatus::AnnexesPending)
        ->and($student->form_b_approved)->toBeTrue();

    Event::assertDispatched(
        StudentStatusChanged::class,
        fn (StudentStatusChanged $event): bool => $event->student->is($student)
            && $event->from === GraduationStatus::FormBReview
            && $event->to === GraduationStatus::AnnexesPending,
    );
});

it('rejects a Form B: status moves to rejected and observations are stored', function (): void {
    Event::fake([StudentStatusChanged::class]);

    $admin = User::factory()->admin()->create();
    $student = studentInReview();

    actingAs($admin)
        ->post(route('admin.graduation.reject', $student), [
            'observations' => 'El promedio capturado no coincide con el certificado.',
        ])
        ->assertRedirect();

    $student->refresh();

    expect($student->status)->toBe(GraduationStatus::FormBRejected)
        ->and($student->form_b_observations)
        ->toBe('El promedio capturado no coincide con el certificado.');

    Event::assertDispatched(
        StudentStatusChanged::class,
        fn (StudentStatusChanged $event): bool => $event->to === GraduationStatus::FormBRejected,
    );
});

it('rejects a Form B with empty observations as a 422', function (): void {
    $admin = User::factory()->admin()->create();
    $student = studentInReview();

    actingAs($admin)
        ->post(route('admin.graduation.reject', $student), ['observations' => ''])
        ->assertRedirect()
        ->assertSessionHasErrors('observations');
});

it('lets a rejected student resubmit: status returns to review and observations clear', function (): void {
    Event::fake([StudentStatusChanged::class]);

    $user = User::factory()->student()->create();
    $program = Program::factory()->create();
    $student = Student::factory()->for($user)->create([
        'status' => GraduationStatus::FormBRejected->value,
        'form_b_observations' => 'Corrige el título de la tesis.',
    ]);

    $payload = [
        'control_number' => '20239876',
        'first_name' => 'Luis',
        'last_name' => 'Martínez',
        'gender' => 'male',
        'gpa' => 91.0,
        'enrollment_date' => '2019-08-19',
        'program_id' => $program->id,
        'graduation_type_id' => GraduationType::factory()->create()->id,
        'study_plan_id' => StudyPlan::factory()->for($program)->create()->id,
    ];

    actingAs($user)
        ->put(route('student.form-b.update'), $payload)
        ->assertRedirect();

    $student->refresh();

    expect($student->status)->toBe(GraduationStatus::FormBReview)
        ->and($student->form_b_observations)->toBeNull();

    Event::assertDispatched(
        StudentStatusChanged::class,
        fn (StudentStatusChanged $event): bool => $event->from === GraduationStatus::FormBRejected
            && $event->to === GraduationStatus::FormBReview,
    );
});

it('forbids a non-staff user from approving a Form B', function (): void {
    $intruder = User::factory()->student()->create();
    $student = studentInReview();

    actingAs($intruder)
        ->post(route('admin.graduation.approve', $student))
        ->assertForbidden();

    expect($student->refresh()->status)->toBe(GraduationStatus::FormBReview);
});

it('forbids a non-staff user from rejecting a Form B', function (): void {
    $intruder = User::factory()->student()->create();
    $student = studentInReview();

    actingAs($intruder)
        ->post(route('admin.graduation.reject', $student), [
            'observations' => 'should never be applied',
        ])
        ->assertForbidden();
});
