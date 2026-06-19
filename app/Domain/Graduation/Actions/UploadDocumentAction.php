<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Actions;

use App\Domain\Academic\Models\RequiredDocument;
use App\Domain\Graduation\Data\UploadDocumentData;
use App\Domain\Graduation\Enums\DocumentStatus;
use App\Domain\Graduation\Events\DocumentStatusChanged;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\Models\StudentDocument;
use App\Domain\Shared\ValueObjects\FileToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Stores a student's uploaded file for a required document and flips the row to
 * Uploaded, awaiting staff review. No state-machine transition (the student
 * stays in AnnexIiiPending). Enforces the per-document MIME/size rules here
 * because they are dynamic; the DTO only carries the global 10MB cap.
 */
final class UploadDocumentAction
{
    public function handle(UploadDocumentData $data, Student $student): StudentDocument
    {
        // Resolve the required document THROUGH the student's graduation type so
        // a student can never upload against a slot their checklist excludes.
        /** @var RequiredDocument $requiredDocument */
        $requiredDocument = $student->graduationType->requiredDocuments()
            ->findOrFail($data->required_document_id);

        $this->assertFileAllowed($data, $requiredDocument);

        $document = StudentDocument::query()->firstOrNew([
            'student_id' => $student->id,
            'required_document_id' => $requiredDocument->id,
        ]);

        $oldPath = $document->file_path;
        $token = $document->file_token ?? (string) FileToken::generate();
        $extension = $data->file->getClientOriginalExtension();
        $directory = "documents/{$student->id}/uploads";
        $filename = "{$requiredDocument->id}_{$token}.{$extension}";
        $path = "{$directory}/{$filename}";

        // Write the file BEFORE the DB work so a transaction rollback never
        // leaves a committed row pointing at nothing; on commit failure the
        // freshly written file is removed (no orphaned bytes).
        Storage::disk('local')->putFileAs($directory, $data->file, $filename);

        try {
            DB::transaction(function () use ($document, $data, $path, $token): void {
                $document->fill([
                    'status' => DocumentStatus::Uploaded,
                    'file_path' => $path,
                    'file_token' => $token,
                    'original_filename' => $data->file->getClientOriginalName(),
                    // Server-sniffed MIME (finfo), never the spoofable client header.
                    'mime_type' => $data->file->getMimeType(),
                    'file_size' => $data->file->getSize(),
                    'uploaded_at' => now(),
                    'rejection_reason' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);
                $document->save();

                DocumentStatusChanged::dispatch($document, false);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);

            throw $e;
        }

        // Replacement committed — drop the previous file if the path changed.
        if ($oldPath !== null && $oldPath !== $path && Storage::disk('local')->exists($oldPath)) {
            Storage::disk('local')->delete($oldPath);
        }

        return $document;
    }

    /**
     * Per-document MIME and size enforcement (dynamic, from the required doc).
     * The MIME is the server-guessed type, never the client-supplied header.
     *
     * @throws ValidationException
     */
    private function assertFileAllowed(UploadDocumentData $data, RequiredDocument $requiredDocument): void
    {
        $allowedMimes = array_map('trim', explode(',', $requiredDocument->allowed_mimes));

        if (! in_array($data->file->getMimeType(), $allowedMimes, strict: true)) {
            throw ValidationException::withMessages([
                'file' => __('validation.documents.mime'),
            ]);
        }

        if ((int) ceil($data->file->getSize() / 1024) > $requiredDocument->max_size_kb) {
            throw ValidationException::withMessages([
                'file' => __('validation.documents.max_size'),
            ]);
        }
    }
}
