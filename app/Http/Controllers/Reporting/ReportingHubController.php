<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reporting hub (SPEC §3.7 / spec 008 §2.1): a query-free navigation index
 * over the four staff reports. Each card carries a resolved {@see route()} URL so
 * the page never hardcodes a path and never renders a dead-end link; the i18n
 * title/description keys resolve client-side via useLocale (no PHP __()).
 *
 * No data query lives here — every report owns its own scoped aggregate.
 */
final class ReportingHubController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Reporting/Index', [
            'reports' => [
                ['key' => 'graduates', 'title_key' => 'reports.graduates.title', 'description_key' => 'reports.graduates.description', 'route' => route('admin.reports.graduates')],
                ['key' => 'terminal_efficiency', 'title_key' => 'reports.terminal_efficiency.title', 'description_key' => 'reports.terminal_efficiency.description', 'route' => route('admin.reports.terminal-efficiency')],
                ['key' => 'cohorts', 'title_key' => 'reports.cohorts.title', 'description_key' => 'reports.cohorts.description', 'route' => route('admin.reports.cohorts')],
                ['key' => 'judge_certificates', 'title_key' => 'reports.judge_certificates.title', 'description_key' => 'reports.judge_certificates.description', 'route' => route('admin.reports.judge-certificates')],
            ],
        ]);
    }
}
