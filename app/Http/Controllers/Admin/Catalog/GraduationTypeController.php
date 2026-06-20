<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Catalog;

use App\Domain\Academic\Actions\CreateGraduationTypeAction;
use App\Domain\Academic\Actions\DeleteGraduationTypeAction;
use App\Domain\Academic\Actions\UpdateGraduationTypeAction;
use App\Domain\Academic\Data\GraduationTypeData;
use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\RequiredDocument;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Graduation-types catalog CRUD (spec 009). Each type pins a `requires_advisor`
 * flag and a set of required documents (the graduation_type_required_document
 * pivot). The index eager-loads that pivot to expose `required_document_ids` for
 * the multi-select, and ships the document picker options.
 */
final class GraduationTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Catalogs/GraduationTypes', [
            'graduation_types' => GraduationType::query()
                ->with('requiredDocuments:id')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'requires_advisor'])
                ->map(fn (GraduationType $type): array => [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'requires_advisor' => $type->requires_advisor,
                    'required_document_ids' => $type->requiredDocuments
                        ->map(fn (RequiredDocument $document): int => $document->id)
                        ->all(),
                ])->all(),
            'required_document_options' => RequiredDocument::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (RequiredDocument $document): array => [
                    'id' => $document->id,
                    'name' => $document->name,
                ])->all(),
        ]);
    }

    public function store(GraduationTypeData $data, CreateGraduationTypeAction $action): RedirectResponse
    {
        $action->handle($data);

        return back()->with('success', __('catalogs.created'));
    }

    public function update(GraduationType $graduationType, GraduationTypeData $data, UpdateGraduationTypeAction $action): RedirectResponse
    {
        $action->handle($graduationType, $data);

        return back()->with('success', __('catalogs.updated'));
    }

    public function destroy(GraduationType $graduationType, DeleteGraduationTypeAction $action): RedirectResponse
    {
        $action->handle($graduationType);

        return back()->with('success', __('catalogs.deleted'));
    }
}
