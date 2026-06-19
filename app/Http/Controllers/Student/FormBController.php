<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\Professor;
use App\Domain\Academic\Models\Program;
use App\Domain\Academic\Models\StudyPlan;
use App\Domain\Graduation\Actions\SubmitFormBAction;
use App\Domain\Graduation\Data\SubmitFormBData;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Student self-service for the Format B submission (SPEC §7). */
final class FormBController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Student/FormB', [
            'student' => $request->user()?->student?->only([
                'control_number', 'first_name', 'last_name', 'mother_last_name', 'gender', 'gpa',
                'enrollment_date', 'thesis_title', 'thesis_abstract', 'program_id', 'graduation_type_id',
                'study_plan_id', 'advisor_id', 'phone', 'mobile', 'age', 'address_street',
                'address_neighborhood', 'address_ext_number', 'address_int_number', 'address_postal_code',
                'status', 'form_b_observations',
            ]),
            'catalogs' => $this->catalogs(),
        ]);
    }

    public function store(SubmitFormBData $data, Request $request, SubmitFormBAction $action): RedirectResponse
    {
        return $this->submit($data, $request, $action);
    }

    public function update(SubmitFormBData $data, Request $request, SubmitFormBAction $action): RedirectResponse
    {
        return $this->submit($data, $request, $action);
    }

    private function submit(SubmitFormBData $data, Request $request, SubmitFormBAction $action): RedirectResponse
    {
        $student = $request->user()?->student;

        if ($student === null) {
            abort(404);
        }

        $action->handle($data, $student);

        return redirect()->route('student.status')->with('success', __('form.success'));
    }

    /** @return array<string, mixed> */
    private function catalogs(): array
    {
        return [
            'programs' => Program::query()->orderBy('name')->get(['id', 'name']),
            'graduation_types' => GraduationType::query()->orderBy('name')->get(['id', 'name', 'requires_advisor']),
            'study_plans' => StudyPlan::query()->orderBy('name')->get(['id', 'name', 'program_id']),
            'professors' => Professor::query()
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'mother_last_name'])
                ->map(fn (Professor $professor): array => [
                    'id' => $professor->id,
                    'name' => trim("{$professor->first_name} {$professor->last_name} {$professor->mother_last_name}"),
                ]),
        ];
    }
}
