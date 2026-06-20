<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Academic\Models\Program;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * REPORT-02 — the registrar's roster of Graduated students with their full record.
 *
 * Read-only: one list query over the DemoScope-scoped {@see Student} model (so a
 * demo coordinator sees only their sandbox, a real one only real data) eager-loaded
 * with the unscoped Program / GraduationType catalogs for display names. The year
 * filter is the GRADUATION year (D-YEAR — "graduates of 2025"); the program filter
 * is the program_id. NEVER withoutGlobalScopes() — it would leak real graduates into
 * a demo report.
 */
final class GraduatesReportService
{
    /**
     * The list of graduated students (scoped), filtered + ordered, as plain
     * snake_case row arrays ready for the Inertia prop / a future export.
     *
     * @return Collection<int, array{
     *     id: int,
     *     control_number: string,
     *     full_name: string,
     *     program_name: string,
     *     graduation_type_name: string,
     *     diploma_folio: string|null,
     *     record_book: string|null,
     *     record_sheet: string|null,
     *     graduation_date: string|null,
     * }>
     */
    public function graduates(?int $year, ?int $programId): Collection
    {
        return Student::query()
            ->with(['program:id,name', 'graduationType:id,name'])
            ->where('status', GraduationStatus::Graduated->value)
            ->when($year !== null, fn ($query) => $query->whereYear('graduation_date', $year))
            ->when($programId !== null, fn ($query) => $query->where('program_id', $programId))
            ->orderByDesc('graduation_date')
            ->orderBy('control_number')
            ->get()
            ->map(fn (Student $student): array => [
                'id' => (int) $student->id,
                'control_number' => (string) $student->control_number,
                'full_name' => trim("{$student->first_name} {$student->last_name}"),
                'program_name' => (string) $student->program->name,
                'graduation_type_name' => (string) $student->graduationType->name,
                'diploma_folio' => $student->diploma_folio,
                'record_book' => $student->record_book,
                'record_sheet' => $student->record_sheet,
                'graduation_date' => $this->toDate($student->graduation_date),
            ])
            ->values();
    }

    /**
     * Distinct graduation years present (desc) for the year <select>.
     *
     * One scoped DISTINCT aggregate; no N+1.
     *
     * @return list<int>
     */
    public function availableYears(): array
    {
        $years = Student::query()
            ->where('status', GraduationStatus::Graduated->value)
            ->whereNotNull('graduation_date')
            ->selectRaw('distinct extract(year from graduation_date)::int as graduation_year')
            ->orderByDesc('graduation_year')
            ->pluck('graduation_year')
            ->map(static fn (mixed $year): int => is_numeric($year) ? (int) $year : 0)
            ->all();

        return array_values($years);
    }

    /**
     * The program catalog (unscoped reference data) for the program <select>.
     *
     * Lives on the service so the controller touches no model directly (keeps it
     * anemic + arch-clean — symmetric with the other report controllers).
     *
     * @return list<array{id: int, name: string}>
     */
    public function programOptions(): array
    {
        $options = Program::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Program $program): array => [
                'id' => (int) $program->id,
                'name' => (string) $program->name,
            ])
            ->all();

        return array_values($options);
    }

    /**
     * The `graduation_date` attribute is statically typed `string|null` here (no
     * model property-tag declares it as Carbon); parse and serialise as a Y-m-d date
     * — the established codebase pattern (see StudentGraduated / CeremonyController).
     */
    private function toDate(mixed $value): ?string
    {
        // graduation_date is cast to Carbon on the model, but a raw string can
        // also reach here — accept both, reject anything else.
        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse($value)->toDateString();
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }
}
