<?php

declare(strict_types=1);

namespace App\Domain\Jury\Actions;

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Domain\Jury\Data\SubmitPaymentData;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Student records their payment reference at the payment stage (step 6).
 * This captures the reference and the paid-at timestamp only — it does NOT
 * verify the payment, does NOT advance the workflow and emits no event. Staff
 * verification (VerifyPaymentAction) and jury assignment (AssignJuryAction)
 * remain separate operations.
 */
final class SubmitPaymentAction
{
    public function handle(SubmitPaymentData $data, Student $student): Student
    {
        return DB::transaction(function () use ($data, $student): Student {
            if ($student->status !== GraduationStatus::PaymentPending) {
                throw ValidationException::withMessages([
                    'payment_reference' => __('validation.payment.wrong_state'),
                ]);
            }

            $student->payment_reference = $data->payment_reference;
            $student->setAttribute('paid_at', now());
            $student->save();

            return $student;
        });
    }
}
