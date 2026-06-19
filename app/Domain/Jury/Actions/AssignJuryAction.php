<?php

declare(strict_types=1);

namespace App\Domain\Jury\Actions;

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\StateMachine\GraduationStateMachine;
use App\Domain\Jury\Data\AssignJuryData;
use App\Domain\Jury\Events\JuryAssigned;
use App\Domain\Jury\Models\JuryAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff assigns the jury to a payment-verified student, advancing the workflow
 * PaymentPending -> JuryAssigned (6 -> 7). Requires the payment to be verified,
 * guarded by the state machine, all inside one transaction. Emits the workflow
 * progress event and the jury-assigned broadcast.
 */
final class AssignJuryAction
{
    public function __construct(
        private readonly GraduationStateMachine $stateMachine,
    ) {}

    public function handle(AssignJuryData $data, Student $student): JuryAssignment
    {
        return DB::transaction(function () use ($data, $student): JuryAssignment {
            if (! $student->payment_verified) {
                throw ValidationException::withMessages([
                    'payment_verified' => __('validation.jury.payment_unverified'),
                ]);
            }

            $from = $student->status;
            $to = GraduationStatus::JuryAssigned;

            $this->stateMachine->assertCanTransition($from, $to);

            $jury = JuryAssignment::create([
                'student_id' => $student->id,
                'president_professor_id' => $data->president_professor_id,
                'secretary_professor_id' => $data->secretary_professor_id,
                'vocal_professor_id' => $data->vocal_professor_id,
                'substitute_professor_id' => $data->substitute_professor_id,
            ]);

            $student->status = $to;
            $student->save();

            StudentStatusChanged::dispatch($student, $from, $to);
            JuryAssigned::dispatch($jury);

            return $jury;
        });
    }
}
