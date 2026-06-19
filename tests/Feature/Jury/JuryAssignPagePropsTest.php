<?php

declare(strict_types=1);

use App\Domain\Academic\Models\Professor;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Runtime contract test for the Graduation/JuryAssign Inertia page
 * (CONTRACT §12). The queue is a Laravel paginator of students parked at
 * PaymentPending, plus the professor pool for the role selects and the four
 * jury role strings. This locks the exact snake_case shapes so the controller
 * and the React page never drift. Runs against PostgreSQL 18 via RefreshDatabase.
 */

/** A student parked at step 6 (PaymentPending) with a verified payment. */
function studentInJuryQueue(): Student
{
    return Student::factory()->paymentVerified()->create([
        'payment_reference' => 'PAY-22223333',
        'paid_at' => now(),
    ]);
}

it('renders the jury-assign queue with the paginator, professor and role prop shapes', function (): void {
    $admin = User::factory()->admin()->create();
    $student = studentInJuryQueue();
    Professor::factory()->count(4)->create();

    actingAs($admin)
        ->get(route('admin.graduation.jury.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Graduation/JuryAssign')
                ->has('students.data', 1)
                ->has('professors')
                ->has('roles')
                ->has(
                    'students.data.0',
                    fn (AssertableInertia $row): AssertableInertia => $row
                        ->where('id', $student->id)
                        ->where('control_number', $student->control_number)
                        ->where('program_name', $student->program->name)
                        ->where('graduation_type_name', $student->graduationType->name)
                        ->has('full_name')
                        ->has('payment_reference')
                        ->has('paid_at')
                        ->where('payment_verified', true),
                )
                ->has(
                    'professors.0',
                    fn (AssertableInertia $professor): AssertableInertia => $professor
                        ->has('id')
                        ->has('full_name'),
                ),
        );
});

it('exposes the four jury role values in order', function (): void {
    $admin = User::factory()->admin()->create();
    studentInJuryQueue();
    Professor::factory()->count(4)->create();

    actingAs($admin)
        ->get(route('admin.graduation.jury.index'))
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('roles', ['president', 'secretary', 'vocal', 'substitute']),
        );
});

it('only lists students parked at PaymentPending', function (): void {
    $admin = User::factory()->admin()->create();
    studentInJuryQueue();
    Professor::factory()->count(4)->create();

    // A student at a different stage must not appear in the jury queue.
    Student::factory()->create([
        'status' => GraduationStatus::AnnexIiiPending->value,
    ]);

    actingAs($admin)
        ->get(route('admin.graduation.jury.index'))
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Graduation/JuryAssign')
                ->has('students.data', 1),
        );
});

it('forbids a non-staff user from viewing the jury queue', function (): void {
    $student = User::factory()->student()->create();

    actingAs($student)
        ->get(route('admin.graduation.jury.index'))
        ->assertForbidden();
});
