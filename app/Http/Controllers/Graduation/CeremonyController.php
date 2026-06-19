<?php

declare(strict_types=1);

namespace App\Http\Controllers\Graduation;

use App\Domain\Ceremony\Actions\MarkAsGraduatedAction;
use App\Domain\Ceremony\Actions\ScheduleCeremonyAction;
use App\Domain\Ceremony\Data\ScheduleCeremonyData;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** Staff queue to schedule the ceremony (7→8) and graduate students (8→9), completing the pipeline (SPEC §004). */
final class CeremonyController extends Controller
{
    public function index(): Response
    {
        $scheduling = Student::query()
            ->with(['program:id,name', 'graduationType:id,name'])
            ->where('status', GraduationStatus::JuryAssigned->value)
            ->orderBy('control_number')
            ->paginate(15)
            ->through(fn (Student $student): array => $this->mapScheduling($student));

        $graduating = Student::query()
            ->where('status', GraduationStatus::CeremonyScheduled->value)
            ->orderBy('control_number')
            ->paginate(15)
            ->through(fn (Student $student): array => $this->mapGraduating($student));

        return Inertia::render('Graduation/CeremonySchedule', [
            'scheduling' => $scheduling,
            'graduating' => $graduating,
        ]);
    }

    public function schedule(Student $student, ScheduleCeremonyData $data, ScheduleCeremonyAction $action): RedirectResponse
    {
        $action->handle($data, $student);

        return redirect()->back()->with('success', __('ceremony.scheduled'));
    }

    public function graduate(Student $student, MarkAsGraduatedAction $action): RedirectResponse
    {
        $action->handle($student);

        return redirect()->back()->with('success', __('ceremony.graduated'));
    }

    /**
     * Shape one jury-assigned student awaiting a ceremony date (SPEC §9).
     *
     * @return array<string, mixed>
     */
    private function mapScheduling(Student $student): array
    {
        // program & graduation_type are NOT NULL FKs (eager-loaded above).
        $program = $student->program;
        $graduationType = $student->graduationType;

        return [
            'id' => $student->id,
            'control_number' => $student->control_number,
            'full_name' => trim("{$student->first_name} {$student->last_name}"),
            'program_name' => $program->name,
            'graduation_type_name' => $graduationType->name,
        ];
    }

    /**
     * Shape one ceremony-scheduled student awaiting graduation (SPEC §9).
     *
     * @return array<string, mixed>
     */
    private function mapGraduating(Student $student): array
    {
        $ceremonyDate = $student->ceremony_date;

        return [
            'id' => $student->id,
            'control_number' => $student->control_number,
            'full_name' => trim("{$student->first_name} {$student->last_name}"),
            'ceremony_date' => $ceremonyDate === null ? null : Carbon::parse($ceremonyDate)->toIso8601String(),
            'ceremony_location' => $student->ceremony_location,
            'can_graduate' => $ceremonyDate !== null && ! Carbon::parse($ceremonyDate)->isFuture(),
        ];
    }
}
