<?php

declare(strict_types=1);

use App\Domain\Academic\Models\Department;
use App\Domain\Academic\Models\Program;
use App\Domain\Academic\Models\StudyPlan;
use App\Domain\Ceremony\Models\DiplomaFolioSequence;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Programs catalog CRUD (spec 009 §3 scenarios 2-5, 7, 8; CONTRACT §10.1). Program
 * carries a department_id FK (Exists rule) and is referenced by BOTH
 * students.program_id AND study_plans.program_id (two restrict FKs). Either live
 * reference must make a delete fail gracefully (302 + in-use error, row preserved,
 * never a 500). PostgreSQL 18 via RefreshDatabase; web validation = 302 + errors.
 */

it('creates a program on the happy path with a valid department FK', function (): void {
    $department = Department::factory()->create();

    actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.catalogs.programs.store'), [
            'code' => 'PRGX1',
            'name' => 'Ingeniería en Sistemas Computacionales',
            'department_id' => $department->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.created'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('programs', [
        'code' => 'PRGX1',
        'department_id' => $department->id,
    ]);
});

it('updates a program keeping its own code (unique ignores self)', function (): void {
    $program = Program::factory()->create(['code' => 'PRGKEEP', 'name' => 'Antes']);

    actingAs(User::factory()->superAdmin()->create())
        ->put(route('admin.catalogs.programs.update', $program), [
            'code' => 'PRGKEEP',
            'name' => 'Después',
            'department_id' => $program->department_id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.updated'))
        ->assertSessionHasNoErrors();

    expect($program->fresh())->code->toBe('PRGKEEP')->name->toBe('Después');
});

it('rejects an update that collides with another program code', function (): void {
    Program::factory()->create(['code' => 'PRGTAKEN']);
    $program = Program::factory()->create(['code' => 'PRGMINE']);

    actingAs(User::factory()->superAdmin()->create())
        ->put(route('admin.catalogs.programs.update', $program), [
            'code' => 'PRGTAKEN',
            'name' => 'Cualquiera',
            'department_id' => $program->department_id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('code');

    expect($program->fresh()->code)->toBe('PRGMINE');
});

it('rejects invalid program create input with 302 + session errors', function (callable $payload, string $field): void {
    $department = Department::factory()->create();
    Program::factory()->create(['code' => 'PRGDUP']);

    actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.catalogs.programs.store'), $payload($department->id))
        ->assertRedirect()
        ->assertSessionHasErrors($field)
        ->assertStatus(302);
})->with([
    'missing name' => [fn (int $dep) => ['code' => 'PRGN1', 'department_id' => $dep], 'name'],
    'missing code' => [fn (int $dep) => ['name' => 'Sin código', 'department_id' => $dep], 'code'],
    'over-max code' => [fn (int $dep) => ['code' => str_repeat('A', 21), 'name' => 'L', 'department_id' => $dep], 'code'],
    'non-existent department FK' => [fn (int $dep) => ['code' => 'PRGN3', 'name' => 'Huérfano', 'department_id' => 999999], 'department_id'],
    'missing department FK' => [fn (int $dep) => ['code' => 'PRGN4', 'name' => 'Sin depto'], 'department_id'],
    'duplicate code' => [fn (int $dep) => ['code' => 'PRGDUP', 'name' => 'Repetida', 'department_id' => $dep], 'code'],
]);

it('soft-deletes a program that nothing references', function (): void {
    $program = Program::factory()->create();

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.programs.destroy', $program))
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.deleted'));

    $this->assertSoftDeleted($program);
});

it('refuses to delete a program a student references (302, in-use, row survives, NOT 500)', function (): void {
    $program = Program::factory()->create();
    Student::factory()->create(['program_id' => $program->id]);

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.programs.destroy', $program))
        ->assertRedirect()
        ->assertSessionHasErrors('catalog');

    expect(Program::query()->whereKey($program->id)->exists())->toBeTrue();
    $this->assertNotSoftDeleted($program);
});

it('refuses to delete a program a study plan references (the second restrict FK)', function (): void {
    $program = Program::factory()->create();
    StudyPlan::factory()->create(['program_id' => $program->id]);

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.programs.destroy', $program))
        ->assertRedirect()
        ->assertSessionHasErrors('catalog');

    expect(Program::query()->whereKey($program->id)->exists())->toBeTrue();
    $this->assertNotSoftDeleted($program);
});

it('refuses to delete a program a diploma folio sequence references (the third restrict FK)', function (): void {
    // Soft-delete would NOT trip the restrict FK (it is an UPDATE), so without the
    // isReferenced() check this would silently leave a dangling folio-counter
    // reference behind a soft-deleted program — the slice-009 review WARN.
    $program = Program::factory()->create();
    DiplomaFolioSequence::factory()->create(['program_id' => $program->id]);

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.programs.destroy', $program))
        ->assertRedirect()
        ->assertSessionHasErrors('catalog');

    expect(Program::query()->whereKey($program->id)->exists())->toBeTrue();
    $this->assertNotSoftDeleted($program);
});
