<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Feature coverage for the student-facing payment submission (CONTRACT §8,
 * SubmitPaymentAction). Submitting a reference records it and stamps paid_at,
 * but it does NOT verify the payment, does NOT advance the machine and emits no
 * StudentStatusChanged — verification is a separate staff step. Runs against
 * PostgreSQL 18 via RefreshDatabase. Web validation surfaces as 302 + session
 * errors, never a 422.
 */

/** A student parked at step 6 (PaymentPending) owned by the acting user. */
function studentAwaitingPayment(User $user): Student
{
    return Student::factory()->for($user)->paymentPending()->create();
}

it('records the reference and stamps paid_at without verifying or advancing', function (): void {
    Event::fake([StudentStatusChanged::class]);

    $user = User::factory()->student()->create();
    $student = studentAwaitingPayment($user);

    actingAs($user)
        ->post(route('student.payment.submit'), [
            'payment_reference' => 'PAY-12345678',
        ])
        ->assertRedirect(route('student.payment.index'))
        ->assertSessionHas('success');

    $student->refresh();

    expect($student->payment_reference)->toBe('PAY-12345678')
        ->and($student->paid_at)->not->toBeNull()
        ->and($student->payment_verified)->toBeFalse()
        ->and($student->status)->toBe(GraduationStatus::PaymentPending);

    Event::assertNotDispatched(StudentStatusChanged::class);
});

it('rejects a missing reference as a session error (302), not a 422', function (): void {
    $user = User::factory()->student()->create();
    studentAwaitingPayment($user);

    actingAs($user)
        ->post(route('student.payment.submit'), [])
        ->assertRedirect()
        ->assertSessionHasErrors('payment_reference');
});

it('rejects a reference longer than 50 characters as a session error', function (): void {
    $user = User::factory()->student()->create();
    studentAwaitingPayment($user);

    actingAs($user)
        ->post(route('student.payment.submit'), [
            'payment_reference' => str_repeat('X', 51),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('payment_reference');
});

it('guards against submitting payment from the wrong state (not PaymentPending)', function (): void {
    Event::fake([StudentStatusChanged::class]);

    $user = User::factory()->student()->create();
    $student = Student::factory()->for($user)->create([
        'status' => GraduationStatus::AnnexIiiPending->value,
    ]);

    actingAs($user)
        ->post(route('student.payment.submit'), [
            'payment_reference' => 'PAY-99999999',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('payment_reference');

    $student->refresh();

    expect($student->payment_reference)->toBeNull()
        ->and($student->paid_at)->toBeNull()
        ->and($student->status)->toBe(GraduationStatus::AnnexIiiPending);

    Event::assertNotDispatched(StudentStatusChanged::class);
});

it('forbids a non-student role from submitting payment', function (): void {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->post(route('student.payment.submit'), [
            'payment_reference' => 'PAY-00000000',
        ])
        ->assertForbidden();
});

it('renders the payment page for the acting student', function (): void {
    $user = User::factory()->student()->create();
    studentAwaitingPayment($user);

    actingAs($user)
        ->get(route('student.payment.index'))
        ->assertOk();
});
