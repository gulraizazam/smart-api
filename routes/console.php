<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily rollup at 00:15 UTC rebuilds yesterday's metrics for every account ×
// branch × company-wide. Catch-up run at 06:00 UTC covers the prior 3 days
// (cheap insurance against missed events).
Schedule::command('management-dashboard:rebuild-metrics --days=1')
    ->dailyAt('00:15')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('management-dashboard:rebuild-metrics --days=3')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();
