<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Catalog;

use App\Domain\Academic\Actions\CreateProfessorAction;
use App\Domain\Academic\Actions\DeleteProfessorAction;
use App\Domain\Academic\Actions\UpdateProfessorAction;
use App\Domain\Academic\Data\ProfessorData;
use App\Domain\Academic\Models\Professor;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Graduation\JuryController;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Professors catalog CRUD (spec 009). `mother_last_name` is nullable, so the
 * composed `full_name` collapses the gaps via trim() (mirrors
 * {@see JuryController::professors()}).
 */
final class ProfessorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Catalogs/Professors', [
            'professors' => Professor::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'mother_last_name', 'email'])
                ->map(fn (Professor $professor): array => [
                    'id' => $professor->id,
                    'first_name' => $professor->first_name,
                    'last_name' => $professor->last_name,
                    'mother_last_name' => $professor->mother_last_name,
                    'email' => $professor->email,
                    'full_name' => trim("{$professor->first_name} {$professor->last_name} {$professor->mother_last_name}"),
                ])->all(),
        ]);
    }

    public function store(ProfessorData $data, CreateProfessorAction $action): RedirectResponse
    {
        $action->handle($data);

        return back()->with('success', __('catalogs.created'));
    }

    public function update(Professor $professor, ProfessorData $data, UpdateProfessorAction $action): RedirectResponse
    {
        $action->handle($professor, $data);

        return back()->with('success', __('catalogs.updated'));
    }

    public function destroy(Professor $professor, DeleteProfessorAction $action): RedirectResponse
    {
        $action->handle($professor);

        return back()->with('success', __('catalogs.deleted'));
    }
}
