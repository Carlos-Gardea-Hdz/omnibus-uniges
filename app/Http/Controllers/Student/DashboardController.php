<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only overview home for the authenticated student (SPEC §7). Summarises
 * the 9-state graduation journey and points to the single next action via a
 * pure-{@see GraduationStatus} CTA. No write, no Action — the DemoScope on
 * {@see Student} isolates the demo student automatically.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user()?->student;
        $status = $student?->status;

        return Inertia::render('Student/Dashboard', [
            'student_id' => $student?->id,
            'control_number' => $student?->control_number,
            'full_name' => $student === null ? null : trim("{$student->first_name} {$student->last_name}"),
            'status' => $status?->value,
            'step' => $status?->step(),
            'total_steps' => 9,
            'is_graduated' => $status === GraduationStatus::Graduated,
            'cta' => $status === null ? null : $this->cta($status),
            'form_b_observations' => $student?->form_b_observations,
        ]);
    }

    /**
     * The single next action for a state, or null at the terminal Graduated
     * state (the page renders a celebration block instead).
     *
     * @return array{route: string, label_key: string}|null
     */
    private function cta(GraduationStatus $status): ?array
    {
        return match ($status) {
            GraduationStatus::FormBPending => ['route' => route('student.form-b.create'), 'label_key' => 'dashboard.student.cta.form_b'],
            GraduationStatus::FormBReview => ['route' => route('student.status'), 'label_key' => 'dashboard.student.cta.status_review'],
            GraduationStatus::FormBRejected => ['route' => route('student.form-b.create'), 'label_key' => 'dashboard.student.cta.form_b_fix'],
            GraduationStatus::AnnexesPending => ['route' => route('student.documents.index'), 'label_key' => 'dashboard.student.cta.documents_begin'],
            GraduationStatus::AnnexIiiPending => ['route' => route('student.documents.index'), 'label_key' => 'dashboard.student.cta.documents'],
            GraduationStatus::PaymentPending => ['route' => route('student.payment.index'), 'label_key' => 'dashboard.student.cta.payment'],
            GraduationStatus::JuryAssigned => ['route' => route('student.status'), 'label_key' => 'dashboard.student.cta.status_track'],
            GraduationStatus::CeremonyScheduled => ['route' => route('student.status'), 'label_key' => 'dashboard.student.cta.status_ceremony'],
            GraduationStatus::Graduated => null,
        };
    }
}
