<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Catalog;

use App\Domain\Academic\Actions\CreateRequiredDocumentAction;
use App\Domain\Academic\Actions\DeleteRequiredDocumentAction;
use App\Domain\Academic\Actions\UpdateRequiredDocumentAction;
use App\Domain\Academic\Data\RequiredDocumentData;
use App\Domain\Academic\Models\RequiredDocument;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Required-documents catalog CRUD (spec 009). This catalog carries no unique
 * column and no SoftDeletes (its migration omits both); `description` is
 * nullable, `max_size_kb` an integer cap.
 */
final class RequiredDocumentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Catalogs/RequiredDocuments', [
            'required_documents' => RequiredDocument::query()
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'allowed_mimes', 'max_size_kb'])
                ->map(fn (RequiredDocument $document): array => [
                    'id' => $document->id,
                    'name' => $document->name,
                    'description' => $document->description,
                    'allowed_mimes' => $document->allowed_mimes,
                    'max_size_kb' => $document->max_size_kb,
                ])->all(),
        ]);
    }

    public function store(RequiredDocumentData $data, CreateRequiredDocumentAction $action): RedirectResponse
    {
        $action->handle($data);

        return back()->with('success', __('catalogs.created'));
    }

    public function update(RequiredDocument $requiredDocument, RequiredDocumentData $data, UpdateRequiredDocumentAction $action): RedirectResponse
    {
        $action->handle($requiredDocument, $data);

        return back()->with('success', __('catalogs.updated'));
    }

    public function destroy(RequiredDocument $requiredDocument, DeleteRequiredDocumentAction $action): RedirectResponse
    {
        $action->handle($requiredDocument);

        return back()->with('success', __('catalogs.deleted'));
    }
}
