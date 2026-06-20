<?php

declare(strict_types=1);

use App\Domain\Academic\Models\Professor;
use App\Domain\Graduation\Models\Student;
use App\Domain\Jury\Models\JuryAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Professors catalog CRUD (spec 009 §3 scenarios 2-5, 8; CONTRACT §10.1). The
 * unique column is `email` (not a code). Professors are referenced as jury seats
 * (president/secretary/vocal — restrict) and as student advisor / jury substitute
 * (nullOnDelete — NOT a block). So: deleting a professor SEATED on a jury fails
 * gracefully (302 + in-use, row survives, never 500); deleting one used ONLY as an
 * advisor/substitute SUCCEEDS and nulls those columns. PostgreSQL 18.
 */

it('creates a professor on the happy path', function (): void {
    actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.catalogs.professors.store'), [
            'first_name' => 'Laura',
            'last_name' => 'Domínguez',
            'mother_last_name' => 'Reyes',
            'email' => 'laura.dominguez@uniges.test',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.created'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('professors', [
        'email' => 'laura.dominguez@uniges.test',
        'first_name' => 'Laura',
    ]);
});

it('creates a professor without a mother last name (nullable field)', function (): void {
    actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.catalogs.professors.store'), [
            'first_name' => 'Marco',
            'last_name' => 'Téllez',
            'email' => 'marco.tellez@uniges.test',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('professors', [
        'email' => 'marco.tellez@uniges.test',
        'mother_last_name' => null,
    ]);
});

it('updates a professor keeping its own email (unique ignores self)', function (): void {
    $professor = Professor::factory()->create(['email' => 'keep@uniges.test', 'first_name' => 'Antes']);

    actingAs(User::factory()->superAdmin()->create())
        ->put(route('admin.catalogs.professors.update', $professor), [
            'first_name' => 'Después',
            'last_name' => $professor->last_name,
            'mother_last_name' => $professor->mother_last_name,
            'email' => 'keep@uniges.test',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.updated'))
        ->assertSessionHasNoErrors();

    expect($professor->fresh())->first_name->toBe('Después')->email->toBe('keep@uniges.test');
});

it('rejects an update that collides with another professor email', function (): void {
    Professor::factory()->create(['email' => 'taken@uniges.test']);
    $professor = Professor::factory()->create(['email' => 'mine@uniges.test']);

    actingAs(User::factory()->superAdmin()->create())
        ->put(route('admin.catalogs.professors.update', $professor), [
            'first_name' => $professor->first_name,
            'last_name' => $professor->last_name,
            'mother_last_name' => $professor->mother_last_name,
            'email' => 'taken@uniges.test',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    expect($professor->fresh()->email)->toBe('mine@uniges.test');
});

it('rejects invalid professor create input with 302 + session errors', function (array $payload, string $field): void {
    Professor::factory()->create(['email' => 'dup@uniges.test']);

    actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.catalogs.professors.store'), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors($field)
        ->assertStatus(302);
})->with([
    'missing first_name' => [['last_name' => 'Solo', 'email' => 'a@uniges.test'], 'first_name'],
    'missing last_name' => [['first_name' => 'Solo', 'email' => 'b@uniges.test'], 'last_name'],
    'missing email' => [['first_name' => 'Sin', 'last_name' => 'Correo'], 'email'],
    'malformed email' => [['first_name' => 'Mal', 'last_name' => 'Correo', 'email' => 'not-an-email'], 'email'],
    'over-max first_name' => [['first_name' => str_repeat('N', 101), 'last_name' => 'L', 'email' => 'c@uniges.test'], 'first_name'],
    'duplicate email' => [['first_name' => 'Rep', 'last_name' => 'Etida', 'email' => 'dup@uniges.test'], 'email'],
]);

it('soft-deletes a professor that no jury seats', function (): void {
    $professor = Professor::factory()->create();

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.professors.destroy', $professor))
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.deleted'));

    $this->assertSoftDeleted($professor);
});

it('refuses to delete a professor seated on a jury (302, in-use, row survives, NOT 500)', function (): void {
    $professor = Professor::factory()->create();
    // Seat this professor as the president of a jury (a restrict FK).
    JuryAssignment::factory()->create(['president_professor_id' => $professor->id]);

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.professors.destroy', $professor))
        ->assertRedirect()
        ->assertSessionHasErrors('catalog');

    expect(Professor::query()->whereKey($professor->id)->exists())->toBeTrue();
    $this->assertNotSoftDeleted($professor);
});

it('deletes a professor used only as a student advisor and nulls the advisor_id (nullOnDelete)', function (): void {
    $professor = Professor::factory()->create();
    $student = Student::factory()->create(['advisor_id' => $professor->id]);

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.professors.destroy', $professor))
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.deleted'));

    $this->assertSoftDeleted($professor);
    // advisor_id is nullOnDelete — but the soft delete is an UPDATE, so the FK does
    // not fire; the pre-check correctly EXCLUDES advisor references, so the delete
    // succeeds. The student keeps the advisor_id pointing at the soft-deleted row.
    expect($student->fresh()->advisor_id)->toBe($professor->id);
});

it('deletes a professor used only as a jury substitute (substitute excluded from the block)', function (): void {
    $professor = Professor::factory()->create();
    // Seat this professor ONLY as the optional substitute (nullOnDelete → not a block).
    JuryAssignment::factory()->create(['substitute_professor_id' => $professor->id]);

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.professors.destroy', $professor))
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.deleted'));

    $this->assertSoftDeleted($professor);
});
