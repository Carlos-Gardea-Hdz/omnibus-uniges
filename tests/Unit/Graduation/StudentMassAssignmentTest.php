<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;

/*
 * WARN 5 — defense-in-depth on mass-assignment. Workflow/identity columns are no
 * longer in $fillable, so a stray fill() (e.g. a controller that forwards request
 * input) can never escalate a student's state or rebind their identity. Hidden
 * PII/identity columns never leak through array/JSON serialization.
 */

it('does not mass-assign workflow/identity columns via fill()', function (): void {
    $student = new Student;

    $student->fill([
        'status' => GraduationStatus::Graduated->value,
        'payment_verified' => true,
        'form_b_approved' => true,
        'user_id' => 999,
        'diploma_folio' => '2026-ISC-001',
        // A legitimately fillable intake field, to prove fill() still works.
        'first_name' => 'Ana',
    ]);

    expect($student->isDirty('status'))->toBeFalse()
        ->and($student->isDirty('payment_verified'))->toBeFalse()
        ->and($student->isDirty('form_b_approved'))->toBeFalse()
        ->and($student->isDirty('user_id'))->toBeFalse()
        ->and($student->isDirty('diploma_folio'))->toBeFalse()
        // The intake field WAS applied — fill() still serves the Form B path.
        ->and($student->first_name)->toBe('Ana');
});

it('hides identity/PII columns from array serialization', function (): void {
    // Build the model in-memory (no DB / no factory relations) — this is a pure
    // serialization assertion about $hidden.
    $student = new Student;
    $student->forceFill([
        'id' => 1,
        'demo_session_id' => null,
        'user_id' => 7,
        'control_number' => '20231234',
        'first_name' => 'Ana',
        'phone' => '6181234567',
        'mobile' => '6189876543',
        'payment_reference' => 'PAY-0001',
        'address_street' => 'Av. Tecnológico',
        'address_neighborhood' => 'Centro',
        'address_ext_number' => '1500',
        'address_int_number' => null,
        'address_postal_code' => 34080,
    ]);

    $array = $student->toArray();

    expect($array)->not->toHaveKeys([
        'demo_session_id',
        'user_id',
        'payment_reference',
        'phone',
        'mobile',
        'address_street',
        'address_neighborhood',
        'address_ext_number',
        'address_int_number',
        'address_postal_code',
    ]);
});
