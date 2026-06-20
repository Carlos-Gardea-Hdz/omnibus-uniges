<?php

declare(strict_types=1);

use App\Domain\Academic\Models\Department;
use App\Domain\Academic\Models\Program;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Departments catalog CRUD (spec 009 §3 scenarios 2-5, 8; CONTRACT §10.1). Every
 * mutation runs end-to-end through the ['auth','demo','role:super_admin'] route
 * group on PostgreSQL 18 (RefreshDatabase, never SQLite). Web validation surfaces
 * as 302 + session errors (the Spatie-Data-via-method-signature convention) — NEVER
 * 422. Deleting a department a Program still references must fail GRACEFULLY:
 * 302 + the CatalogInUseException 'catalog' error, the row preserved, NEVER a 500.
 */

it('creates a department on the happy path', function (): void {
    actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.catalogs.departments.store'), [
            'code' => 'DEP-X1',
            'name' => 'Departamento de Ingeniería en Sistemas',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.created'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('departments', [
        'code' => 'DEP-X1',
        'name' => 'Departamento de Ingeniería en Sistemas',
    ]);
});

it('updates a department keeping its own code (unique ignores self)', function (): void {
    $department = Department::factory()->create(['code' => 'DEP-KEEP', 'name' => 'Antes']);

    actingAs(User::factory()->superAdmin()->create())
        ->put(route('admin.catalogs.departments.update', $department), [
            'code' => 'DEP-KEEP', // unchanged — the update must not trip its own unique row
            'name' => 'Después',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.updated'))
        ->assertSessionHasNoErrors();

    expect($department->fresh())
        ->code->toBe('DEP-KEEP')
        ->name->toBe('Después');
});

it('rejects an update that collides with another department code (302, not 500)', function (): void {
    Department::factory()->create(['code' => 'DEP-TAKEN']);
    $department = Department::factory()->create(['code' => 'DEP-MINE']);

    actingAs(User::factory()->superAdmin()->create())
        ->put(route('admin.catalogs.departments.update', $department), [
            'code' => 'DEP-TAKEN', // already owned by another row
            'name' => 'Cualquiera',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('code');

    expect($department->fresh()->code)->toBe('DEP-MINE'); // unchanged
});

it('rejects invalid create input with 302 + session errors and persists nothing', function (array $payload, string $field): void {
    Department::factory()->create(['code' => 'DEP-DUP']); // for the duplicate-code case

    actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.catalogs.departments.store'), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors($field)
        ->assertStatus(302);

    expect(Department::query()->where('code', $payload['code'] ?? '')->where('name', $payload['name'] ?? 'x')->exists())
        ->toBeFalse();
})->with([
    'missing name' => [['code' => 'DEP-N1'], 'name'],
    'missing code' => [['name' => 'Sin código'], 'code'],
    'over-max code' => [['code' => str_repeat('A', 21), 'name' => 'Larga'], 'code'],
    'over-max name' => [['code' => 'DEP-N2', 'name' => str_repeat('N', 151)], 'name'],
    'duplicate code' => [['code' => 'DEP-DUP', 'name' => 'Repetida'], 'code'],
]);

it('soft-deletes a department that nothing references', function (): void {
    $department = Department::factory()->create();

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.departments.destroy', $department))
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.deleted'))
        ->assertSessionHasNoErrors();

    $this->assertSoftDeleted($department);
});

it('refuses to delete a department a program references (302, in-use error, row survives, NOT 500)', function (): void {
    $department = Department::factory()->create();
    Program::factory()->create(['department_id' => $department->id]);

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.departments.destroy', $department))
        ->assertRedirect()
        ->assertSessionHasErrors('catalog');

    // The row is still present (not soft-deleted, never hard-deleted) and no 500 occurred.
    expect(Department::query()->whereKey($department->id)->exists())->toBeTrue();
    $this->assertNotSoftDeleted($department);
});

it('refuses to delete a department referenced through a program a student also uses', function (): void {
    // A deeper graph: department ← program ← student. The department pre-check only
    // looks one hop (programs), but this proves the restrict chain never 500s.
    $department = Department::factory()->create();
    $program = Program::factory()->create(['department_id' => $department->id]);
    Student::factory()->create(['program_id' => $program->id]);

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.departments.destroy', $department))
        ->assertRedirect()
        ->assertSessionHasErrors('catalog');

    expect(Department::query()->whereKey($department->id)->exists())->toBeTrue();
});
