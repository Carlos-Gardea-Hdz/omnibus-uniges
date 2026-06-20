<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Runtime prop-contract + counts-correctness test for the read-only admin
 * dashboard (CONTRACT §4, §5; SPEC §7). Seeds a known mix of students across
 * pipeline stages and locks AdminDashboardController::index() to:
 *
 *   - status_breakdown: EXACTLY 9 rows (one per GraduationStatus, in step()
 *     order), each carrying the value string, step, label_key, color token, and
 *     the count for that bucket (missing states = 0);
 *   - queues: EXACTLY 5 quick-link rows whose counts are buckets of the same
 *     histogram and whose `route` is a resolved URL;
 *   - totals: students = sum, graduates = graduated bucket, in_progress = diff.
 *
 * Boots the app + PostgreSQL 18 (tests/Feature, RefreshDatabase).
 */

/**
 * The exact stage mix this suite asserts against (CONTRACT §8.2):
 *   form_b_review × 3, annex_iii_pending × 2, payment_pending × 1,
 *   jury_assigned × 1, ceremony_scheduled × 2, graduated × 4  → 13 students.
 */
function seedAdminDashboardMix(): void
{
    Student::factory()->count(3)->formBReview()->create();
    Student::factory()->count(2)->documentsStage()->create();   // annex_iii_pending
    Student::factory()->count(1)->paymentPending()->create();
    Student::factory()->count(1)->juryAssigned()->create();
    Student::factory()->count(2)->ceremonyScheduled()->create();
    Student::factory()->count(4)->ceremonyPassed()->create([
        'status' => GraduationStatus::Graduated,
        'graduation_date' => now()->toDateString(),
    ]);
}

it('renders Graduation/AdminDashboard with a 9-row status breakdown in step order', function (): void {
    $admin = User::factory()->admin()->create();
    seedAdminDashboardMix();

    /** @var array<string, int> $expectedCounts */
    $expectedCounts = [
        'form_b_pending' => 0,
        'form_b_review' => 3,
        'form_b_rejected' => 0,
        'annexes_pending' => 0,
        'annex_iii_pending' => 2,
        'payment_pending' => 1,
        'jury_assigned' => 1,
        'ceremony_scheduled' => 2,
        'graduated' => 4,
    ];

    actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Graduation/AdminDashboard')
                ->has('status_breakdown', 9)
                // Rows are in GraduationStatus::cases() / step() order, each fully shaped.
                ->where('status_breakdown', function ($rows) use ($expectedCounts): bool {
                    $rows = collect($rows);
                    $cases = GraduationStatus::cases();

                    foreach ($cases as $i => $case) {
                        $row = $rows[$i];

                        if ($row['status'] !== $case->value
                            || $row['step'] !== $case->step()
                            || $row['label_key'] !== 'status.'.$case->value
                            || $row['label_key'] !== $case->labelKey()
                            || $row['color'] !== $case->color()
                            || $row['count'] !== $expectedCounts[$case->value]) {
                            return false;
                        }
                    }

                    return $rows->count() === 9;
                }),
        );
});

it('renders 5 queue rows whose counts are buckets of the histogram with resolved routes', function (): void {
    $admin = User::factory()->admin()->create();
    seedAdminDashboardMix();

    actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Graduation/AdminDashboard')
                ->has('queues', 5)
                // form_b ← form_b_review = 3
                ->where('queues.0.key', 'form_b')
                ->where('queues.0.count', 3)
                ->where('queues.0.route', route('admin.graduation.review'))
                ->where('queues.0.label_key', 'dashboard.admin.queue.form_b')
                // documents ← annex_iii_pending = 2
                ->where('queues.1.key', 'documents')
                ->where('queues.1.count', 2)
                ->where('queues.1.route', route('admin.graduation.documents.index'))
                ->where('queues.1.label_key', 'dashboard.admin.queue.documents')
                // jury ← payment_pending = 1
                ->where('queues.2.key', 'jury')
                ->where('queues.2.count', 1)
                ->where('queues.2.route', route('admin.graduation.jury.index'))
                ->where('queues.2.label_key', 'dashboard.admin.queue.jury')
                // ceremony ← jury_assigned = 1
                ->where('queues.3.key', 'ceremony')
                ->where('queues.3.count', 1)
                ->where('queues.3.route', route('admin.graduation.ceremony.index'))
                ->where('queues.3.label_key', 'dashboard.admin.queue.ceremony')
                // graduation ← ceremony_scheduled = 2
                ->where('queues.4.key', 'graduation')
                ->where('queues.4.count', 2)
                ->where('queues.4.route', route('admin.graduation.ceremony.index'))
                ->where('queues.4.label_key', 'dashboard.admin.queue.graduation'),
        );
});

it('derives totals from the histogram (students, graduates, in_progress)', function (): void {
    $admin = User::factory()->admin()->create();
    seedAdminDashboardMix();

    actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Graduation/AdminDashboard')
                ->where('totals.students', 13)
                ->where('totals.graduates', 4)
                ->where('totals.in_progress', 9), // 13 - 4
        );
});

it('reports a zeroed breakdown and empty totals when no students exist', function (): void {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Graduation/AdminDashboard')
                ->has('status_breakdown', 9)
                ->where('status_breakdown', fn ($rows) => collect($rows)->every(
                    fn ($row) => $row['count'] === 0,
                ))
                ->where('totals.students', 0)
                ->where('totals.graduates', 0)
                ->where('totals.in_progress', 0)
                ->where('queues', fn ($rows) => collect($rows)->every(
                    fn ($row) => $row['count'] === 0,
                )),
        );
});
