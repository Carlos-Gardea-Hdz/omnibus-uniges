<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Runtime contract test for the student status tracker (SPEC §3.3).
 *
 * Inertia props are untyped at runtime, so the static gates (tsc / PHPStan)
 * cannot catch a controller that serialises a different prop shape than the
 * React page consumes. This test locks the StatusController::index() payload to
 * the exact snake_case shape Status.tsx reads — most importantly `student_id`,
 * which the page uses to subscribe to its private `student.{id}` channel.
 */

it('renders Student/Status with the snake_case prop contract the page consumes', function (): void {
    $user = User::factory()->student()->create();
    $student = Student::factory()->for($user)->create([
        'control_number' => '20231234',
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'status' => GraduationStatus::AnnexesPending,
        'form_b_observations' => null,
    ]);

    actingAs($user)
        ->get(route('student.status'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Student/Status')
                ->where('student_id', $student->id)
                ->where('control_number', '20231234')
                ->where('full_name', 'Ana Reyes')
                ->where('status', GraduationStatus::AnnexesPending->value)
                ->where('form_b_observations', null)
                ->hasAll(['student_id', 'control_number', 'full_name', 'status', 'form_b_observations']),
        );
});

it('serialises status as the GraduationStatus enum value, never the enum object', function (): void {
    $user = User::factory()->student()->create();
    Student::factory()->for($user)->create([
        'status' => GraduationStatus::FormBRejected,
        'form_b_observations' => 'Corrige el título de la tesis.',
    ]);

    actingAs($user)
        ->get(route('student.status'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('status', 'form_b_rejected')
                ->where('form_b_observations', 'Corrige el título de la tesis.'),
        );
});
