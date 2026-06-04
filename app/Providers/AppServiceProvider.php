<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\AppointmentEvent;
use App\Events\CustomFormEvent;
use App\Events\CustomFormFieldEvent;
use App\Listeners\AuthActivityListener;
use App\Listeners\PermissionActivityListener;
use App\Models\Appointments;
use App\Models\CashFlow\CashTransfer;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\ExpenseAttachment;
use App\Models\CashFlow\StaffAdvance;
use App\Models\CashFlow\StaffReturn;
use App\Models\CashFlow\VendorTransaction;
use App\Models\Invoices;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\Membership;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\PlanInvoice;
use App\Models\Services;
use App\Models\User;
use App\Http\Controllers\Api\CashFlow\ExpenseAttachmentsController;
use App\Http\Controllers\Api\CashFlow\VendorTransactionAttachmentsController;
use App\Models\BusinessClosure;
use App\Models\WorkingDayException;
use App\Observers\ActivityLogObserver;
use App\Observers\MembershipObserver;
use App\Observers\R2CleanupObserver;
use App\Observers\Schedule\OperatingDaysVersionObserver;
use App\Services\Storage\R2DocumentService;
use Illuminate\Support\Facades\Storage;
use App\Observers\CashFlow\CashTransferObserver;
use App\Observers\CashFlow\ExpenseObserver;
use App\Observers\CashFlow\LocationCashflowObserver;
use App\Observers\CashFlow\PackageAdvanceObserver;
use App\Observers\CashFlow\StaffAdvanceObserver;
use App\Observers\CashFlow\StaffReturnObserver;
use App\Observers\CashFlow\VendorTransactionObserver;
use App\Policies\AppointmentPolicy;
use App\Policies\CashFlowPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LeadPolicy;
use App\Policies\ManagementDashboardPolicy;
use App\Policies\MembershipPolicy;
use App\Policies\PatientPolicy;
use App\Policies\PlanPolicy;
use App\Policies\ServicePolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind R2DocumentService against the private `r2_invoices` disk for
        // the cash-flow attachments controller. The service itself is
        // bucket-agnostic; this is the first contextual wiring per its
        // docblock. Add a sibling `when()->needs()` block when transfers /
        // vendor purchases adopt the same multi-file pattern.
        $this->app->when(ExpenseAttachmentsController::class)
            ->needs(R2DocumentService::class)
            ->give(static fn (): R2DocumentService => new R2DocumentService(
                Storage::disk('r2_invoices')
            ));

        // Vendor transactions adopted the same multi-file pattern
        // (2026-05-17) — same private `invoices` bucket.
        $this->app->when(VendorTransactionAttachmentsController::class)
            ->needs(R2DocumentService::class)
            ->give(static fn (): R2DocumentService => new R2DocumentService(
                Storage::disk('r2_invoices')
            ));
    }

    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
        $this->configureAuthorization();
        $this->configurePassport();
        $this->configurePasswordResetUrl();
        $this->ensurePermissionCacheHealth();
        $this->registerObservers();
        $this->registerAuditEventListeners();
        $this->registerAuthEventListeners();
        $this->registerObservedModels();
    }

    /**
     * Reset-password emails default to `route('password.reset', $token)`
     * which renders a Blade view — that view dies at cutover. Override
     * the URL builder so emails link to the SPA's /reset-password/{token}
     * screen instead. The email parameter rides along so the SPA's reset
     * form can prefill it (and the backend re-validates it on submit, so
     * a tampered email simply fails the broker check).
     */
    private function configurePasswordResetUrl(): void
    {
        // Gate on the cutover flag: while the legacy Blade frontend is still live
        // (flag false — the default), reset emails must keep Laravel's default
        // route('password.reset') Blade link so live Blade users get a WORKING
        // reset page. Overriding to the SPA /reset-password screen before the SPA
        // is the live frontend would mail dead links to every Blade user
        // requesting a reset (go-live §5.2). Flips to true at go-live via
        // APP_SPA_CUTOVER_DONE in the prod .env.
        if (! (bool) config('app.spa_cutover_done')) {
            return;
        }

        ResetPasswordNotification::createUrlUsing(function ($notifiable, string $token): string {
            $base = rtrim((string) config('app.spa_url'), '/');
            $email = urlencode((string) $notifiable->getEmailForPasswordReset());

            return "{$base}/reset-password/{$token}?email={$email}";
        });
    }

    /**
     * Passport runs alongside Sanctum during the staged SPA migration.
     * Password grant issues short-lived access tokens + 30-day refresh
     * tokens for /api/v2/auth/login. Personal access tokens are capped
     * at 6 months per CLAUDE.md's finite-TTL rule.
     */
    private function configurePassport(): void
    {
        Passport::enablePasswordGrant();
        Passport::tokensExpireIn(now()->addMinutes(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));
    }

    /**
     * Attach App\Observers\ActivityLogObserver to every Eloquent model
     * under App\Models (recursive) except those in
     * config('activity_log.exclude'). Adding a new module to the audit
     * log requires zero code changes — the model is picked up on the
     * next boot and its rows start flowing into `activities` at the
     * configured tier (or `default_tier` if unlisted).
     *
     * Discovery is cached (file store; no Redis per infra constraint) for
     * one hour in production to avoid filesystem-walking every boot. In
     * local/testing the cache TTL is short so new model files are
     * picked up without manual cache flushing.
     */
    private function registerObservedModels(): void
    {
        if (! (bool) config('activity_log.enabled', true)) {
            return;
        }

        $exclude = (array) config('activity_log.exclude', []);
        $tierOverrides = (array) config('activity_log.tiers', []);
        $defaultTier = config('activity_log.default_tier');
        $autoDiscover = (bool) config('activity_log.auto_discover', true);

        $classes = $autoDiscover
            ? $this->discoverModelClasses()
            : array_keys($tierOverrides);

        foreach ($classes as $class) {
            if (in_array($class, $exclude, true)) {
                continue;
            }

            if (! class_exists($class)) {
                continue;
            }

            // Skip abstract models (e.g. BaseModel) and non-model classes
            // that happen to live under app/Models (helpers, enums).
            if (! is_subclass_of($class, Model::class)) {
                continue;
            }

            $reflect = new \ReflectionClass($class);
            if ($reflect->isAbstract()) {
                continue;
            }

            // Skip if the class has no resolvable tier — neither in the
            // overrides map nor a global default — meaning the operator
            // has explicitly opted that class out.
            $hasExplicitTier = isset($tierOverrides[$class]);
            if (! $hasExplicitTier && $defaultTier === null) {
                continue;
            }

            $class::observe(ActivityLogObserver::class);
        }
    }

    /**
     * @return array<int, class-string>
     */
    private function discoverModelClasses(): array
    {
        $ttl = app()->environment('production') ? 3600 : 60;

        return Cache::remember(
            'activity_log.discovered_models',
            $ttl,
            static function (): array {
                $base = app_path('Models');
                if (! is_dir($base)) {
                    return [];
                }

                $classes = [];
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if (! $file->isFile() || $file->getExtension() !== 'php') {
                        continue;
                    }

                    $relative = str_replace([$base.DIRECTORY_SEPARATOR, '.php'], '', $file->getRealPath());
                    $class = 'App\\Models\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
                    $classes[] = $class;
                }

                sort($classes);

                return $classes;
            },
        );
    }

    /**
     * Write a security-tier row to `activities` for every auth event
     * (login, logout, failed login, lockout, password reset). The listener
     * itself handles PII redaction — see App\Listeners\AuthActivityListener.
     */
    private function registerAuthEventListeners(): void
    {
        foreach ([
            Login::class => [AuthActivityListener::class, 'onLogin'],
            Logout::class => [AuthActivityListener::class, 'onLogout'],
            Failed::class => [AuthActivityListener::class, 'onFailed'],
            Lockout::class => [AuthActivityListener::class, 'onLockout'],
            PasswordReset::class => [AuthActivityListener::class, 'onPasswordReset'],
            RoleAttached::class => [PermissionActivityListener::class, 'onRoleAttached'],
            RoleDetached::class => [PermissionActivityListener::class, 'onRoleDetached'],
            PermissionAttached::class => [PermissionActivityListener::class, 'onPermissionAttached'],
            PermissionDetached::class => [PermissionActivityListener::class, 'onPermissionDetached'],
        ] as $eventClass => $handler) {
            Event::listen($eventClass, $handler);
        }
    }

    /**
     * Wire the legacy string-event audit trail listeners. The Appointments,
     * CustomForms and CustomFormFields models dispatch string events
     * ('appointment.created', 'custom_form.updating', etc.) from their boot()
     * hooks, and the matching App\Events\*Event classes carry the audit-write
     * logic in public methods — but until now no provider actually bound the
     * two together, so the audit trail for these three tables has been a
     * silent no-op. Pin the bindings here so every mutation on an
     * appointment, custom form or custom form field leaves a paper trail.
     */
    private function registerAuditEventListeners(): void
    {
        foreach ([
            'appointment.created' => [AppointmentEvent::class, 'created'],
            'appointment.updating' => [AppointmentEvent::class, 'updating'],
            'appointment.deleting' => [AppointmentEvent::class, 'deleting'],
            'custom_form.created' => [CustomFormEvent::class, 'created'],
            'custom_form.updating' => [CustomFormEvent::class, 'updating'],
            'custom_form.deleting' => [CustomFormEvent::class, 'deleting'],
            'custom_form_field.created' => [CustomFormFieldEvent::class, 'created'],
            'custom_form_field.updating' => [CustomFormFieldEvent::class, 'updating'],
            'custom_form_field.deleting' => [CustomFormFieldEvent::class, 'deleting'],
        ] as $eventName => $handler) {
            Event::listen($eventName, $handler);
        }
    }

    /**
     * Detect and auto-repair a corrupted Spatie permission cache.
     *
     * The file-backed permission cache occasionally loses entries (e.g. after
     * running tests that call Cache::flush). When the cached set is far smaller
     * than what the DB holds, every non-Super-Admin user loses access. This
     * check runs once per boot, compares cached vs DB count, and resets the
     * cache if the delta is too large.
     */
    private function ensurePermissionCacheHealth(): void
    {
        if (app()->runningInConsole() || app()->runningUnitTests()) {
            return;
        }

        try {
            $registrar = app(PermissionRegistrar::class);
            $cached = $registrar->getPermissions();

            if ($cached->count() < 50) {
                $dbCount = Permission::count();
                if ($dbCount > 50 && $cached->count() < $dbCount * 0.5) {
                    $registrar->forgetCachedPermissions();
                }
            }
        } catch (\Throwable) {
            // DB not available yet — skip silently
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $key = $request->user()?->id ?: $request->ip();

            // Heavy read/scrape + bulk surfaces get a tight ceiling; the rest
            // get a generous limit that normal SPA use never reaches (audit
            // 2026-06 — the limiter existed but was never attached).
            $isHeavy = $request->is('api/*datatable*', 'api/*export*', 'api/*search*');

            return $isHeavy
                ? Limit::perMinute(30)->by('heavy:'.$key)
                : Limit::perMinute(300)->by($key);
        });
    }

    private function configureAuthorization(): void
    {
        Gate::policy(Appointments::class, AppointmentPolicy::class);
        Gate::policy(Patients::class, PatientPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Packages::class, PlanPolicy::class);
        Gate::policy(PlanInvoice::class, PlanPolicy::class);
        Gate::policy(Leads::class, LeadPolicy::class);
        Gate::policy(Invoices::class, InvoicePolicy::class);
        Gate::policy(Services::class, ServicePolicy::class);
        Gate::policy(Membership::class, MembershipPolicy::class);
        Gate::policy(CashFlowPolicy::class, CashFlowPolicy::class);
        Gate::policy(ManagementDashboardPolicy::class, ManagementDashboardPolicy::class);

        Gate::before(fn ($user, $ability) => $user->hasRole('Super-Admin') ? true : null);

        // Permission-catalog alias bridge — translates between the legacy
        // snake_case slugs (`plans_cash_edit_amount`) the codebase's
        // Gate::allows checks reference and the new dotted slugs
        // (`plans.cash.edit_amount`) the role editor surfaces. PermissionAliasMap
        // returns every equivalent name; granting any of them satisfies the
        // original check. Uses hasPermissionTo directly to avoid recursing
        // back into Gate::allows. PermissionDoesNotExist is swallowed so an
        // alias for a slug that hasn't been seeded yet is treated as "no
        // match", not an error.
        Gate::before(function ($user, string $ability) {
            foreach (\App\Support\PermissionAliasMap::aliasesFor($ability) as $aliased) {
                try {
                    if ($user->hasPermissionTo($aliased)) {
                        return true;
                    }
                } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
                    continue;
                }
            }
            return null;
        });

        // Route-group gate for the /api/management-dashboard/* endpoints.
        // Those endpoints back BOTH the Management Dashboard (overview /
        // practitioners / marketing tabs, gated by `management_dashboard.view`)
        // AND the FDM dashboard tab, which reuses most of the same sections
        // (overview, people, patients, utilization, at-risk, today-status-board,
        // …). An FDM is granted the `dashboard_fdm` umbrella + `dashboard.fdm.*`
        // panel permissions — but NOT `management_dashboard.view`. Gating the
        // group on `management_dashboard.view` alone therefore 403'd every FDM
        // panel ("no data in any section") even though the SPA correctly showed
        // the FDM tab. Accept either permission here; every section is already
        // branch-scoped via ResourceScopeResolver, so an FDM only ever sees
        // their own branch regardless of which sections they can call.
        Gate::define(
            'access-management-dashboard-api',
            static fn ($user): bool => $user->can('management_dashboard.view') || $user->can('dashboard_fdm'),
        );
    }

    private function registerObservers(): void
    {
        Locations::observe(LocationCashflowObserver::class);
        Expense::observe(ExpenseObserver::class);
        // Wipes the R2 blob when a row is force-deleted (orphan-prune
        // command). Plain delete() / soft-delete is intentionally a no-op
        // — invoices are kept forever per business policy.
        ExpenseAttachment::observe(R2CleanupObserver::class);
        CashTransfer::observe(CashTransferObserver::class);
        VendorTransaction::observe(VendorTransactionObserver::class);
        StaffAdvance::observe(StaffAdvanceObserver::class);
        StaffReturn::observe(StaffReturnObserver::class);
        PackageAdvances::observe(PackageAdvanceObserver::class);
        // NOTE: Order has NO cash-flow observer. Inventory (Order) sales are
        // overlay-only on pool balances (PoolService::applyInventoryOverlay)
        // and never write package_advances / cached_balance, so there is no
        // stored order credit to reverse on delete. This matches the legacy
        // app (crm2), keeping both consistent on the shared prod column.
        // Mirrors a parent membership's end_date edits onto its
        // referrals so the data model stays internally consistent
        // without requiring every read site to look up the parent.
        Membership::observe(MembershipObserver::class);

        // Bumps the per-account OperatingDays version counter on every
        // schedule write so downstream caches (BenchmarkCalculator's
        // per-doctor revenue pool) invalidate immediately rather than
        // waiting for their natural TTL — see App\Support\OperatingDays::bumpVersion().
        BusinessClosure::observe(OperatingDaysVersionObserver::class);
        WorkingDayException::observe(OperatingDaysVersionObserver::class);
    }
}
