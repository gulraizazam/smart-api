<?php

declare(strict_types=1);

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\AuthenticateApiDual;
use App\Http\Middleware\AuthenticateApiWeb;
use App\Http\Middleware\CheckAccountStatus;
use App\Http\Middleware\CheckIpRestriction;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\VerifyCsrfToken;
use App\Jobs\SendCashflowDailyDigest;
use App\Jobs\SendCashflowMonthlyReport;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

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
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR |
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
            Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class => VerifyCsrfToken::class,
        ]);

        // API group includes session/cookies/CSRF for hybrid auth (session + Sanctum token)
        // This is intentional — AuthenticateApiWeb checks session auth before Sanctum fallback
        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
        ]);

        // Custom middleware aliases
        $middleware->alias([
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'checkAccount' => CheckAccountStatus::class,
            'auth.common' => AuthenticateApiWeb::class,
            'auth.api.dual' => AuthenticateApiDual::class,
            'permission' => CheckPermission::class,
            'check.ip.restriction' => CheckIpRestriction::class,
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
        $schedule->job(new SendCashflowDailyDigest)
            ->dailyAt('08:00')->timezone($timeZone);

        // Cash Flow: Monthly Report Email (1st of every month at 09:00 AM)
        $schedule->job(new SendCashflowMonthlyReport)
            ->monthlyOn(1, '09:00')->timezone($timeZone);

        // Activities: HIPAA-aligned archive sweep. PHI and security tiers
        // archive-only (append-only cold storage, 6-year min retention). HR
        // tiers prune at their configured cutoffs. NULL-tier rows are never
        // touched. See App\Console\Commands\ArchiveAndPruneActivities.
        $schedule->command('activities:archive')
            ->dailyAt('03:15')->timezone($timeZone)
            ->withoutOverlapping()
            ->onOneServer();

        // Security: prune expired password-reset tokens (H3 finding).
        // Closes the H3 audit gap where rows from 2019 still existed in
        // password_resets because no cleanup ever ran.
        $schedule->command('auth:clear-resets')
            ->daily()->timezone($timeZone);

        // Security: prune leftover bulk-import spreadsheets older than 24h
        // (L1 finding). The importer should also delete-after-import; this
        // is the safety net for crashed/aborted runs.
        $schedule->call(function (): void {
            $dir = storage_path('app');
            if (! is_dir($dir)) {
                return;
            }
            $cutoff = time() - 86400;
            foreach (glob($dir.DIRECTORY_SEPARATOR.'temp_import_*.xlsx') ?: [] as $file) {
                if (is_file($file) && filemtime($file) < $cutoff) {
                    @unlink($file);
                }
            }
        })->name('prune-temp-imports')->dailyAt('02:00')->timezone($timeZone);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);
    })
    ->create();
