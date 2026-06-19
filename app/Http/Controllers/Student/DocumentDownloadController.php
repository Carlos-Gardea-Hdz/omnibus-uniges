<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Graduation\Models\StudentDocument;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a stored document file. Reached only through a 24h temporary signed
 * URL (SPEC §10.4); the owning student or any staff member may download.
 */
final class DocumentDownloadController extends Controller
{
    public function show(StudentDocument $document, Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $owner = $document->student;
        abort_unless($owner !== null && ($owner->user_id === $user->id || $user->role->isStaff()), 403);
        abort_if($document->file_path === null, 404);

        return Storage::disk('local')->download($document->file_path, $document->original_filename);
    }
}
