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
 * Prop-contract tests for the six catalog INDEX pages (spec 009 §2.4, scenarios 13).
 * Each controller payload must match its .tsx interface EXACTLY (snake_case keys;
 * requires_advisor a bool; required_document_ids a number[]; FK option lists
 * present; eager-loaded department_name / program_name / full_name). A future
 * controller/page drift fails CI. Boots the app + PostgreSQL 18 (RefreshDatabase),
 * acting as super_admin.
 */

function catalogSuperAdmin(): User
{
    return User::factory()->superAdmin()->create();
}

it('locks the Departments page prop contract', function (): void {
    $department = Department::factory()->create(['code' => 'DEP-AAA', 'name' => 'Sistemas']);

    actingAs(catalogSuperAdmin())
        ->get(route('admin.catalogs.departments.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Admin/Catalogs/Departments')
                ->has('departments', 1)
                ->where('departments.0.id', $department->id)
                ->where('departments.0.code', 'DEP-AAA')
                ->where('departments.0.name', 'Sistemas'),
        );
});

it('locks the Programs page prop contract with the department_name and FK options', function (): void {
    $department = Department::factory()->create(['name' => 'Departamento Z']);
    $program = Program::factory()->create([
        'code' => 'PRGAAA',
        'name' => 'Ingeniería',
        'department_id' => $department->id,
    ]);

    actingAs(catalogSuperAdmin())
        ->get(route('admin.catalogs.programs.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Admin/Catalogs/Programs')
                ->has('programs', 1)
                ->where('programs.0.id', $program->id)
                ->where('programs.0.code', 'PRGAAA')
                ->where('programs.0.name', 'Ingeniería')
                ->where('programs.0.department_id', $department->id)
                ->where('programs.0.department_name', 'Departamento Z')
                ->has('department_options', 1)
                ->where('department_options.0.id', $department->id)
                ->where('department_options.0.name', 'Departamento Z'),
        );
});

it('locks the Professors page prop contract with full_name and nullable mother_last_name', function (): void {
    $withMother = Professor::factory()->create([
        'first_name' => 'Ana',
        'last_name' => 'Borja', // sorts first by last_name
        'mother_last_name' => 'Cruz',
        'email' => 'ana@uniges.test',
    ]);
    Professor::factory()->create([
        'first_name' => 'Luis',
        'last_name' => 'Zamora',
        'mother_last_name' => null,
        'email' => 'luis@uniges.test',
    ]);

    actingAs(catalogSuperAdmin())
        ->get(route('admin.catalogs.professors.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Admin/Catalogs/Professors')
                ->has('professors', 2)
                ->where('professors.0.id', $withMother->id)
                ->where('professors.0.first_name', 'Ana')
                ->where('professors.0.last_name', 'Borja')
                ->where('professors.0.mother_last_name', 'Cruz')
                ->where('professors.0.email', 'ana@uniges.test')
                ->where('professors.0.full_name', fn (string $name): bool => str_contains($name, 'Ana') && str_contains($name, 'Borja'))
                ->where('professors.1.mother_last_name', null),
        );
});

it('locks the GraduationTypes page prop contract with requires_advisor bool, the pivot ids and options', function (): void {
    $docs = RequiredDocument::factory()->count(2)->create();
    $type = GraduationType::factory()->create([
        'code' => 'GT-AAA',
        'name' => 'Tesis',
        'requires_advisor' => true,
    ]);
    $type->requiredDocuments()->sync($docs->pluck('id')->all());

    actingAs(catalogSuperAdmin())
        ->get(route('admin.catalogs.graduation-types.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Admin/Catalogs/GraduationTypes')
                ->has('graduation_types', 1)
                ->where('graduation_types.0.id', $type->id)
                ->where('graduation_types.0.code', 'GT-AAA')
                ->where('graduation_types.0.name', 'Tesis')
                ->where('graduation_types.0.requires_advisor', true)
                ->where('graduation_types.0.required_document_ids', fn ($ids): bool => collect($ids)->sort()->values()->all() === $docs->pluck('id')->sort()->values()->all())
                ->has('required_document_options', 2)
                ->where('required_document_options.0.id', fn ($id): bool => is_int($id))
                ->where('required_document_options.0.name', fn ($name): bool => is_string($name)),
        );
});

it('serialises requires_advisor as a real boolean (false stays false)', function (): void {
    GraduationType::factory()->create(['code' => 'GT-NOA', 'requires_advisor' => false]);

    actingAs(catalogSuperAdmin())
        ->get(route('admin.catalogs.graduation-types.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Admin/Catalogs/GraduationTypes')
                ->where('graduation_types.0.requires_advisor', false),
        );
});

it('locks the StudyPlans page prop contract with the program_name and FK options', function (): void {
    $program = Program::factory()->create(['name' => 'Programa Q']);
    $plan = StudyPlan::factory()->create([
        'code' => 'SP-AAA',
        'name' => 'Plan 2024',
        'program_id' => $program->id,
    ]);

    actingAs(catalogSuperAdmin())
        ->get(route('admin.catalogs.study-plans.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Admin/Catalogs/StudyPlans')
                ->has('study_plans', 1)
                ->where('study_plans.0.id', $plan->id)
                ->where('study_plans.0.code', 'SP-AAA')
                ->where('study_plans.0.name', 'Plan 2024')
                ->where('study_plans.0.program_id', $program->id)
                ->where('study_plans.0.program_name', 'Programa Q')
                ->has('program_options', 1)
                ->where('program_options.0.id', $program->id)
                ->where('program_options.0.name', 'Programa Q'),
        );
});

it('locks the RequiredDocuments page prop contract with a nullable description', function (): void {
    $document = RequiredDocument::factory()->create([
        'name' => 'Acta',
        'description' => null,
        'allowed_mimes' => 'application/pdf',
        'max_size_kb' => 4096,
    ]);

    actingAs(catalogSuperAdmin())
        ->get(route('admin.catalogs.required-documents.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Admin/Catalogs/RequiredDocuments')
                ->has('required_documents', 1)
                ->where('required_documents.0.id', $document->id)
                ->where('required_documents.0.name', 'Acta')
                ->where('required_documents.0.description', null)
                ->where('required_documents.0.allowed_mimes', 'application/pdf')
                ->where('required_documents.0.max_size_kb', 4096),
        );
});
