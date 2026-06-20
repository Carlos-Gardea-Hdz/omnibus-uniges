<?php

declare(strict_types=1);

use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Single-query guarantee for the admin dashboard histogram (CONTRACT §4, §8.3).
 *
 * The status snapshot MUST be produced by exactly ONE grouped-count query
 * against `students` — never a per-state `->count()` loop (9 queries) or an N+1.
 * This is the load-bearing performance invariant of the slice (Ley 9), so it is
 * asserted falsifiably: we listen on the connection, keep only the queries that
 * touch the `students` table AND aggregate (`group by` + `count`), and require
 * the count to be exactly one. A regression to a per-status loop turns this red.
 *
 * Boots the app + PostgreSQL 18 (tests/Feature, RefreshDatabase).
 */

it('builds the status histogram with exactly one grouped-count query on students', function (): void {
    $admin = User::factory()->admin()->create();

    // A spread of stages so the histogram is non-trivial.
    Student::factory()->count(2)->formBReview()->create();
    Student::factory()->count(2)->documentsStage()->create();
    Student::factory()->count(1)->ceremonyScheduled()->create();

    /** @var list<string> $histogramQueries */
    $histogramQueries = [];

    DB::listen(function ($query) use (&$histogramQueries): void {
        $sql = strtolower($query->sql);

        // Only the aggregate over the students table is the histogram. We ignore
        // session/auth/setup queries and any single-row lookups (the acting user,
        // its student relation) — those are not the count snapshot under test.
        if (str_contains($sql, ' from "students"')
            && str_contains($sql, 'group by')
            && str_contains($sql, 'count(')) {
            $histogramQueries[] = $sql;
        }
    });

    actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk();

    expect($histogramQueries)->toHaveCount(1);
});

it('never issues a per-state count loop against students', function (): void {
    $admin = User::factory()->admin()->create();
    Student::factory()->count(3)->formBReview()->create();

    /** @var int $studentCountQueries */
    $studentCountQueries = 0;

    DB::listen(function ($query) use (&$studentCountQueries): void {
        $sql = strtolower($query->sql);
        if (str_contains($sql, ' from "students"') && str_contains($sql, 'count(')) {
            $studentCountQueries++;
        }
    });

    actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk();

    // A per-state loop would emit 9 count() queries; the single grouped histogram
    // emits exactly one count query against students.
    expect($studentCountQueries)->toBe(1);
});
