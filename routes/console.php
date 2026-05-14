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

// Cash-flow attachment hygiene — sweep upload rows that never got bound
// to an expense (form abandoned). Runs daily at 02:00 server time;
// dedup-aware, so a shared R2 blob isn't deleted while another row still
// references it.
Schedule::command('cashflow:prune-orphan-attachments')->dailyAt('02:00');

