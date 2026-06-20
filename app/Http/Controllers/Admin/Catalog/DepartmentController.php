<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Catalog;

use App\Domain\Academic\Actions\CreateDepartmentAction;
use App\Domain\Academic\Actions\DeleteDepartmentAction;
use App\Domain\Academic\Actions\UpdateDepartmentAction;
use App\Domain\Academic\Data\DepartmentData;
use App\Domain\Academic\Models\Department;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Departments catalog CRUD (spec 009). Anemic: each mutation hands a validated
 * {@see DepartmentData} (resolved via the method signature → web failure is
 * 302 + session errors, never 422) to its Action, which owns the write.
 */
final class DepartmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Catalogs/Departments', [
            'departments' => Department::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (Department $department): array => [
                    'id' => $department->id,
                    'code' => $department->code,
                    'name' => $department->name,
                ])->all(),
        ]);
    }

    public function store(DepartmentData $data, CreateDepartmentAction $action): RedirectResponse
    {
        $action->handle($data);

        return back()->with('success', __('catalogs.created'));
    }

    public function update(Department $department, DepartmentData $data, UpdateDepartmentAction $action): RedirectResponse
    {
        $action->handle($department, $data);

        return back()->with('success', __('catalogs.updated'));
    }

    public function destroy(Department $department, DeleteDepartmentAction $action): RedirectResponse
    {
        $action->handle($department);

        return back()->with('success', __('catalogs.deleted'));
    }
}
