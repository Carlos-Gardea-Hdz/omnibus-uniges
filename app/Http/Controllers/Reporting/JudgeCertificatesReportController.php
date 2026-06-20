<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Domain\Reporting\Services\JudgeCertificatesService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only judge-certificates report (SPEC §3.7 CERT-01 / spec 008 §2.1): per
 * professor, the jury assignments they sat — the exact data behind a future
 * printable certificate. The export/download is DEFERRED (D-EXPORT-DEFERRED): this
 * screen shows the data only, no working export button.
 *
 * The optional `professor_id` filter is a plain GET query param on a read-only
 * endpoint (no DTO, never 422 — D-FILTER). The service runs ONE DemoScope-scoped
 * JuryAssignment query, inverts its four role columns into per-professor rows in
 * PHP (D-INVERT) and derives the professor options from the same result — so this
 * controller touches only the service + Inertia::render.
 */
final class JudgeCertificatesReportController extends Controller
{
    public function index(Request $request, JudgeCertificatesService $service): Response
    {
        $professorId = $request->filled('professor_id') ? $request->integer('professor_id') : null;

        return Inertia::render('Reporting/JudgeCertificates', [
            'professors' => $service->byProfessor($professorId)->all(),
            'filters' => ['professor_id' => $professorId],
            'filter_options' => ['professors' => $service->professorOptions()],
        ]);
    }
}
