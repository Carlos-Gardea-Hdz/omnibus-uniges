<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Domain\Reporting\Services\CohortsReportService;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only cohorts overview (SPEC §3.7 REPORT-03 / spec 008 §2.1): students
 * grouped by enrollment year, each cohort split by current GraduationStatus into a
 * fixed 9-state breakdown (+ total / graduated / in_progress) — produced by ONE
 * DemoScope-scoped `group by year, status` aggregate pivoted in PHP in the service.
 */
final class CohortsReportController extends Controller
{
    public function index(CohortsReportService $service): Response
    {
        return Inertia::render('Reporting/Cohorts', [
            'cohorts' => $service->overview()->all(),
        ]);
    }
}
