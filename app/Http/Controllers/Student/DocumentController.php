<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Academic\Models\RequiredDocument;
use App\Domain\Graduation\Actions\BeginDocumentStageAction;
use App\Domain\Graduation\Actions\UploadDocumentAction;
use App\Domain\Graduation\Data\UploadDocumentData;
use App\Domain\Graduation\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/** Student self-service for the Annex III document stage (SPEC §10). */
final class DocumentController extends Controller
{
    /** Lifetime of a document download's temporary signed URL. */
    private const int DOWNLOAD_TTL_HOURS = 24;

    public function index(Request $request): Response
    {
        $student = $this->student($request);
        $student->loadMissing(['graduationType.requiredDocuments', 'documents']);

        return Inertia::render('Student/Documents', [
            'student_id' => $student->id,
            'status' => $student->status->value,
            'documents' => $this->documents($student),
        ]);
    }

    public function begin(Request $request, BeginDocumentStageAction $action): RedirectResponse
    {
        $action->handle($this->student($request));

        return redirect()->route('student.documents.index')->with('success', __('documents.stage_started'));
    }

    public function upload(UploadDocumentData $data, Request $request, UploadDocumentAction $action): RedirectResponse
    {
        $action->handle($data, $this->student($request));

        return redirect()->route('student.documents.index')->with('success', __('documents.uploaded'));
    }

    /** Resolve the authenticated student or fail loud (SPEC §10). */
    private function student(Request $request): Student
    {
        $student = $request->user()?->student;

        if ($student === null) {
            abort(404);
        }

        return $student;
    }

    /**
     * Shape the student's required-document rows for the page (SPEC §12).
     *
     * @return array<int, array<string, mixed>>
     */
    private function documents(Student $student): array
    {
        return $student->graduationType->requiredDocuments
            ->map(function (RequiredDocument $required) use ($student): array {
                $document = $student->documents->firstWhere('required_document_id', $required->id);
                $status = $document?->status->value ?? 'pending';

                return [
                    'id' => $document?->id,
                    'required_document_id' => $required->id,
                    'name' => $required->name,
                    'description' => $required->description,
                    'status' => $status,
                    'original_filename' => $document?->original_filename,
                    'rejection_reason' => $document?->rejection_reason,
                    'uploaded_at' => $document?->uploaded_at?->toIso8601String(),
                    'download_url' => $document !== null && $status !== 'pending'
                        ? URL::temporarySignedRoute(
                            'student.documents.download',
                            now()->addHours(self::DOWNLOAD_TTL_HOURS),
                            ['document' => $document],
                        )
                        : null,
                    'allowed_mimes' => $required->allowed_mimes,
                    'max_size_kb' => $required->max_size_kb,
                ];
            })->values()->all();
    }
}
