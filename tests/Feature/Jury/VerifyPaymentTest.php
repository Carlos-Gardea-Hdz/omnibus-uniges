<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Models\Student;
use App\Domain\Jury\Events\JuryAssigned;
use App\Domain\Jury\Models\JuryAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Feature coverage for the staff payment verification (CONTRACT §8,
 * VerifyPaymentAction). Verifying flips payment_verified to true and nothing
 * else: it never advances the machine, never creates a jury and never emits an
 * event — it is purely the precondition the AssignJuryAction later checks. Runs
 * against PostgreSQL 18 via RefreshDatabase.
 */

it('verifies the payment without advancing, creating a jury or emitting an event', function (): void {
    Event::fake([StudentStatusChanged::class, JuryAssigned::class]);

    $admin = User::factory()->admin()->create();
    $student = Student::factory()->paymentPending()->create([
        'payment_reference' => 'PAY-55555555',
        'paid_at' => now(),
    ]);

    actingAs($admin)
        ->post(route('admin.graduation.jury.verify-payment', $student))
        ->assertRedirect()
        ->assertSessionHas('success');

    $student->refresh();

    expect($student->payment_verified)->toBeTrue()
        ->and($student->status)->toBe(GraduationStatus::PaymentPending)
        ->and(JuryAssignment::query()->count())->toBe(0);

    Event::assertNotDispatched(StudentStatusChanged::class);
    Event::assertNotDispatched(JuryAssigned::class);
});

it('allows a secretary to verify a payment', function (): void {
    $secretary = User::factory()->secretary()->create();
    $student = Student::factory()->paymentPending()->create();

    actingAs($secretary)
        ->post(route('admin.graduation.jury.verify-payment', $student))
        ->assertRedirect();

    expect($student->fresh()->payment_verified)->toBeTrue();
});

it('forbids a student from verifying a payment', function (): void {
    $intruder = User::factory()->student()->create();
    $student = Student::factory()->paymentPending()->create();

    actingAs($intruder)
        ->post(route('admin.graduation.jury.verify-payment', $student))
        ->assertForbidden();

    expect($student->fresh()->payment_verified)->toBeFalse();
});
