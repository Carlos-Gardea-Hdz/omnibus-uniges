<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Academic\Models\Professor;
use App\Domain\Jury\Enums\JuryRole;
use App\Domain\Jury\Models\JuryAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * CERT-01 — the data behind each professor's judge certificates: per professor who
 * has served on ≥1 jury, the list of juries they sat (which student, which
 * {@see JuryRole}, the ceremony/graduation context).
 *
 * ONE query over the DemoScope-scoped {@see JuryAssignment} model (+ eager loads),
 * memoised for the request so both {@see byProfessor()} and {@see professorOptions()}
 * share it (no N+1, one jury query per report). The four professor columns are
 * INVERTED into per-professor rows in PHP (D-INVERT — simpler + scope-safe than four
 * SQL UNIONs; the data volume per session is small). A null substitute is skipped (a
 * withoutSubstitute() jury yields 3 tuples). The export (.docx) is DEFERRED — this
 * returns the export-ready rows. NEVER withoutGlobalScopes() — it would leak real
 * juries into a demo report.
 *
 * @phpstan-type AssignmentRow array{
 *     jury_assignment_id: int,
 *     role: string,
 *     role_label_key: string,
 *     student_id: int,
 *     student_control_number: string,
 *     student_name: string,
 *     program_name: string,
 *     student_status: string,
 *     ceremony_date: string|null,
 *     graduation_date: string|null,
 * }
 * @phpstan-type ProfessorRow array{
 *     professor_id: int,
 *     professor_name: string,
 *     assignment_count: int,
 *     assignments: list<AssignmentRow>,
 * }
 */
final class JudgeCertificatesService
{
    /**
     * The single scoped jury query, memoised for the request.
     *
     * @var Collection<int, JuryAssignment>|null
     */
    private ?Collection $assignments = null;

    /**
     * Per professor (optionally narrowed to one), the juries they sat — ordered by
     * professor name, assignments ordered by jury_assignment_id.
     *
     * @return Collection<int, ProfessorRow>
     */
    public function byProfessor(?int $professorId): Collection
    {
        /** @var array<int, array{professor: Professor, assignments: list<AssignmentRow>}> $buckets */
        $buckets = [];

        foreach ($this->loadAssignments() as $assignment) {
            foreach ($this->rolePairs($assignment) as [$professor, $role]) {
                $id = (int) $professor->id;

                if ($professorId !== null && $id !== $professorId) {
                    continue;
                }

                $buckets[$id] ??= ['professor' => $professor, 'assignments' => []];
                $buckets[$id]['assignments'][] = $this->assignmentRow($assignment, $role);
            }
        }

        return collect($buckets)
            ->map(fn (array $bucket): array => [
                'professor_id' => (int) $bucket['professor']->id,
                'professor_name' => $this->professorName($bucket['professor']),
                'assignment_count' => count($bucket['assignments']),
                'assignments' => $bucket['assignments'],
            ])
            ->sortBy('professor_name')
            ->values();
    }

    /**
     * Professors who have served on ≥1 jury, for the filter <select> — derived in
     * PHP from the same memoised scoped query (no extra query), ordered by name.
     *
     * @return list<array{id: int, name: string}>
     */
    public function professorOptions(): array
    {
        /** @var array<int, string> $options */
        $options = [];

        foreach ($this->loadAssignments() as $assignment) {
            foreach ($this->rolePairs($assignment) as [$professor]) {
                $options[(int) $professor->id] = $this->professorName($professor);
            }
        }

        asort($options);

        return array_map(
            static fn (int $id, string $name): array => ['id' => $id, 'name' => $name],
            array_keys($options),
            array_values($options),
        );
    }

    /**
     * The seated (professor, role) pairs for one jury — a null substitute is skipped
     * (a withoutSubstitute() jury yields 3 pairs). Relations are read explicitly (no
     * dynamic property access).
     *
     * @return list<array{0: Professor, 1: JuryRole}>
     */
    private function rolePairs(JuryAssignment $assignment): array
    {
        $pairs = [
            [$assignment->president, JuryRole::President],
            [$assignment->secretary, JuryRole::Secretary],
            [$assignment->vocal, JuryRole::Vocal],
        ];

        if ($assignment->substitute !== null) {
            $pairs[] = [$assignment->substitute, JuryRole::Substitute];
        }

        return $pairs;
    }

    /**
     * The single scoped jury query (+ eager loads), memoised. No N+1.
     *
     * @return Collection<int, JuryAssignment>
     */
    private function loadAssignments(): Collection
    {
        return $this->assignments ??= JuryAssignment::query()
            // Skip an assignment if its student or a REQUIRED professor has been
            // soft-deleted: the SoftDeletes scope would null the eager-load and the
            // row shaper dereferences ->id/->name. (The nullable substitute is
            // handled by the null check in rolePairs.)
            ->whereHas('student')
            ->whereHas('president')
            ->whereHas('secretary')
            ->whereHas('vocal')
            ->with([
                'student:id,control_number,first_name,last_name,status,ceremony_date,graduation_date,program_id',
                'student.program:id,name',
                'president:id,first_name,last_name,mother_last_name',
                'secretary:id,first_name,last_name,mother_last_name',
                'vocal:id,first_name,last_name,mother_last_name',
                'substitute:id,first_name,last_name,mother_last_name',
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * Shape one (assignment, role) tuple into a per-assignment row.
     *
     * @return AssignmentRow
     */
    private function assignmentRow(JuryAssignment $assignment, JuryRole $role): array
    {
        $student = $assignment->student;

        return [
            'jury_assignment_id' => (int) $assignment->id,
            'role' => $role->value,
            'role_label_key' => $role->labelKey(),
            'student_id' => (int) $student->id,
            'student_control_number' => (string) $student->control_number,
            'student_name' => trim("{$student->first_name} {$student->last_name}"),
            'program_name' => (string) $student->program->name,
            'student_status' => $student->status->value,
            'ceremony_date' => $this->toIso8601($student->ceremony_date),
            'graduation_date' => $this->toDate($student->graduation_date),
        ];
    }

    /**
     * The `ceremony_date` attribute is statically typed `string|null` here (no model
     * property-tag declares it as Carbon); parse and serialise as ISO-8601 — the
     * established codebase pattern (see CeremonyController / StudentGraduated).
     */
    private function toIso8601(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toIso8601String();
    }

    /** The `graduation_date` attribute is statically typed `string|null`; parse to Y-m-d. */
    private function toDate(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    /** "first last [mother]" with single-spacing. */
    private function professorName(Professor $professor): string
    {
        return trim(implode(' ', array_filter([
            $professor->first_name,
            $professor->last_name,
            $professor->mother_last_name,
        ])));
    }
}
