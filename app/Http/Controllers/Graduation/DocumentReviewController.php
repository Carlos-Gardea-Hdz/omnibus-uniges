<?php

declare(strict_types=1);

namespace App\Http\Controllers\Graduation;

use App\Domain\Graduation\Actions\ApproveDocumentAction;
use App\Domain\Graduation\Actions\RejectDocumentAction;
use App\Domain\Graduation\Data\ReviewDocumentData;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\Models\StudentDocument;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/** Staff queue to approve or reject uploaded Annex III documents (SPEC §10). */
final class DocumentReviewController extends Controller
{
    /** Lifetime of a reviewer's temporary signed download URL. */
    private const int DOWNLOAD_TTL_HOURS = 24;

    public function index(): Response
    {
        $students = Student::query()
            ->with(['program:id,name', 'graduationType:id,name', 'documents.requiredDocument:id,name'])
            ->where('status', GraduationStatus::AnnexIiiPending->value)
            ->orderBy('control_number')
            ->paginate(15)
            ->through(fn (Student $student): array => $this->mapStudent($student));

        return Inertia::render('Graduation/DocumentReview', [
            'students' => $students,
        ]);
    }

    public function approve(StudentDocument $studentDocument, Request $request, ApproveDocumentAction $action): RedirectResponse
    {
        $action->handle($studentDocument, $this->reviewer($request));

        return redirect()->back()->with('success', __('documents.approved'));
    }

    public function reject(StudentDocument $studentDocument, ReviewDocumentData $data, Request $request, RejectDocumentAction $action): RedirectResponse
    {
        $action->handle($studentDocument, $data, $this->reviewer($request));

        return redirect()->back()->with('success', __('documents.rejected'));
    }

    /** The authenticated staff reviewer (route is role-gated, never null). */
    private function reviewer(Request $request): User
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return $user;
    }

    /**
     * Shape one student row with its documents for the page (SPEC §12).
     *
     * @return array<string, mixed>
     */
    private function mapStudent(Student $student): array
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
            'documents' => $student->documents->map(fn (StudentDocument $document): array => [
                'id' => $document->id,
                'name' => $document->requiredDocument?->name,
                'status' => $document->status->value,
                'original_filename' => $document->original_filename,
                'rejection_reason' => $document->rejection_reason,
                'download_url' => $document->file_path !== null
                    ? URL::temporarySignedRoute(
                        'student.documents.download',
                        now()->addHours(self::DOWNLOAD_TTL_HOURS),
                        ['document' => $document],
                    )
                    : null,
            ])->all(),
        ];
    }
}
