<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\RequiredDocument;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * GraduationTypes catalog CRUD + the graduation_type ↔ required_document pivot
 * (spec 009 §3 scenarios 2-6, 8; CONTRACT §10.1). Create/update sync the pivot
 * from required_document_ids (sync semantics — a shrunk list REMOVES dropped pivot
 * rows). requires_advisor serialises as a bool. A type a Student references cannot
 * be deleted (302 + in-use, row survives, never 500); the cascade pivot is never a
 * block. PostgreSQL 18 via RefreshDatabase.
 */

it('creates a graduation type and syncs its required documents', function (): void {
    $docs = RequiredDocument::factory()->count(2)->create();

    actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.catalogs.graduation-types.store'), [
            'code' => 'GT-TES',
            'name' => 'Tesis',
            'requires_advisor' => true,
            'required_document_ids' => $docs->pluck('id')->all(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.created'))
        ->assertSessionHasNoErrors();

    $type = GraduationType::query()->where('code', 'GT-TES')->firstOrFail();

    expect($type->requires_advisor)->toBeTrue()
        ->and($type->requiredDocuments()->pluck('required_documents.id')->sort()->values()->all())
        ->toBe($docs->pluck('id')->sort()->values()->all());
});

it('creates a graduation type with no required documents (empty pivot)', function (): void {
    actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.catalogs.graduation-types.store'), [
            'code' => 'GT-EMP',
            'name' => 'Promedio General',
            'requires_advisor' => false,
            'required_document_ids' => [],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $type = GraduationType::query()->where('code', 'GT-EMP')->firstOrFail();

    expect($type->requires_advisor)->toBeFalse()
        ->and($type->requiredDocuments()->count())->toBe(0);
});

it('updates a graduation type keeping its own code and re-syncs the pivot (shrink removes rows)', function (): void {
    $docs = RequiredDocument::factory()->count(3)->create();
    $type = GraduationType::factory()->create(['code' => 'GT-KEEP']);
    $type->requiredDocuments()->sync($docs->pluck('id')->all());

    // Update keeps the code; the pivot shrinks from 3 ids to the first 1.
    $kept = [$docs->first()->id];

    actingAs(User::factory()->superAdmin()->create())
        ->put(route('admin.catalogs.graduation-types.update', $type), [
            'code' => 'GT-KEEP',
            'name' => 'Renombrada',
            'requires_advisor' => true,
            'required_document_ids' => $kept,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.updated'))
        ->assertSessionHasNoErrors();

    expect($type->fresh()->name)->toBe('Renombrada')
        ->and($type->requiredDocuments()->pluck('required_documents.id')->all())->toBe($kept);
});

it('rejects an update that collides with another graduation type code', function (): void {
    GraduationType::factory()->create(['code' => 'GT-TAKEN']);
    $type = GraduationType::factory()->create(['code' => 'GT-MINE']);

    actingAs(User::factory()->superAdmin()->create())
        ->put(route('admin.catalogs.graduation-types.update', $type), [
            'code' => 'GT-TAKEN',
            'name' => 'Cualquiera',
            'requires_advisor' => false,
            'required_document_ids' => [],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('code');

    expect($type->fresh()->code)->toBe('GT-MINE');
});

it('rejects invalid graduation type create input with 302 + session errors', function (array $payload, string $field): void {
    GraduationType::factory()->create(['code' => 'GT-DUP']);

    actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.catalogs.graduation-types.store'), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors($field)
        ->assertStatus(302);
})->with([
    'missing name' => [['code' => 'GT-N1', 'requires_advisor' => false], 'name'],
    'missing code' => [['name' => 'Sin código', 'requires_advisor' => false], 'code'],
    'over-max code' => [['code' => str_repeat('A', 21), 'name' => 'L', 'requires_advisor' => false], 'code'],
    'duplicate code' => [['code' => 'GT-DUP', 'name' => 'Repetida', 'requires_advisor' => false], 'code'],
    'non-existent required document id' => [['code' => 'GT-N2', 'name' => 'Mal pivote', 'requires_advisor' => false, 'required_document_ids' => [999999]], 'required_document_ids.0'],
]);

it('soft-deletes a graduation type that no student references', function (): void {
    $type = GraduationType::factory()->withRequiredDocuments(2)->create();

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.graduation-types.destroy', $type))
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.deleted'));

    $this->assertSoftDeleted($type);
});

it('refuses to delete a graduation type a student references (302, in-use, row survives, NOT 500)', function (): void {
    $type = GraduationType::factory()->create();
    Student::factory()->create(['graduation_type_id' => $type->id]);

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.graduation-types.destroy', $type))
        ->assertRedirect()
        ->assertSessionHasErrors('catalog');

    expect(GraduationType::query()->whereKey($type->id)->exists())->toBeTrue();
    $this->assertNotSoftDeleted($type);
});
