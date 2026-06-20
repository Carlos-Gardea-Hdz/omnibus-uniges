<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\Models\StudentDocument;
use App\Domain\Jury\Models\JuryAssignment;
use App\Models\User;
use App\Support\DemoScope;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Purge expired demo-session data (SPEC §13.4).
 *
 * The cutoff is 30 minutes ago: any demo-tagged row older than that belongs to a
 * session that has timed out. Deletes run in one transaction in FK-safe order
 * (children before parents, respecting restrictOnDelete on jury_assignments and
 * users <- students), and EVERY delete carries the falsifiable guard
 * `whereNotNull('demo_session_id')` — the load-bearing invariant: this command can
 * never touch a real row or a baseline catalog row.
 *
 * The DemoScope is bypassed (cleanup runs from CLI with a null DemoContext, which
 * would otherwise scope to NULL-tagged rows only) and soft-deletes are force-
 * deleted so the rows are physically gone (no orphan FKs, no growth). Idempotent:
 * a second run with nothing expired deletes zero.
 */
final class DemoCleanupAction
{
    /**
     * @return int the total number of rows physically deleted
     */
    public function handle(?CarbonInterface $now = null): int
    {
        $cutoff = ($now ?? now())->copy()->subMinutes(30);

        return DB::transaction(function () use ($cutoff): int {
            $deleted = 0;

            $deleted += $this->rows(JuryAssignment::query()
                ->withoutGlobalScope(DemoScope::class)
                ->withTrashed()
                ->whereNotNull('demo_session_id')
                ->where('created_at', '<', $cutoff)
                ->forceDelete());

            $deleted += $this->rows(StudentDocument::query()
                ->withoutGlobalScope(DemoScope::class)
                ->withTrashed()
                ->whereNotNull('demo_session_id')
                ->where('created_at', '<', $cutoff)
                ->forceDelete());

            $deleted += $this->rows(Student::query()
                ->withoutGlobalScope(DemoScope::class)
                ->withTrashed()
                ->whereNotNull('demo_session_id')
                ->where('created_at', '<', $cutoff)
                ->forceDelete());

            // User has no DemoScope and no soft-delete: a plain delete.
            $deleted += $this->rows(User::query()
                ->whereNotNull('demo_session_id')
                ->where('created_at', '<', $cutoff)
                ->delete());

            return $deleted;
        });
    }

    /**
     * Purge a single demo session immediately, regardless of age (optional
     * companion to the scheduled sweep — e.g. an explicit "exit demo" that wipes
     * the sandbox now). Same FK-safe, tag-scoped, scope-bypassing deletes. NOT
     * wired by default; the 15-minute sweep is the primary mechanism.
     *
     * @return int the total number of rows physically deleted for the session
     */
    public function purgeSession(string $demoSessionId): int
    {
        return DB::transaction(function () use ($demoSessionId): int {
            $deleted = 0;

            $deleted += $this->rows(JuryAssignment::query()
                ->withoutGlobalScope(DemoScope::class)
                ->withTrashed()
                ->where('demo_session_id', $demoSessionId)
                ->forceDelete());

            $deleted += $this->rows(StudentDocument::query()
                ->withoutGlobalScope(DemoScope::class)
                ->withTrashed()
                ->where('demo_session_id', $demoSessionId)
                ->forceDelete());

            $deleted += $this->rows(Student::query()
                ->withoutGlobalScope(DemoScope::class)
                ->withTrashed()
                ->where('demo_session_id', $demoSessionId)
                ->forceDelete());

            $deleted += $this->rows(User::query()
                ->where('demo_session_id', $demoSessionId)
                ->delete());

            return $deleted;
        });
    }

    /**
     * Coerce an Eloquent delete/forceDelete result (typed `mixed` by the query
     * builder, an affected-row int at runtime) to a non-negative int row count.
     */
    private function rows(mixed $result): int
    {
        return is_numeric($result) ? (int) $result : 0;
    }
}
