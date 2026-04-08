<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Appointments;
use App\Models\CashFlow\CashTransfer;
use App\Models\CashFlow\Expense;
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
use App\Policies\MembershipPolicy;
use App\Policies\PatientPolicy;
use App\Policies\PlanPolicy;
use App\Policies\ServicePolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public const HOME = '/admin/home';

    #[\Override]
    public function register(): void
    {
        //
    }

    #[\Override]
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
        $this->configureAuthorization();
        $this->registerObservers();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn(Request $request): Limit => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
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

        Gate::before(fn($user, $ability) => $user->hasRole('Super-Admin') ? true : null);
    }

    private function registerObservers(): void
    {
        Locations::observe(LocationCashflowObserver::class);
        Expense::observe(ExpenseObserver::class);
        CashTransfer::observe(CashTransferObserver::class);
        VendorTransaction::observe(VendorTransactionObserver::class);
        StaffAdvance::observe(StaffAdvanceObserver::class);
        StaffReturn::observe(StaffReturnObserver::class);
        PackageAdvances::observe(PackageAdvanceObserver::class);
    }
}
