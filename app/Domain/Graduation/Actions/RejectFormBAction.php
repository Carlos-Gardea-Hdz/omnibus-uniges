<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Actions;

use App\Domain\Graduation\Data\ReviewFormBData;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\StateMachine\GraduationStateMachine;
use Illuminate\Support\Facades\DB;

/**
 * Reviewer rejects a student's Formato B, sending FormBReview ->
 * FormBRejected and recording the observations the student must address
 * before re-submitting. Guarded by the state machine inside a transaction;
 * emits the real-time progress event.
 */
final class RejectFormBAction
{
    public function __construct(
        private readonly GraduationStateMachine $stateMachine,
    ) {}

    public function handle(Student $student, ReviewFormBData $data): Student
    {
        return DB::transaction(function () use ($student, $data): Student {
            $from = $student->status;
            $to = GraduationStatus::FormBRejected;

            $this->stateMachine->assertCanTransition($from, $to);

            $student->form_b_observations = $data->observations;
            $student->status = $to;
            $student->save();

            StudentStatusChanged::dispatch($student, $from, $to);

            return $student;
        });
    }
}
