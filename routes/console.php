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

// Keep the at-risk pool warm so the dashboard, the patient datatable, and
// the patient detail header all hit the cache instead of materialising on
// demand (~15-25s for accounts with many branches). Pool TTL is 5 minutes
// per (scope, branch); we run every 4 minutes so a refresh always lands
// before expiry. `withoutOverlapping` avoids piling up if a refresh ever
// runs long. Without this, latency-sensitive callers (PatientLifecycleMetric)
// gracefully degrade by skipping at-risk on cache miss — but the badge
// would silently disappear after 5 min idle, which the schedule prevents.
Schedule::command('mgmt-dash:warm-at-risk')
    ->everyFourMinutes()
    ->withoutOverlapping()
    ->runInBackground();
