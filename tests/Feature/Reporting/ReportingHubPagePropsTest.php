<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Prop-contract test for the reporting hub (spec 008 §2.4 Reporting/Index,
 * scenario 1). The hub is query-free: it renders EXACTLY 4 report cards in a
 * fixed order, each with key / title_key / description_key / a RESOLVED route()
 * URL (never a dead-end link). This locks the snake_case contract so a future
 * controller/page drift fails CI. Boots the app + PostgreSQL 18 (RefreshDatabase).
 */

it('renders exactly 4 report cards with the fixed keys and resolved routes', function (): void {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Index')
                ->has('reports', 4)
                // Graduates
                ->where('reports.0.key', 'graduates')
                ->where('reports.0.title_key', 'reports.graduates.title')
                ->where('reports.0.description_key', 'reports.graduates.description')
                ->where('reports.0.route', route('admin.reports.graduates'))
                // Terminal efficiency
                ->where('reports.1.key', 'terminal_efficiency')
                ->where('reports.1.title_key', 'reports.terminal_efficiency.title')
                ->where('reports.1.description_key', 'reports.terminal_efficiency.description')
                ->where('reports.1.route', route('admin.reports.terminal-efficiency'))
                // Cohorts
                ->where('reports.2.key', 'cohorts')
                ->where('reports.2.title_key', 'reports.cohorts.title')
                ->where('reports.2.description_key', 'reports.cohorts.description')
                ->where('reports.2.route', route('admin.reports.cohorts'))
                // Judge certificates
                ->where('reports.3.key', 'judge_certificates')
                ->where('reports.3.title_key', 'reports.judge_certificates.title')
                ->where('reports.3.description_key', 'reports.judge_certificates.description')
                ->where('reports.3.route', route('admin.reports.judge-certificates')),
        );
});

it('resolves every hub card route to a real registered report route (no dead-end)', function (): void {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Index')
                ->where('reports', fn ($reports) => collect($reports)
                    ->pluck('route')->all() === [
                        route('admin.reports.graduates'),
                        route('admin.reports.terminal-efficiency'),
                        route('admin.reports.cohorts'),
                        route('admin.reports.judge-certificates'),
                    ]),
        );
});
