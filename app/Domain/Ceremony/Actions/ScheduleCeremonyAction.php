<?php

declare(strict_types=1);

namespace App\Domain\Ceremony\Actions;

use App\Domain\Ceremony\Data\ScheduleCeremonyData;
use App\Domain\Ceremony\Events\CeremonyScheduled;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\StateMachine\GraduationStateMachine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Staff schedule a graduation ceremony for a jury-assigned student, advancing
 * the workflow JuryAssigned -> CeremonyScheduled (7 -> 8). The date/location
 * have already cleared the CeremonyDate rule at the DTO boundary; the state
 * machine guards the source state, all inside one transaction. Emits the
 * workflow progress event and the ceremony-scheduled broadcast.
 */
final class ScheduleCeremonyAction
{
    public function __construct(
        private readonly GraduationStateMachine $stateMachine,
    ) {}

    public function handle(ScheduleCeremonyData $data, Student $student): Student
    {
        return DB::transaction(function () use ($data, $student): Student {
            // Serialize concurrent transitions: re-load the row under a
            // pessimistic lock and re-read the source state from it, so a
            // double-click / retry / two-staff race loses the guard cleanly
            // instead of double-firing the transition (and its events).
            $student = Student::query()->whereKey($student->getKey())->lockForUpdate()->firstOrFail();

            $from = $student->status;
            $to = GraduationStatus::CeremonyScheduled;

            $this->stateMachine->assertCanTransition($from, $to);

            $student->setAttribute('ceremony_date', Carbon::parse($data->ceremony_date));
            $student->ceremony_location = $data->ceremony_location;
            $student->status = $to;
            $student->save();

            StudentStatusChanged::dispatch($student, $from, $to);
            CeremonyScheduled::dispatch($student);

            return $student;
        });
    }
}
