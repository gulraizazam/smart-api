<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware: trust proxies with forwarded headers
        $middleware->trustProxies(headers:
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );

        // Preserve password fields from trimming
        $middleware->trimStrings(except: [
            'current_password',
            'password',
            'password_confirmation',
        ]);

        // Custom CSRF: skips verification for requests with Authorization header
        $middleware->web(replace: [
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class => \App\Http\Middleware\VerifyCsrfToken::class,
        ]);

        // API group includes session/cookies/CSRF for hybrid auth (session + Sanctum token)
        // This is intentional — AuthenticateApiWeb checks session auth before Sanctum fallback
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
        ]);

        // Custom middleware aliases
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'checkAccount' => \App\Http\Middleware\CheckAccountStatus::class,
            'auth.common' => \App\Http\Middleware\AuthenticateApiWeb::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'check.ip.restriction' => \App\Http\Middleware\CheckIpRestriction::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $timeZone = 'Asia/Karachi';

        // 2nd message one day before appointment at 7:55 PM
        $schedule->command('appointment:2nd-message-on-appointment-day')
            ->dailyAt('19:55')->timezone($timeZone);

        // Deliver SMS on time of booking (every minute, no overlapping)
        $schedule->command('appointment:deliver-on-appointment-book')
            ->withoutOverlapping()
            ->everyMinute();

        // Inactive discounts with past end date
        $schedule->command('discounts:inactive')
            ->dailyAt('01:00')->timezone($timeZone);

        // Inactive bundles with past end date
        $schedule->command('bundles:inactive')
            ->dailyAt('01:00')->timezone($timeZone);

        // Appointment and Treatment daily stats
        $schedule->command('appointments:daily-stats')
            ->dailyAt('23:50')->timezone($timeZone);

        // Cash Flow: Daily Digest Email (08:00 AM PKT)
        $schedule->job(new \App\Jobs\SendCashflowDailyDigest())
            ->dailyAt('08:00')->timezone($timeZone);

        // Cash Flow: Monthly Report Email (1st of every month at 09:00 AM)
        $schedule->job(new \App\Jobs\SendCashflowMonthlyReport())
            ->monthlyOn(1, '09:00')->timezone($timeZone);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);
    })
    ->create();
