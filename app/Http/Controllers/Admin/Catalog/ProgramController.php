<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Catalog;

use App\Domain\Academic\Actions\CreateProgramAction;
use App\Domain\Academic\Actions\DeleteProgramAction;
use App\Domain\Academic\Actions\UpdateProgramAction;
use App\Domain\Academic\Data\ProgramData;
use App\Domain\Academic\Models\Department;
use App\Domain\Academic\Models\Program;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Programs catalog CRUD (spec 009). Each program belongs to a department (NOT
 * NULL FK), so the index eager-loads it for the `department_name` column and
 * ships the department picker options for the create/edit form.
 */
final class ProgramController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Catalogs/Programs', [
            'programs' => Program::query()
                ->with('department:id,name')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'department_id'])
                ->map(fn (Program $program): array => [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                    'department_id' => $program->department_id,
                    // department is a NOT NULL FK (eager-loaded above).
                    'department_name' => $program->department->name,
                ])->all(),
            'department_options' => Department::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Department $department): array => [
                    'id' => $department->id,
                    'name' => $department->name,
                ])->all(),
        ]);
    }

    public function store(ProgramData $data, CreateProgramAction $action): RedirectResponse
    {
        $action->handle($data);

        return back()->with('success', __('catalogs.created'));
    }

    public function update(Program $program, ProgramData $data, UpdateProgramAction $action): RedirectResponse
    {
        $action->handle($program, $data);

        return back()->with('success', __('catalogs.updated'));
    }

    public function destroy(Program $program, DeleteProgramAction $action): RedirectResponse
    {
        $action->handle($program);

        return back()->with('success', __('catalogs.deleted'));
    }
}
