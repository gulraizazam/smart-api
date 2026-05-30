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

        // Preserve password fields from trimming. `search` is also exempt:
        // the cash-flow Payments list uses a trailing space as the user's
        // "I'm done with this token" signal to switch from substring to
        // word-boundary search (e.g. "pens " excludes "Dispenser"). If
        // TrimStrings ran first, the SPA's signal would be erased before
        // ExpenseService ever saw it.
        $middleware->trimStrings(except: [
            'current_password',
            'password',
            'password_confirmation',
            'search',
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
            // Opt-in Idempotency-Key support. Routes that opt in (write
            // endpoints on the cashflow + plan surfaces) will serve a
            // cached response for replayed requests carrying the same
            // header. See App\Http\Middleware\EnsureIdempotency for the
            // contract — and tests/Feature/Plan/IdempotencyMiddlewareTest.
            'idempotent' => \App\Http\Middleware\EnsureIdempotency::class,
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

        // 3rd reminder ~2 hours before the appointment. The command self-
        // guards to 09:00–19:00 (Asia/Karachi) and dedups per appointment via
        // SMSLogs, so a frequent cadence is safe and never double-sends.
        // Previously this ran ONLY via the GET /3rd-message-before-appointment
        // web URL (external pinger) — scheduled here ahead of the Blade
        // cutover that deletes that route (routes/web.php). HOLE-1.
        $schedule->command('appointment:3rd-message-before-appointment')
            ->everyFifteenMinutes()
            ->withoutOverlapping();

        // Inactive discounts with past end date
        $schedule->command('discounts:inactive')
            ->dailyAt('01:00')->timezone($timeZone);

        // Deactivate packages with past end date. Operates on the
        // `bundles` table behind the SPA "Packages" page (UI label
        // /packages → DB table `bundles`).
        $schedule->command('packages:expire')
            ->dailyAt('01:00')->timezone($timeZone);

        // Expire memberships past their end_date (active → 0; referral rows
        // cascade via the shared end_date — see project_membership_referral_lifecycle).
        // Previously ran ONLY via the GET /check-memberships web URL — scheduled
        // here ahead of the Blade cutover that deletes that route. HOLE-1.
        $schedule->command('memberships:expire')
            ->dailyAt('01:00')->timezone($timeZone);

        // Appointment and Treatment daily stats
        $schedule->command('appointments:daily-stats')
            ->dailyAt('23:50')->timezone($timeZone);

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

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);
    })
    ->create();
