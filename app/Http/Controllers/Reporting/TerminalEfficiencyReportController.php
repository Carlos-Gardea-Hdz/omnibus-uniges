<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Domain\Reporting\Services\TerminalEfficiencyService;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only terminal-efficiency report (SPEC §3.7 REPORT-01 / spec 008 §2.1):
 * graduates ÷ total as an integer percent (0-100, D-RATE-INT) per enrollment-year
 * cohort, per program, and overall — each computed by ONE DemoScope-scoped
 * `count(*) filter (where status = ?)` aggregate in the service (no N+1).
 */
final class TerminalEfficiencyReportController extends Controller
{
    public function index(TerminalEfficiencyService $service): Response
    {
        return Inertia::render('Reporting/TerminalEfficiency', [
            'by_cohort' => $service->byCohort()->all(),
            'by_program' => $service->byProgram()->all(),
            'overall' => $service->overall(),
        ]);
    }
}
