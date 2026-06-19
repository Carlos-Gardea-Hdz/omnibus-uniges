<?php

declare(strict_types=1);

use App\Http\Controllers\Graduation\FormBReviewController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Student\FormBController;
use App\Http\Controllers\Student\StatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

/*
 * Student self-service (SPEC §7). A student owns their own Format B and may
 * watch their graduation status in real time. Gated by the 'role' alias —
 * authorization never leaks into the domain layer.
 */
Route::middleware('role:student')->group(function (): void {
    Route::get('/student/form-b', [FormBController::class, 'create'])->name('student.form-b.create');
    Route::post('/student/form-b', [FormBController::class, 'store'])->name('student.form-b.store');
    Route::put('/student/form-b', [FormBController::class, 'update'])->name('student.form-b.update');
    Route::get('/student/status', [StatusController::class, 'index'])->name('student.status');
});

/*
 * Staff review of submitted Format B (SPEC §7). Admins, super admins and
 * secretaries may approve or reject a student's submission.
 */
Route::middleware('role:admin,super_admin,secretary')->group(function (): void {
    Route::get('/admin/graduation/review', [FormBReviewController::class, 'index'])->name('admin.graduation.review');
    Route::post('/admin/graduation/{student}/approve', [FormBReviewController::class, 'approve'])->name('admin.graduation.approve');
    Route::post('/admin/graduation/{student}/reject', [FormBReviewController::class, 'reject'])->name('admin.graduation.reject');
});
