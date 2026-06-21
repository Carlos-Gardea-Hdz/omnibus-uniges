<?php

declare(strict_types=1);

namespace App\Domain\Jury\Actions;

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff marks a student's recorded payment as verified. This is a precondition
 * for jury assignment (AssignJuryAction). It does NOT advance the workflow and
 * emits no event — the 6 → 7 transition happens only when the jury is assigned.
 */
final class VerifyPaymentAction
{
    public function handle(Student $student): Student
    {
        return DB::transaction(function () use ($student): Student {
            // Serialize concurrent mutations: re-load the row under a
            // pessimistic lock and re-read state from it, so a double-click /
            // retry / two-staff race re-checks the precondition against the
            // committed row instead of a stale read.
            $student = Student::query()->whereKey($student->getKey())->lockForUpdate()->firstOrFail();

            if ($student->status !== GraduationStatus::PaymentPending) {
                throw ValidationException::withMessages([
                    'payment' => __('validation.payment.wrong_state'),
                ]);
            }

            $student->payment_verified = true;
            $student->save();

            return $student;
        });
    }
}
