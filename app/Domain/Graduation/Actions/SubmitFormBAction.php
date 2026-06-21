<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Actions;

use App\Domain\Graduation\Data\SubmitFormBData;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\StateMachine\GraduationStateMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Student submits (or re-submits) their Formato B, moving the workflow into
 * FormBReview. Re-submission from FormBRejected clears the prior observations.
 * One business operation; multi-column write is wrapped in a transaction and
 * guarded by the state machine before any mutation is persisted.
 */
final class SubmitFormBAction
{
    public function __construct(
        private readonly GraduationStateMachine $stateMachine,
    ) {}

    public function handle(SubmitFormBData $data, Student $student): Student
    {
        $this->assertControlNumberAvailable($data->control_number, $student);

        return DB::transaction(function () use ($data, $student): Student {
            // Serialize concurrent transitions: re-load the row under a
            // pessimistic lock and re-read the source state from it, so a
            // double-click / retry race loses the FormBReview guard cleanly
            // instead of double-firing the transition (and its event).
            $student = Student::query()->whereKey($student->getKey())->lockForUpdate()->firstOrFail();

            $from = $student->status;

            $student->fill([
                'control_number' => $data->control_number,
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'mother_last_name' => $data->mother_last_name,
                'gender' => $data->gender,
                'gpa' => $data->gpa,
                'enrollment_date' => $data->enrollment_date,
                'thesis_title' => $data->thesis_title,
                'thesis_abstract' => $data->thesis_abstract,
                'program_id' => $data->program_id,
                'graduation_type_id' => $data->graduation_type_id,
                'study_plan_id' => $data->study_plan_id,
                'advisor_id' => $data->advisor_id,
                'phone' => $data->phone,
                'mobile' => $data->mobile,
                'age' => $data->age,
                'address_street' => $data->address_street,
                'address_neighborhood' => $data->address_neighborhood,
                'address_ext_number' => $data->address_ext_number,
                'address_int_number' => $data->address_int_number,
                'address_postal_code' => $data->address_postal_code,
            ]);

            // SPEC §3.3: a submission while still in the Form B stage (steps
            // 1–3) (re-)enters review; once past it (step > 3) only the data is
            // updated — status and the Form B flags stay put.
            $to = $from->step() <= 3 && $from !== GraduationStatus::FormBReview
                ? GraduationStatus::FormBReview
                : null;

            if ($to !== null) {
                $this->stateMachine->assertCanTransition($from, $to);

                if ($from === GraduationStatus::FormBRejected) {
                    $student->form_b_observations = null;
                }

                $student->setAttribute('form_b_submitted_at', now());
                $student->status = $to;
            }

            $student->save();

            if ($to !== null) {
                StudentStatusChanged::dispatch($student, $from, $to);
            }

            return $student;
        });
    }

    /**
     * A control number is unique across students. A re-submission by the same
     * student keeps their own number; a clash with ANY other student surfaces
     * as a 422 field error instead of a database-integrity 500.
     */
    private function assertControlNumberAvailable(string $controlNumber, Student $student): void
    {
        $taken = Student::query()
            ->where('control_number', $controlNumber)
            ->whereKeyNot($student->getKey())
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'control_number' => __('form.control_number_taken'),
            ]);
        }
    }
}
