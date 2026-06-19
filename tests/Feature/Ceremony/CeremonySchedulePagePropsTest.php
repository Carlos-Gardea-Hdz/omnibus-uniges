<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Runtime contract test for the Graduation/CeremonySchedule Inertia page
 * (CONTRACT §9). Two paginators: `scheduling` (students parked at JuryAssigned,
 * awaiting a ceremony date) and `graduating` (students at CeremonyScheduled,
 * with a server-computed can_graduate flag = ceremony_date passed). This locks
 * the exact snake_case shapes so the controller and the React page never drift.
 * Runs against PostgreSQL 18 via RefreshDatabase.
 */

it('renders the ceremony page with both paginators and the scheduling row shape', function (): void {
    $admin = User::factory()->admin()->create();
    $student = Student::factory()->juryAssigned()->create();

    actingAs($admin)
        ->get(route('admin.graduation.ceremony.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Graduation/CeremonySchedule')
                ->has('scheduling.data', 1)
                ->has('graduating.data')
                ->has(
                    'scheduling.data.0',
                    fn (AssertableInertia $row): AssertableInertia => $row
                        ->where('id', $student->id)
                        ->where('control_number', $student->control_number)
                        ->where('program_name', $student->program->name)
                        ->where('graduation_type_name', $student->graduationType->name)
                        ->has('full_name'),
                ),
        );
});

it('shapes the graduating row with can_graduate true once the ceremony has passed', function (): void {
    $admin = User::factory()->admin()->create();
    $student = Student::factory()->ceremonyPassed()->create();

    actingAs($admin)
        ->get(route('admin.graduation.ceremony.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->has('graduating.data', 1)
                ->has(
                    'graduating.data.0',
                    fn (AssertableInertia $row): AssertableInertia => $row
                        ->where('id', $student->id)
                        ->where('control_number', $student->control_number)
                        ->where('can_graduate', true)
                        ->has('full_name')
                        ->has('ceremony_date')
                        ->has('ceremony_location'),
                ),
        );
});

it('shapes the graduating row with can_graduate false while the ceremony is still future', function (): void {
    $admin = User::factory()->admin()->create();
    Student::factory()->ceremonyScheduled()->create();

    actingAs($admin)
        ->get(route('admin.graduation.ceremony.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->has('graduating.data', 1)
                ->where('graduating.data.0.can_graduate', false),
        );
});

it('only lists JuryAssigned students in the scheduling queue', function (): void {
    $admin = User::factory()->admin()->create();
    Student::factory()->juryAssigned()->create();
    // A student at a different stage must not appear in the scheduling queue.
    Student::factory()->paymentVerified()->create();

    actingAs($admin)
        ->get(route('admin.graduation.ceremony.index'))
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Graduation/CeremonySchedule')
                ->has('scheduling.data', 1),
        );
});

it('only lists CeremonyScheduled students in the graduating queue', function (): void {
    $admin = User::factory()->admin()->create();
    Student::factory()->ceremonyScheduled()->create();
    // A graduated student is terminal and must not reappear in the graduating queue.
    Student::factory()->ceremonyPassed()->create([
        'status' => GraduationStatus::Graduated->value,
    ]);

    actingAs($admin)
        ->get(route('admin.graduation.ceremony.index'))
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->has('graduating.data', 1),
        );
});

it('exposes the ceremony date as an ISO-8601 string on the graduating row', function (): void {
    $admin = User::factory()->admin()->create();
    $student = Student::factory()->ceremonyPassed()->create();

    actingAs($admin)
        ->get(route('admin.graduation.ceremony.index'))
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where(
                    'graduating.data.0.ceremony_date',
                    $student->ceremony_date->toIso8601String(),
                ),
        );
});

it('forbids a non-staff user from viewing the ceremony queue', function (): void {
    $student = User::factory()->student()->create();

    actingAs($student)
        ->get(route('admin.graduation.ceremony.index'))
        ->assertForbidden();
});
