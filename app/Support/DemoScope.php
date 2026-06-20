<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * The symmetric isolation scope for demo mode (slice 006).
 *
 * Applied to every ephemeral demo-tagged model (Student, StudentDocument,
 * JuryAssignment). It reads the active demo session tag from the per-request
 * DemoContext singleton (set by DemoSessionMiddleware) and constrains EVERY
 * query in both directions:
 *
 *   - No active demo tag (real user / CLI / scheduler) → WHERE demo_session_id IS NULL
 *     (real users never see demo rows).
 *   - Active demo tag                                  → WHERE demo_session_id = <tag>
 *     (a demo session sees ONLY its own sandbox — never real rows, never another
 *      demo session's rows).
 *
 * The qualified column (`<table>.demo_session_id`) keeps the predicate
 * unambiguous under joins. Cleanup and cross-session provisioning that must reach
 * untagged or other-tagged rows bypass this scope with
 * Model::withoutGlobalScope(DemoScope::class).
 *
 * Lives under App\Support (domain-neutral) so the Graduation/Jury models can use
 * it without importing App\Domain\Identity (which the arch tests forbid).
 */
final class DemoScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $column = $model->qualifyColumn('demo_session_id');
        $sessionId = app(DemoContext::class)->sessionId();

        if ($sessionId === null) {
            $builder->whereNull($column);

            return;
        }

        $builder->where($column, $sessionId);
    }
}
