<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Catalog;

use App\Domain\Academic\Models\Department;
use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\Professor;
use App\Domain\Academic\Models\Program;
use App\Domain\Academic\Models\RequiredDocument;
use App\Domain\Academic\Models\StudyPlan;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\FormBController;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The academic-catalog hub (spec 009 §2): a navigation index over the six
 * catalogs. Each card carries a live `count` and a resolved {@see route()} URL so
 * the page never hardcodes a path nor renders a dead-end link; the i18n
 * title/description keys resolve client-side via useLocale (no PHP __()).
 *
 * The six counts are plain read-only catalog queries — the same allowance as
 * {@see FormBController::catalogs()} — and run only
 * as super_admin with no demo context, so the catalogs (which are NOT
 * DemoScope-scoped) report the full shared baseline. No write lives here.
 */
final class CatalogHubController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Catalogs/Index', [
            'catalogs' => [
                ['key' => 'departments', 'title_key' => 'catalogs.departments.title', 'description_key' => 'catalogs.departments.description', 'count' => Department::count(), 'route' => route('admin.catalogs.departments.index')],
                ['key' => 'programs', 'title_key' => 'catalogs.programs.title', 'description_key' => 'catalogs.programs.description', 'count' => Program::count(), 'route' => route('admin.catalogs.programs.index')],
                ['key' => 'professors', 'title_key' => 'catalogs.professors.title', 'description_key' => 'catalogs.professors.description', 'count' => Professor::count(), 'route' => route('admin.catalogs.professors.index')],
                ['key' => 'graduation_types', 'title_key' => 'catalogs.graduation_types.title', 'description_key' => 'catalogs.graduation_types.description', 'count' => GraduationType::count(), 'route' => route('admin.catalogs.graduation-types.index')],
                ['key' => 'study_plans', 'title_key' => 'catalogs.study_plans.title', 'description_key' => 'catalogs.study_plans.description', 'count' => StudyPlan::count(), 'route' => route('admin.catalogs.study-plans.index')],
                ['key' => 'required_documents', 'title_key' => 'catalogs.required_documents.title', 'description_key' => 'catalogs.required_documents.description', 'count' => RequiredDocument::count(), 'route' => route('admin.catalogs.required-documents.index')],
            ],
        ]);
    }
}
