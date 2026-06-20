<?php

declare(strict_types=1);

use App\Domain\Academic\Models\Department;
use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\Professor;
use App\Domain\Academic\Models\Program;
use App\Domain\Academic\Models\RequiredDocument;
use App\Domain\Academic\Models\StudyPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Prop-contract test for the catalog hub (spec 009 §2.4 Admin/Catalogs/Index,
 * scenario 1). The hub renders EXACTLY 6 cards in a fixed order, each with
 * key / title_key / description_key / a live count / a RESOLVED route() URL (never
 * a dead-end link). Mirrors ReportingHubPagePropsTest. Boots the app + PostgreSQL
 * 18 (RefreshDatabase).
 */

it('renders exactly 6 catalog cards with the fixed keys, counts and resolved routes', function (): void {
    // Tie the catalog chain so the factory's auto-created parents do NOT inflate the
    // counts: a bare Program::factory() mints a Department, and a bare
    // StudyPlan::factory() mints a Program (→ a Department). Reusing fixed parents via
    // ->for()/->recycle() keeps every Model::count() exact and equal to the cards.
    $department = Department::factory()->create();
    $program = Program::factory()->for($department)->create();

    // departments total = 2 (the fixed one above + one more), with the program
    // recycling the fixed department so no third department is created.
    Department::factory()->count(1)->create();
    // programs total = 3 ($program above + 2 more, all reusing $department).
    Program::factory()->count(2)->for($department)->create();
    Professor::factory()->count(4)->create();
    GraduationType::factory()->count(1)->create();
    // study_plans total = 5, all reusing $program so no extra program/department.
    StudyPlan::factory()->count(5)->for($program)->create();
    RequiredDocument::factory()->count(6)->create();

    actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.catalogs.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Admin/Catalogs/Index')
                ->has('catalogs', 6)
                // Departments
                ->where('catalogs.0.key', 'departments')
                ->where('catalogs.0.title_key', 'catalogs.departments.title')
                ->where('catalogs.0.description_key', 'catalogs.departments.description')
                ->where('catalogs.0.count', 2)
                ->where('catalogs.0.route', route('admin.catalogs.departments.index'))
                // Programs
                ->where('catalogs.1.key', 'programs')
                ->where('catalogs.1.count', 3)
                ->where('catalogs.1.route', route('admin.catalogs.programs.index'))
                // Professors
                ->where('catalogs.2.key', 'professors')
                ->where('catalogs.2.count', 4)
                ->where('catalogs.2.route', route('admin.catalogs.professors.index'))
                // Graduation types
                ->where('catalogs.3.key', 'graduation_types')
                ->where('catalogs.3.title_key', 'catalogs.graduation_types.title')
                ->where('catalogs.3.count', 1)
                ->where('catalogs.3.route', route('admin.catalogs.graduation-types.index'))
                // Study plans
                ->where('catalogs.4.key', 'study_plans')
                ->where('catalogs.4.count', 5)
                ->where('catalogs.4.route', route('admin.catalogs.study-plans.index'))
                // Required documents
                ->where('catalogs.5.key', 'required_documents')
                ->where('catalogs.5.count', 6)
                ->where('catalogs.5.route', route('admin.catalogs.required-documents.index')),
        );
});

it('resolves every hub card route to a real registered catalog index route (no dead-end)', function (): void {
    actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.catalogs.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Admin/Catalogs/Index')
                ->where('catalogs', fn ($catalogs) => collect($catalogs)
                    ->pluck('route')->all() === [
                        route('admin.catalogs.departments.index'),
                        route('admin.catalogs.programs.index'),
                        route('admin.catalogs.professors.index'),
                        route('admin.catalogs.graduation-types.index'),
                        route('admin.catalogs.study-plans.index'),
                        route('admin.catalogs.required-documents.index'),
                    ]),
        );
});

it('reports a zero count for an empty catalog (no rows seeded)', function (): void {
    actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.catalogs.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Admin/Catalogs/Index')
                ->where('catalogs.0.count', 0)
                ->where('catalogs.5.count', 0),
        );
});
