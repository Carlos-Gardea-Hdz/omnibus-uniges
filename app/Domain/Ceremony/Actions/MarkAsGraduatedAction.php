<?php

declare(strict_types=1);

namespace App\Domain\Ceremony\Actions;

use App\Domain\Ceremony\Events\StudentGraduated;
use App\Domain\Ceremony\Services\DiplomaFolioGenerator;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\StateMachine\GraduationStateMachine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff mark a student graduated once their scheduled ceremony has passed,
 * driving the terminal CeremonyScheduled -> Graduated (8 -> 9) transition. The
 * ceremony date must exist and lie in the past (CERE-02); a race-safe diploma
 * folio + record book/sheet are minted inside the same transaction (the folio
 * generator holds its row lock), and the graduation date is stamped. Emits the
 * workflow progress event and the graduation broadcast.
 */
final class MarkAsGraduatedAction
{
    public function __construct(
        private readonly GraduationStateMachine $stateMachine,
        private readonly DiplomaFolioGenerator $folioGenerator,
    ) {}

    public function handle(Student $student): Student
    {
        return DB::transaction(function () use ($student): Student {
            // Serialize concurrent transitions: re-load the row under a
            // pessimistic lock and re-read state/ceremony from it, so a
            // double-click / retry / two-staff race loses the guard cleanly
            // instead of minting a second diploma folio and re-firing events.
            $student = Student::query()->whereKey($student->getKey())->lockForUpdate()->firstOrFail();

            $ceremonyDate = $student->ceremony_date;

            if ($ceremonyDate === null || Carbon::parse($ceremonyDate)->isFuture()) {
                throw ValidationException::withMessages([
                    'ceremony_date' => __('validation.ceremony.not_passed'),
                ]);
            }

            $from = $student->status;
            $to = GraduationStatus::Graduated;

            $this->stateMachine->assertCanTransition($from, $to);

            $folio = $this->folioGenerator->generate($student);

            $student->diploma_folio = $folio->folio;
            $student->record_book = $folio->book;
            $student->record_sheet = $folio->sheet;
            $student->graduation_date = now()->toDateString();
            $student->status = $to;
            $student->save();

            StudentStatusChanged::dispatch($student, $from, $to);
            StudentGraduated::dispatch($student);

            return $student;
        });
    }
}
