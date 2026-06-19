<?php

declare(strict_types=1);

namespace App\Http\Controllers\Graduation;

use App\Domain\Graduation\Actions\ApproveFormBAction;
use App\Domain\Graduation\Actions\RejectFormBAction;
use App\Domain\Graduation\Data\ReviewFormBData;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Staff queue to approve or reject submitted Format B forms (SPEC §7). */
final class FormBReviewController extends Controller
{
    public function index(): Response
    {
        $students = Student::query()
            ->with(['program:id,name', 'graduationType:id,name'])
            ->where('status', GraduationStatus::FormBReview->value)
            ->orderByDesc('form_b_submitted_at')
            ->paginate(15)
            ->through(function (Student $student): array {
                // program & graduation_type are NOT NULL FKs (eager-loaded above).
                $program = $student->program;
                $graduationType = $student->graduationType;

                return [
                    'id' => $student->id,
                    'control_number' => $student->control_number,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'full_name' => trim("{$student->first_name} {$student->last_name}"),
                    'program_name' => $program->name,
                    'graduation_type_name' => $graduationType->name,
                    'gpa' => $student->gpa,
                    'status' => $student->status,
                    'form_b_submitted_at' => $student->form_b_submitted_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Graduation/Review', [
            'students' => $students,
        ]);
    }

    public function approve(Student $student, ApproveFormBAction $action): RedirectResponse
    {
        $action->handle($student);

        return redirect()->back()->with('success', __('admin.review.approved'));
    }

    public function reject(Student $student, ReviewFormBData $data, RejectFormBAction $action): RedirectResponse
    {
        $action->handle($student, $data);

        return redirect()->back()->with('success', __('admin.review.rejected'));
    }
}
