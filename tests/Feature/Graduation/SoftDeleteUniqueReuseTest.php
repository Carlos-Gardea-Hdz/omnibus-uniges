<?php

declare(strict_types=1);

use App\Domain\Academic\Models\Department;
use App\Domain\Academic\Models\Professor;
use App\Domain\Academic\Models\Program;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
 * WARN 4 — soft-delete + FULL unique trap. The unique indexes on soft-deletable
 * identity/business-key columns are now PARTIAL (WHERE deleted_at IS NULL), so a
 * value held by a SOFT-DELETED row can be reused by a fresh live row without a
 * 23505. A FULL unique index would have collided against the trashed row.
 */

it('lets a new student reuse a soft-deleted student control_number', function (): void {
    $original = Student::factory()->create(['control_number' => '20231234']);
    $original->delete();

    expect($original->fresh()->deleted_at)->not->toBeNull();

    // No QueryException (23505): the partial index ignores the trashed row.
    $reused = Student::factory()->create(['control_number' => '20231234']);

    expect($reused->control_number)->toBe('20231234')
        ->and($reused->id)->not->toBe($original->id);
});

it('lets a new student reuse a soft-deleted student user_id', function (): void {
    $user = User::factory()->student()->create();
    $original = Student::factory()->for($user)->create();
    $original->delete();

    // The freed user_id can back a new live student row.
    $reused = Student::factory()->for($user)->create();

    expect($reused->user_id)->toBe($user->id)
        ->and($reused->id)->not->toBe($original->id);
});

it('lets a new professor reuse a soft-deleted professor email', function (): void {
    $original = Professor::factory()->create(['email' => 'reuse@example.test']);
    $original->delete();

    $reused = Professor::factory()->create(['email' => 'reuse@example.test']);

    expect($reused->email)->toBe('reuse@example.test')
        ->and($reused->id)->not->toBe($original->id);
});

it('lets a new catalog row reuse a soft-deleted catalog code', function (): void {
    $original = Program::factory()->create(['code' => 'ISC']);
    $original->delete();

    $reused = Program::factory()->create(['code' => 'ISC']);

    expect($reused->code)->toBe('ISC')
        ->and($reused->id)->not->toBe($original->id);

    // Departments share the same trap/fix.
    $dept = Department::factory()->create(['code' => 'SYS']);
    $dept->delete();
    $deptReused = Department::factory()->create(['code' => 'SYS']);
    expect($deptReused->code)->toBe('SYS');
});

it('still rejects a duplicate control_number among LIVE students', function (): void {
    Student::factory()->create(['control_number' => '20231234']);

    // The partial index still enforces uniqueness across non-trashed rows. The
    // failing INSERT aborts its PG (sub)transaction, so scope it in a nested
    // transaction whose rollback un-poisons the connection for RefreshDatabase.
    expect(function (): void {
        DB::transaction(function (): void {
            Student::factory()->create(['control_number' => '20231234']);
        });
    })->toThrow(QueryException::class);
});
