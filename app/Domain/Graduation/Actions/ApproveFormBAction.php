<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Actions;

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\StateMachine\GraduationStateMachine;
use Illuminate\Support\Facades\DB;

/**
 * Reviewer approves a student's Formato B, advancing FormBReview ->
 * AnnexesPending and marking the form approved. Guarded by the state machine
 * inside a transaction; emits the real-time progress event.
 */
final class ApproveFormBAction
{
    public function __construct(
        private readonly GraduationStateMachine $stateMachine,
    ) {}

    public function handle(Student $student): Student
    {
        return DB::transaction(function () use ($student): Student {
            $from = $student->status;
            $to = GraduationStatus::AnnexesPending;

            $this->stateMachine->assertCanTransition($from, $to);

            $student->form_b_approved = true;
            $student->status = $to;
            $student->save();

            StudentStatusChanged::dispatch($student, $from, $to);

            return $student;
        });
    }
}
