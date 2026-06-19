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
        if ($student->status !== GraduationStatus::PaymentPending) {
            throw ValidationException::withMessages([
                'payment' => __('validation.payment.wrong_state'),
            ]);
        }

        return DB::transaction(function () use ($student): Student {
            $student->payment_verified = true;
            $student->save();

            return $student;
        });
    }
}
