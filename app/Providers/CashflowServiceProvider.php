<?php

namespace App\Providers;

use App\Models\CashFlow\CashTransfer;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\StaffAdvance;
use App\Models\CashFlow\StaffReturn;
use App\Models\CashFlow\VendorTransaction;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Observers\CashFlow\CashTransferObserver;
use App\Observers\CashFlow\ExpenseObserver;
use App\Observers\CashFlow\LocationCashflowObserver;
use App\Observers\CashFlow\StaffAdvanceObserver;
use App\Observers\CashFlow\StaffReturnObserver;
use App\Observers\CashFlow\PackageAdvanceObserver;
use App\Observers\CashFlow\VendorTransactionObserver;
use Illuminate\Support\ServiceProvider;

class CashflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register observers for cached balance updates
        Locations::observe(LocationCashflowObserver::class);
        Expense::observe(ExpenseObserver::class);
        CashTransfer::observe(CashTransferObserver::class);
        VendorTransaction::observe(VendorTransactionObserver::class);
        StaffAdvance::observe(StaffAdvanceObserver::class);
        StaffReturn::observe(StaffReturnObserver::class);
        PackageAdvances::observe(PackageAdvanceObserver::class);
    }
}
