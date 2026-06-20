<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Catalog;

use App\Domain\Academic\Actions\CreateStudyPlanAction;
use App\Domain\Academic\Actions\DeleteStudyPlanAction;
use App\Domain\Academic\Actions\UpdateStudyPlanAction;
use App\Domain\Academic\Data\StudyPlanData;
use App\Domain\Academic\Models\Program;
use App\Domain\Academic\Models\StudyPlan;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Study-plans catalog CRUD (spec 009). Each plan belongs to a program (NOT NULL
 * FK), eager-loaded for the `program_name` column; the program picker options
 * feed the create/edit form.
 */
final class StudyPlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Catalogs/StudyPlans', [
            'study_plans' => StudyPlan::query()
                ->with('program:id,name')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'program_id'])
                ->map(fn (StudyPlan $plan): array => [
                    'id' => $plan->id,
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'program_id' => $plan->program_id,
                    // program is a NOT NULL FK (eager-loaded above).
                    'program_name' => $plan->program->name,
                ])->all(),
            'program_options' => Program::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Program $program): array => [
                    'id' => $program->id,
                    'name' => $program->name,
                ])->all(),
        ]);
    }

    public function store(StudyPlanData $data, CreateStudyPlanAction $action): RedirectResponse
    {
        $action->handle($data);

        return back()->with('success', __('catalogs.created'));
    }

    public function update(StudyPlan $studyPlan, StudyPlanData $data, UpdateStudyPlanAction $action): RedirectResponse
    {
        $action->handle($studyPlan, $data);

        return back()->with('success', __('catalogs.updated'));
    }

    public function destroy(StudyPlan $studyPlan, DeleteStudyPlanAction $action): RedirectResponse
    {
        $action->handle($studyPlan);

        return back()->with('success', __('catalogs.deleted'));
    }
}
