<?php

declare(strict_types=1);

use App\Domain\Academic\Models\RequiredDocument;
use App\Domain\Graduation\Models\StudentDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * RequiredDocuments catalog CRUD (spec 009 §3 scenarios 2-5, 8; CONTRACT §10.1).
 * This catalog is the asymmetric one: NO unique column (no code/email → no
 * unique-ignore-self path) and NO SoftDeletes → a HARD delete (assertDatabaseMissing,
 * not assertSoftDeleted). It is referenced by student_documents.required_document_id
 * (restrict); the hard delete is the riskiest 500 path, so the in-use guard
 * (pre-check + QueryException 23503 backstop) is exercised. PostgreSQL 18.
 */

it('creates a required document on the happy path', function (): void {
    actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.catalogs.required-documents.store'), [
            'name' => 'Acta de Nacimiento',
            'description' => 'Copia certificada reciente',
            'allowed_mimes' => 'application/pdf,image/jpeg',
            'max_size_kb' => 5120,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.created'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('required_documents', [
        'name' => 'Acta de Nacimiento',
        'allowed_mimes' => 'application/pdf,image/jpeg',
        'max_size_kb' => 5120,
    ]);
});

it('creates a required document without a description (nullable field)', function (): void {
    actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.catalogs.required-documents.store'), [
            'name' => 'Comprobante de Pago',
            'allowed_mimes' => 'application/pdf',
            'max_size_kb' => 2048,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('required_documents', [
        'name' => 'Comprobante de Pago',
        'description' => null,
    ]);
});

it('updates a required document', function (): void {
    $document = RequiredDocument::factory()->create(['name' => 'Antes', 'max_size_kb' => 1024]);

    actingAs(User::factory()->superAdmin()->create())
        ->put(route('admin.catalogs.required-documents.update', $document), [
            'name' => 'Después',
            'description' => 'Actualizado',
            'allowed_mimes' => 'application/pdf',
            'max_size_kb' => 8192,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.updated'))
        ->assertSessionHasNoErrors();

    expect($document->fresh())->name->toBe('Después')->max_size_kb->toBe(8192);
});

it('rejects invalid required document create input with 302 + session errors', function (array $payload, string $field): void {
    actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.catalogs.required-documents.store'), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors($field)
        ->assertStatus(302);
})->with([
    'missing name' => [['allowed_mimes' => 'application/pdf', 'max_size_kb' => 1024], 'name'],
    'missing allowed_mimes' => [['name' => 'Sin mimes', 'max_size_kb' => 1024], 'allowed_mimes'],
    'missing max_size_kb' => [['name' => 'Sin tamaño', 'allowed_mimes' => 'application/pdf'], 'max_size_kb'],
    'over-max name' => [['name' => str_repeat('N', 151), 'allowed_mimes' => 'application/pdf', 'max_size_kb' => 1024], 'name'],
    'zero max_size_kb' => [['name' => 'Cero', 'allowed_mimes' => 'application/pdf', 'max_size_kb' => 0], 'max_size_kb'],
    'over-max max_size_kb' => [['name' => 'Enorme', 'allowed_mimes' => 'application/pdf', 'max_size_kb' => 200000], 'max_size_kb'],
]);

it('hard-deletes a required document that no student document references', function (): void {
    $document = RequiredDocument::factory()->create();

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.required-documents.destroy', $document))
        ->assertRedirect()
        ->assertSessionHas('success', __('catalogs.deleted'));

    // No SoftDeletes — the row is physically gone.
    $this->assertDatabaseMissing('required_documents', ['id' => $document->id]);
});

it('refuses to delete a required document a student document references (302, in-use, row survives, NOT 500)', function (): void {
    $document = RequiredDocument::factory()->create();
    StudentDocument::factory()->create(['required_document_id' => $document->id]);

    actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.catalogs.required-documents.destroy', $document))
        ->assertRedirect()
        ->assertSessionHasErrors('catalog');

    // The riskiest hard-delete path is guarded: the row physically survives, no 500.
    $this->assertDatabaseHas('required_documents', ['id' => $document->id]);
});
