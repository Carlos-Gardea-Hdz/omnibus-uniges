<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\DocumentStatus;

covers(DocumentStatus::class);

/*
 * DocumentStatus is the per-document review lifecycle (CONTRACT §2). It backs
 * the student_documents.status column and drives the document badges. These
 * cases pin the four values, the bilingual i18n key and the semantic colour
 * token so the React side and the CHECK constraint never drift.
 */

it('exposes exactly the four lifecycle cases with their string values', function (): void {
    $values = array_map(
        static fn (DocumentStatus $status): string => $status->value,
        DocumentStatus::cases(),
    );

    expect($values)->toEqualCanonicalizing(['pending', 'uploaded', 'approved', 'rejected']);
});

it('builds an i18n key under the document_status namespace', function (
    DocumentStatus $status,
    string $expected,
): void {
    expect($status->labelKey())->toBe($expected);
})->with([
    'pending' => [DocumentStatus::Pending, 'document_status.pending'],
    'uploaded' => [DocumentStatus::Uploaded, 'document_status.uploaded'],
    'approved' => [DocumentStatus::Approved, 'document_status.approved'],
    'rejected' => [DocumentStatus::Rejected, 'document_status.rejected'],
]);

it('maps each status to its semantic colour token', function (
    DocumentStatus $status,
    string $color,
): void {
    expect($status->color())->toBe($color);
})->with([
    'rejected → danger' => [DocumentStatus::Rejected, 'danger'],
    'approved → success' => [DocumentStatus::Approved, 'success'],
    'uploaded → primary' => [DocumentStatus::Uploaded, 'primary'],
    'pending → warning' => [DocumentStatus::Pending, 'warning'],
]);
