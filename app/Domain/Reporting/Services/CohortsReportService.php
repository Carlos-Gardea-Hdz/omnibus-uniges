<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use Illuminate\Support\Collection;

/**
 * REPORT-03 — cohorts overview: students grouped by ENROLLMENT year (D-YEAR), each
 * cohort split across the full 9-state {@see GraduationStatus} breakdown plus its
 * total / graduated / in-progress headline.
 *
 * ONE grouped aggregate (year × status) over the DemoScope-scoped {@see Student}
 * model, then pivoted in PHP into per-cohort rows with a fixed 9-key by_status map
 * (the slice-007 "normalise in PHP to a complete fixed shape" technique, extended to
 * two dimensions). No per-cohort or per-status loop query. NEVER
 * withoutGlobalScopes() — it would leak real students into a demo report.
 */
final class CohortsReportService
{
    /**
     * One row per enrollment-year cohort, ordered by year desc.
     *
     * @return Collection<int, array{
     *     cohort_year: int,
     *     total: int,
     *     graduated: int,
     *     in_progress: int,
     *     by_status: list<array{status: string, step: int, label_key: string, color: string, count: int}>,
     * }>
     */
    public function overview(): Collection
    {
        return Student::query()
            ->selectRaw('extract(year from enrollment_date)::int as cohort_year')
            ->selectRaw('status')
            ->selectRaw('count(*) as total')
            ->whereNotNull('enrollment_date')
            ->groupBy('cohort_year', 'status')
            ->get()
            ->groupBy(fn (Student $row): int => $this->toInt($row->getAttribute('cohort_year')))
            ->map(fn (Collection $rows, int|string $year): array => $this->cohortRow((int) $year, $rows))
            ->sortKeysDesc()
            ->values();
    }

    /**
     * Pivot one cohort's grouped rows into the fixed-shape per-cohort row.
     *
     * @param  Collection<int, Student>  $rows  raw {status, total} rows for this cohort
     * @return array{
     *     cohort_year: int,
     *     total: int,
     *     graduated: int,
     *     in_progress: int,
     *     by_status: list<array{status: string, step: int, label_key: string, color: string, count: int}>,
     * }
     */
    private function cohortRow(int $year, Collection $rows): array
    {
        /** @var array<string, int> $counts */
        $counts = $rows
            ->mapWithKeys(fn (Student $row): array => [
                $this->statusValue($row->getAttribute('status')) => $this->toInt($row->getAttribute('total')),
            ])
            ->all();

        $byStatus = array_map(static fn (GraduationStatus $case): array => [
            'status' => $case->value,
            'step' => $case->step(),
            'label_key' => $case->labelKey(),
            'color' => $case->color(),
            'count' => $counts[$case->value] ?? 0,
        ], GraduationStatus::cases());

        $total = array_sum(array_map('intval', $counts));
        $graduated = $counts[GraduationStatus::Graduated->value] ?? 0;

        return [
            'cohort_year' => $year,
            'total' => $total,
            'graduated' => $graduated,
            'in_progress' => $total - $graduated,
            'by_status' => $byStatus,
        ];
    }

    /**
     * The raw `status` aggregate column arrives as `mixed` (the enum cast applies to
     * a hydrated model, not a raw grouped select); resolve its backing value whether
     * it surfaced as a {@see GraduationStatus} or a plain string.
     */
    private function statusValue(mixed $status): string
    {
        if ($status instanceof GraduationStatus) {
            return $status->value;
        }

        return is_string($status) ? $status : '';
    }

    /**
     * Postgres `count(*)` / `extract` arrive as `mixed` via the magic accessor; guard
     * before casting (PHPStan L9 — never `(int)` on a raw mixed).
     */
    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
