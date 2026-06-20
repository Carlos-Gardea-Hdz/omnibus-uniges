<?php

declare(strict_types=1);

namespace App\Http\Controllers\Graduation;

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only staff overview home (SPEC §7). A single grouped-count query over the
 * DemoScope-scoped {@see Student} model feeds the full 9-state breakdown, the
 * five pending-stage queue counts and the headline totals.
 *
 * The status histogram is intentionally produced by ONE `group by` query — never
 * `withoutGlobalScope(DemoScope::class)`: bypassing the scope would leak real
 * counts into a demo dashboard (and vice versa), the central demo-isolation
 * guarantee of slice 006.
 */
final class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        /** @var Collection<string, int> $counts */
        $counts = Student::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Graduation/AdminDashboard', [
            'status_breakdown' => $this->breakdown($counts),
            'queues' => $this->queues($counts),
            'totals' => $this->totals($counts),
        ]);
    }

    /**
     * One row per state (all 9, in step() order), counts defaulting to 0.
     *
     * @param  Collection<string, int>  $counts
     * @return list<array{status: string, step: int, label_key: string, color: string, count: int}>
     */
    private function breakdown(Collection $counts): array
    {
        return array_map(static fn (GraduationStatus $case): array => [
            'status' => $case->value,
            'step' => $case->step(),
            'label_key' => $case->labelKey(),
            'color' => $case->color(),
            'count' => (int) ($counts[$case->value] ?? 0),
        ], GraduationStatus::cases());
    }

    /**
     * The five pending-stage work queues, each a bucket of the histogram.
     *
     * @param  Collection<string, int>  $counts
     * @return list<array{key: string, label_key: string, count: int, route: string}>
     */
    private function queues(Collection $counts): array
    {
        return [
            ['key' => 'form_b', 'label_key' => 'dashboard.admin.queue.form_b', 'count' => (int) ($counts[GraduationStatus::FormBReview->value] ?? 0), 'route' => route('admin.graduation.review')],
            ['key' => 'documents', 'label_key' => 'dashboard.admin.queue.documents', 'count' => (int) ($counts[GraduationStatus::AnnexIiiPending->value] ?? 0), 'route' => route('admin.graduation.documents.index')],
            ['key' => 'jury', 'label_key' => 'dashboard.admin.queue.jury', 'count' => (int) ($counts[GraduationStatus::PaymentPending->value] ?? 0), 'route' => route('admin.graduation.jury.index')],
            ['key' => 'ceremony', 'label_key' => 'dashboard.admin.queue.ceremony', 'count' => (int) ($counts[GraduationStatus::JuryAssigned->value] ?? 0), 'route' => route('admin.graduation.ceremony.index')],
            ['key' => 'graduation', 'label_key' => 'dashboard.admin.queue.graduation', 'count' => (int) ($counts[GraduationStatus::CeremonyScheduled->value] ?? 0), 'route' => route('admin.graduation.ceremony.index')],
        ];
    }

    /**
     * Headline totals derived from the same histogram (no second query).
     *
     * @param  Collection<string, int>  $counts
     * @return array{students: int, graduates: int, in_progress: int}
     */
    private function totals(Collection $counts): array
    {
        $students = array_sum(array_map('intval', $counts->all()));
        $graduates = (int) ($counts[GraduationStatus::Graduated->value] ?? 0);

        return [
            'students' => $students,
            'graduates' => $graduates,
            'in_progress' => $students - $graduates,
        ];
    }
}
