<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\Permission;
use Database\Seeders\CashflowPermissionsSeeder;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the slug inventory the production seeder must emit so that a fresh
 * deploy or a `php artisan permission:cache-reset` doesn't break controllers
 * that gate on these slugs.
 *
 * Pins:
 *   - Every slug enforced by `Gate::allows` / `->can(...)` in the Cash Flow
 *     controllers is present after the seeder runs.
 *   - The seeder is idempotent (running twice doesn't error or duplicate).
 *
 * Backstory: `cashflow_vendor_manage` was enforced in `CashFlowVendorsController`
 * (approve/dismiss vendor requests) but missing from the seeder. The test
 * fixture trait was masking the prod gap by `firstOrCreate`'ing the slug
 * at test setup. Pinning the contract here so the gap can't reopen.
 */
class CashflowPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every cashflow slug a controller checks at runtime.
     * Update this list whenever a new `Gate::allows('cashflow_*')` lands.
     */
    private const REQUIRED_SLUGS = [
        // Parent
        'cashflow_manage',

        // Dashboard / FDM
        'cashflow_dashboard',
        'cashflow_fdm_view',

        // Expenses
        'cashflow_expense_view',
        'cashflow_expense_create',
        'cashflow_expense_edit',
        'cashflow_expense_approve',
        'cashflow_expense_reject',
        'cashflow_expense_void',
        'cashflow_expense_resubmit',
        'cashflow_expense_unflag',
        'cashflow_expense_duplicate',
        'cashflow_expense_export',

        // Transfers
        'cashflow_transfer_view',
        'cashflow_transfer_create',
        'cashflow_transfer_edit',
        'cashflow_transfer_void',

        // Vendors
        'cashflow_vendor_view',
        'cashflow_vendor_create',
        'cashflow_vendor_edit',
        'cashflow_vendor_toggle',
        'cashflow_vendor_ledger_view',
        'cashflow_vendor_ledger_export',
        'cashflow_vendor_transaction',
        'cashflow_vendor_transaction_edit',
        'cashflow_vendor_transaction_delete',
        'cashflow_vendor_deliver',
        'cashflow_vendor_request',
        'cashflow_vendor_manage',

        // Staff
        'cashflow_staff_advance_view',
        'cashflow_staff_advance_create',
        'cashflow_staff_advance_edit',
        'cashflow_staff_advance_void',
        'cashflow_staff_return_create',
        'cashflow_staff_return_void',

        // Admin
        'cashflow_category_manage',
        'cashflow_pool_manage',
        'cashflow_period_lock',
        'cashflow_audit_view',
        'cashflow_settings',
        'cashflow_reports',
        'cashflow_reports_export',
    ];

    public function test_seeder_emits_every_slug_a_controller_enforces(): void
    {
        $this->seed(CashflowPermissionsSeeder::class);

        foreach (self::REQUIRED_SLUGS as $slug) {
            $this->assertTrue(
                Permission::where('name', $slug)->where('guard_name', 'web')->exists(),
                "Seeder missing required slug `{$slug}`. Some controller calls "
                    . "Gate::allows('{$slug}') / ->can('{$slug}') and will throw "
                    . 'PermissionDoesNotExist after permission:cache-reset.',
            );
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CashflowPermissionsSeeder::class);
        $countAfterFirst = Permission::where('name', 'like', 'cashflow_%')->count();

        $this->seed(CashflowPermissionsSeeder::class);
        $countAfterSecond = Permission::where('name', 'like', 'cashflow_%')->count();

        $this->assertSame(
            $countAfterFirst,
            $countAfterSecond,
            'Running CashflowPermissionsSeeder twice must not duplicate rows.',
        );
    }
}
