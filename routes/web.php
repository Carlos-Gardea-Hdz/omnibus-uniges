<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\DemoLoginController;
use App\Http\Controllers\Graduation\AdminDashboardController;
use App\Http\Controllers\Graduation\CeremonyController;
use App\Http\Controllers\Graduation\DocumentReviewController;
use App\Http\Controllers\Graduation\FormBReviewController;
use App\Http\Controllers\Graduation\JuryController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Reporting\CohortsReportController;
use App\Http\Controllers\Reporting\GraduatesReportController;
use App\Http\Controllers\Reporting\JudgeCertificatesReportController;
use App\Http\Controllers\Reporting\ReportingHubController;
use App\Http\Controllers\Reporting\TerminalEfficiencyReportController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\DocumentController;
use App\Http\Controllers\Student\DocumentDownloadController;
use App\Http\Controllers\Student\FormBController;
use App\Http\Controllers\Student\PaymentController;
use App\Http\Controllers\Student\StatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

/*
 * Session authentication (SPEC §3.1 AUTH-01 / AUTH-06, §7.1). The 'guest'
 * alias keeps an already-authenticated user off the login screen; 'auth'
 * gates logout. Brute-force throttling lives in AuthenticateUserAction, persisted
 * in login_attempts via the LoginThrottle service (SPEC §10.3).
 * Laravel ships the 'auth' and 'guest' middleware aliases by default — no
 * registration needed in bootstrap/app.php.
 */
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    // The demo chooser is a guest-facing landing screen.
    Route::get('/demo', [DemoLoginController::class, 'create'])->name('demo.create');
});

/*
 * Demo mode (SPEC §13): a visitor picks a preset and is provisioned a throwaway,
 * session-scoped sandbox. Provisioning is rate-limited by IP (10/hour via the
 * 'demo-login' limiter in AppServiceProvider) — a SEPARATE guard from the login
 * brute-force throttle. This POST is intentionally NOT in the 'guest' group: the
 * rate limiter (not the auth state) is the gate, so a recruiter can mint a fresh
 * sandbox even while a prior demo session is still active, up to the per-IP cap.
 */
Route::post('/demo-login', [DemoLoginController::class, 'store'])
    ->middleware('throttle:demo-login')
    ->name('demo.store');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
 * Student self-service (SPEC §7). A student owns their own Format B and may
 * watch their graduation status in real time. Gated by 'auth' (must be signed
 * in) then the 'role' alias — authorization never leaks into the domain layer.
 */
Route::middleware(['auth', 'demo', 'role:student'])->group(function (): void {
    Route::get('/student/dashboard', [DashboardController::class, 'index'])->name('student.dashboard');

    Route::get('/student/form-b', [FormBController::class, 'create'])->name('student.form-b.create');
    Route::post('/student/form-b', [FormBController::class, 'store'])->name('student.form-b.store');
    Route::put('/student/form-b', [FormBController::class, 'update'])->name('student.form-b.update');
    Route::get('/student/status', [StatusController::class, 'index'])->name('student.status');

    // Annex III document stage (SPEC §10).
    Route::get('/student/documents', [DocumentController::class, 'index'])->name('student.documents.index');
    Route::post('/student/documents/begin', [DocumentController::class, 'begin'])->name('student.documents.begin');
    Route::post('/student/documents/upload', [DocumentController::class, 'upload'])->name('student.documents.upload');

    // Payment stage that precedes jury assignment (SPEC §003).
    Route::get('/student/payment', [PaymentController::class, 'index'])->name('student.payment.index');
    Route::post('/student/payment', [PaymentController::class, 'submit'])->name('student.payment.submit');
});

/*
 * Document download (SPEC §10.4) — reached only via a temporary signed URL. The
 * controller authorizes the owning student OR any staff member, so it sits
 * outside the role groups: the signed link is the gate. The 'demo' middleware
 * (a no-op for real sessions) publishes the demo tag so a demo visitor can
 * resolve and download a document they uploaded inside their own sandbox.
 */
Route::get('/student/documents/{document}/download', [DocumentDownloadController::class, 'show'])
    ->middleware(['signed', 'demo'])
    ->name('student.documents.download');

/*
 * Staff review of submitted Format B (SPEC §7). Admins, super admins and
 * secretaries may approve or reject a student's submission. Gated by 'auth'
 * (must be signed in) then the 'role' alias.
 */
Route::middleware(['auth', 'demo', 'role:admin,super_admin,secretary'])->group(function (): void {
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

    Route::get('/admin/graduation/review', [FormBReviewController::class, 'index'])->name('admin.graduation.review');
    Route::post('/admin/graduation/{student}/approve', [FormBReviewController::class, 'approve'])->name('admin.graduation.approve');
    Route::post('/admin/graduation/{student}/reject', [FormBReviewController::class, 'reject'])->name('admin.graduation.reject');

    // Annex III document review queue (SPEC §10).
    Route::get('/admin/graduation/documents', [DocumentReviewController::class, 'index'])->name('admin.graduation.documents.index');
    Route::post('/admin/graduation/documents/{studentDocument}/approve', [DocumentReviewController::class, 'approve'])->name('admin.graduation.documents.approve');
    Route::post('/admin/graduation/documents/{studentDocument}/reject', [DocumentReviewController::class, 'reject'])->name('admin.graduation.documents.reject');

    // Payment verification + jury assignment, the 6→7 transition (SPEC §003).
    Route::get('/admin/graduation/jury', [JuryController::class, 'index'])->name('admin.graduation.jury.index');
    Route::post('/admin/graduation/jury/{student}/verify-payment', [JuryController::class, 'verifyPayment'])->name('admin.graduation.jury.verify-payment');
    Route::post('/admin/graduation/jury/{student}/assign', [JuryController::class, 'assign'])->name('admin.graduation.jury.assign');

    // Ceremony scheduling (7→8) + graduation completion (8→9), the terminal transitions (SPEC §004).
    Route::get('/admin/graduation/ceremony', [CeremonyController::class, 'index'])->name('admin.graduation.ceremony.index');
    Route::post('/admin/graduation/ceremony/{student}/schedule', [CeremonyController::class, 'schedule'])->name('admin.graduation.ceremony.schedule');
    Route::post('/admin/graduation/ceremony/{student}/graduate', [CeremonyController::class, 'graduate'])->name('admin.graduation.ceremony.graduate');

    /*
     * Read-only staff analytics (SPEC §3.7 / spec 008): a navigation hub plus the
     * four reports — graduates roster (REPORT-02), terminal efficiency (REPORT-01),
     * cohorts overview (REPORT-03) and judge certificates (CERT-01). Every report
     * is a single DemoScope-scoped aggregate (no N+1, no withoutGlobalScopes — a
     * demo coordinator's reports count ONLY their sandbox). GET-only: no Action, no
     * DTO, no mutation. Excel/PDF/Word export is DEFERRED to a later slice.
     */
    Route::prefix('admin/reports')->name('admin.reports.')->group(function (): void {
        Route::get('/', [ReportingHubController::class, 'index'])->name('index');
        Route::get('/graduates', [GraduatesReportController::class, 'index'])->name('graduates');
        Route::get('/terminal-efficiency', [TerminalEfficiencyReportController::class, 'index'])->name('terminal-efficiency');
        Route::get('/cohorts', [CohortsReportController::class, 'index'])->name('cohorts');
        Route::get('/judge-certificates', [JudgeCertificatesReportController::class, 'index'])->name('judge-certificates');
    });
});
