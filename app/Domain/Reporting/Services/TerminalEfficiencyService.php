<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use Illuminate\Support\Collection;

/**
 * REPORT-01 — terminal efficiency: graduates ÷ total, per enrollment cohort, per
 * program, and overall, as a deliberate INTEGER percent (D-RATE-INT — no float in
 * the contract).
 *
 * Each axis is ONE grouped aggregate over the DemoScope-scoped {@see Student} model
 * using Postgres `count(*) filter (where status = ?)` (the binding is the enum
 * constant — parameterised, no user input), normalised to an int rate in PHP. The
 * cohort axis groups by ENROLLMENT year (D-YEAR — "the 2019 cohort"). NEVER
 * withoutGlobalScopes() — it would leak real counts into a demo report.
 */
final class TerminalEfficiencyService
{
    /**
     * One row per enrollment-year cohort, ordered by year desc.
     *
     * @return Collection<int, array{cohort_year: int, total: int, graduates: int, rate: int}>
     */
    public function byCohort(): Collection
    {
        return Student::query()
            ->selectRaw('extract(year from enrollment_date)::int as cohort_year')
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where status = ?) as graduates', [GraduationStatus::Graduated->value])
            ->whereNotNull('enrollment_date')
            ->groupBy('cohort_year')
            ->orderByDesc('cohort_year')
            ->get()
            ->map(function (Student $row): array {
                $total = $this->toInt($row->getAttribute('total'));
                $graduates = $this->toInt($row->getAttribute('graduates'));

                return [
                    'cohort_year' => $this->toInt($row->getAttribute('cohort_year')),
                    'total' => $total,
                    'graduates' => $graduates,
                    'rate' => $this->rate($graduates, $total),
                ];
            })
            ->values();
    }

    /**
     * One row per program, ordered by program name.
     *
     * @return Collection<int, array{program_id: int, program_name: string, total: int, graduates: int, rate: int}>
     */
    public function byProgram(): Collection
    {
        return Student::query()
            ->join('programs', 'programs.id', '=', 'students.program_id')
            ->selectRaw('students.program_id as program_id')
            ->selectRaw('programs.name as program_name')
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where students.status = ?) as graduates', [GraduationStatus::Graduated->value])
            ->groupBy('students.program_id', 'programs.name')
            ->orderBy('programs.name')
            ->get()
            ->map(function (Student $row): array {
                $total = $this->toInt($row->getAttribute('total'));
                $graduates = $this->toInt($row->getAttribute('graduates'));

                return [
                    'program_id' => $this->toInt($row->getAttribute('program_id')),
                    'program_name' => $this->toStringValue($row->getAttribute('program_name')),
                    'total' => $total,
                    'graduates' => $graduates,
                    'rate' => $this->rate($graduates, $total),
                ];
            })
            ->values();
    }

    /**
     * The single headline rate across every scoped student.
     *
     * @return array{total: int, graduates: int, rate: int}
     */
    public function overall(): array
    {
        $row = Student::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where status = ?) as graduates', [GraduationStatus::Graduated->value])
            ->first();

        $total = $row === null ? 0 : $this->toInt($row->getAttribute('total'));
        $graduates = $row === null ? 0 : $this->toInt($row->getAttribute('graduates'));

        return [
            'total' => $total,
            'graduates' => $graduates,
            'rate' => $this->rate($graduates, $total),
        ];
    }

    /**
     * The int-percent SSOT (D-RATE-INT): a rounded integer 0-100, total === 0 → 0.
     * Never a float, never a pre-formatted string.
     */
    private function rate(int $graduates, int $total): int
    {
        return $total === 0 ? 0 : (int) round($graduates / $total * 100);
    }

    /**
     * Postgres aggregates (count(*), count(*) filter, extract) arrive as `mixed`
     * via the magic attribute accessor; guard before casting (PHPStan L9 — never
     * `(int)` on a raw mixed).
     */
    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function toStringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
