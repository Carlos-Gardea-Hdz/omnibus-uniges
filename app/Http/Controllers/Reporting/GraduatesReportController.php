<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Domain\Reporting\Services\GraduatesReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only graduates roster (SPEC §3.7 REPORT-02 / spec 008 §2.1): every
 * Graduated student (DemoScope-scoped) with their full record, server-side
 * filterable by graduation year + program.
 *
 * The year/program filters are plain GET query params on a read-only endpoint —
 * NOT validated input (no DTO, never 422). A garbage `?year=abc` coerces to 0 via
 * {@see Request::integer()} and simply matches nothing (200, empty result) — the
 * D-FILTER decision. The service owns the scoped aggregate + the catalog options,
 * so this controller touches only the service + route() + Inertia::render.
 */
final class GraduatesReportController extends Controller
{
    public function index(Request $request, GraduatesReportService $service): Response
    {
        $year = $request->filled('year') ? $request->integer('year') : null;
        $programId = $request->filled('program_id') ? $request->integer('program_id') : null;
        $rows = $service->graduates($year, $programId);

        return Inertia::render('Reporting/Graduates', [
            'graduates' => $rows->all(),
            'filters' => ['year' => $year, 'program_id' => $programId],
            'filter_options' => [
                'years' => $service->availableYears(),
                'programs' => $service->programOptions(),
            ],
            'total' => $rows->count(),
        ]);
    }
}
