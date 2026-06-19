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
 * Runtime contract test for the Student/Payment Inertia page (CONTRACT §12).
 * Inertia props are untyped at runtime, so the static gates cannot catch a
 * controller that serialises a different shape than the React page consumes.
 * This locks the exact snake_case payload — especially `student_id`, which the
 * page uses to subscribe to its private student.{id} channel, and `status`,
 * which must be the enum VALUE string, never the enum object. Runs against
 * PostgreSQL 18 via RefreshDatabase.
 */

it('renders Student/Payment with the snake_case prop contract the page consumes', function (): void {
    $user = User::factory()->student()->create();
    $student = Student::factory()->for($user)->paymentPending()->create([
        'payment_reference' => 'PAY-12345678',
        'paid_at' => now(),
        'payment_verified' => false,
    ]);

    actingAs($user)
        ->get(route('student.payment.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Student/Payment')
                ->hasAll(['student_id', 'status', 'payment_reference', 'paid_at', 'payment_verified'])
                ->where('student_id', $student->id)
                ->where('status', GraduationStatus::PaymentPending->value)
                ->where('payment_reference', 'PAY-12345678')
                ->where('payment_verified', false)
                ->has('paid_at'),
        );
});

it('serialises status as the GraduationStatus enum value, never the enum object', function (): void {
    $user = User::factory()->student()->create();
    Student::factory()->for($user)->paymentPending()->create();

    actingAs($user)
        ->get(route('student.payment.index'))
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('status', 'payment_pending'),
        );
});

it('exposes a null payment_reference and paid_at before any submission', function (): void {
    $user = User::factory()->student()->create();
    Student::factory()->for($user)->paymentPending()->create([
        'payment_reference' => null,
        'paid_at' => null,
    ]);

    actingAs($user)
        ->get(route('student.payment.index'))
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('payment_reference', null)
                ->where('paid_at', null)
                ->where('payment_verified', false),
        );
});

it('aborts with 404 when the acting user has no Student record', function (): void {
    $user = User::factory()->student()->create();

    actingAs($user)
        ->get(route('student.payment.index'))
        ->assertNotFound();
});
