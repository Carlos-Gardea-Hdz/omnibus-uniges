<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Demo-mode janitor (SPEC §13): force-delete demo-session rows past their
 * 30-minute TTL. Runs every 15 minutes; withoutOverlapping() prevents a long
 * sweep from stacking with the next tick.
 */
Schedule::command('demo:cleanup')->everyFifteenMinutes()->withoutOverlapping();
