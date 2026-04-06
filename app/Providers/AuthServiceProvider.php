<?php

declare(strict_types=1);
namespace App\Providers;

use App\Models\Appointments;
use App\Models\Invoices;
use App\Models\Leads;
use App\Models\Membership;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\PlanInvoice;
use App\Models\Services;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\Policies\CashFlowPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LeadPolicy;
use App\Policies\MembershipPolicy;
use App\Policies\PatientPolicy;
use App\Policies\PlanPolicy;
use App\Policies\ServicePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * CashFlowPolicy has no dedicated Eloquent model — it is registered
     * as a standalone gate via Gate::policy() in boot().
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Appointments::class => AppointmentPolicy::class,
        Patients::class     => PatientPolicy::class,
        User::class         => UserPolicy::class,
        Packages::class     => PlanPolicy::class,
        PlanInvoice::class  => PlanPolicy::class,
        Leads::class        => LeadPolicy::class,
        Invoices::class     => InvoicePolicy::class,
        Services::class     => ServicePolicy::class,
        Membership::class   => MembershipPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Super-Admin bypasses all authorization checks.
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super-Admin') ? true : null;
        });

        // CashFlowPolicy has no dedicated Eloquent model; register it so
        // controllers can call $this->authorize('viewDashboard', CashFlowPolicy::class).
        Gate::policy(CashFlowPolicy::class, CashFlowPolicy::class);
    }
}
