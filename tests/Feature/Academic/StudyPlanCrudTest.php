<?php

declare(strict_types=1);

use App\Domain\Academic\Models\Program;
use App\Domain\Academic\Models\StudyPlan;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * StudyPlans catalog CRUD (spec 009 §3 scenarios 2-5, 7, 8; CONTRACT §10.1). A
 * study plan carries a program_id FK (Exists) and is referenced by
 * students.study_plan_id (restrict). Deleting a plan a student references fails
 * gracefully (302 + in-use, row survives, never 500). PostgreSQL 18.
 */

it('creates a study plan on the happy path with a valid program FK', function (): void {
    $program = Program::factory()->create();

    actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.catalogs.study-plans.store'), [
            'code' => 'SP-2024',
            'name' => 'Plan de Estudios 2024',
            'program_id' => $program->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.created'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('study_plans', [
        'code' => 'SP-2024',
        'program_id' => $program->id,
    ]);
});

it('updates a study plan keeping its own code (unique ignores self)', function (): void {
    $plan = StudyPlan::factory()->create(['code' => 'SP-KEEP', 'name' => 'Antes']);

    actingAs(User::factory()->superAdmin()->create())
        ->put(route('admin.catalogs.study-plans.update', $plan), [
            'code' => 'SP-KEEP',
            'name' => 'Después',
            'program_id' => $plan->program_id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.updated'))
        ->assertSessionHasNoErrors();

    expect($plan->fresh())->code->toBe('SP-KEEP')->name->toBe('Después');
});

it('rejects an update that collides with another study plan code', function (): void {
    StudyPlan::factory()->create(['code' => 'SP-TAKEN']);
    $plan = StudyPlan::factory()->create(['code' => 'SP-MINE']);

    actingAs(User::factory()->superAdmin()->create())
        ->put(route('admin.catalogs.study-plans.update', $plan), [
            'code' => 'SP-TAKEN',
            'name' => 'Cualquiera',
            'program_id' => $plan->program_id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('code');

    expect($plan->fresh()->code)->toBe('SP-MINE');
});

it('rejects invalid study plan create input with 302 + session errors', function (callable $payload, string $field): void {
    $program = Program::factory()->create();
    StudyPlan::factory()->create(['code' => 'SP-DUP']);

    actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.catalogs.study-plans.store'), $payload($program->id))
        ->assertRedirect()
        ->assertSessionHasErrors($field)
        ->assertStatus(302);
})->with([
    'missing name' => [fn (int $prog) => ['code' => 'SP-N1', 'program_id' => $prog], 'name'],
    'missing code' => [fn (int $prog) => ['name' => 'Sin código', 'program_id' => $prog], 'code'],
    'over-max code' => [fn (int $prog) => ['code' => str_repeat('A', 21), 'name' => 'L', 'program_id' => $prog], 'code'],
    'non-existent program FK' => [fn (int $prog) => ['code' => 'SP-N3', 'name' => 'Huérfano', 'program_id' => 999999], 'program_id'],
    'missing program FK' => [fn (int $prog) => ['code' => 'SP-N4', 'name' => 'Sin programa'], 'program_id'],
    'duplicate code' => [fn (int $prog) => ['code' => 'SP-DUP', 'name' => 'Repetida', 'program_id' => $prog], 'code'],
]);

it('soft-deletes a study plan that no student references', function (): void {
    $plan = StudyPlan::factory()->create();

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.study-plans.destroy', $plan))
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.deleted'));

    $this->assertSoftDeleted($plan);
});

it('refuses to delete a study plan a student references (302, in-use, row survives, NOT 500)', function (): void {
    $plan = StudyPlan::factory()->create();
    Student::factory()->create(['study_plan_id' => $plan->id]);

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.study-plans.destroy', $plan))
        ->assertRedirect()
        ->assertSessionHasErrors('catalog');

    expect(StudyPlan::query()->whereKey($plan->id)->exists())->toBeTrue();
    $this->assertNotSoftDeleted($plan);
});
