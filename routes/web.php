<?php

declare(strict_types=1);

use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

/*
 * Auth, student, and admin route groups are defined per SPEC §7 as their
 * controllers land. Keep route closures out of this file (project law:
 * routes are named, never closures).
 */
