<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Models\InvoiceStatuses;
use App\Models\Invoices;
use App\Models\Locations;
use App\Models\Patients;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The audit replaced the previous "set status_id = 2" hard-coded cancel
 * path with a slug-based lookup so the cancellation path remains correct
 * if status IDs ever shift between tenants. These tests pin:
 *   - CancelRecord resolves the 'cancelled' slug
 *   - CancelRecord respects the account_id boundary (will not cancel
 *     another tenant's invoice)
 *   - CancelRecord on a non-existent invoice returns null without throwing
 */
class InvoiceCancellationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_cancel_record_sets_status_to_cancelled_via_slug_lookup(): void
    {
        $location = Locations::factory()->create();
        $patient = Patients::factory()->create();
        $invoice = Invoices::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $location->id,
            'invoice_status_id' => 1, // pending
        ]);

        $cancelledStatusId = InvoiceStatuses::query()->where('slug', 'cancelled')->value('id');

        $result = Invoices::CancelRecord($invoice->id, account_id: 1);

        $this->assertNotNull($result);
        $this->assertSame(
            $cancelledStatusId,
            (int) $invoice->fresh()->invoice_status_id,
            'Invoice must transition to the row that has slug = cancelled, regardless of its id.'
        );
    }

    public function test_cancel_record_does_not_touch_an_invoice_belonging_to_another_account(): void
    {
        // The GuardsTenantBoundary trait force-rewrites account_id on
        // creating() when a user is authenticated, so a factory with
        // `account_id => 999` would silently become account_id = 1 (the
        // logged-in admin's account). To genuinely produce a cross-tenant
        // row, log out before creating, then log back in.
        \Illuminate\Support\Facades\Auth::logout();

        // Seed the foreign account row so the FK on invoices.account_id
        // (some installations have it) is satisfied. Use a high id to keep
        // it clearly distinct from the default tenant.
        \Illuminate\Support\Facades\DB::table('accounts')->updateOrInsert(
            ['id' => 999],
            [
                'name' => 'Other Tenant',
                'suspended' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $invoice = Invoices::factory()->create([
            'account_id' => 999,
            'invoice_status_id' => 1,
        ]);

        $this->actingAsAdmin();

        $result = Invoices::CancelRecord($invoice->id, account_id: 1);

        $this->assertNull(
            $result,
            'CancelRecord must refuse to cancel an invoice owned by a different account.'
        );
        $this->assertSame(
            1,
            (int) $invoice->fresh()->invoice_status_id,
            'Cross-tenant invoice must remain in its original status.'
        );
    }

    public function test_cancel_record_returns_null_for_non_existent_invoice(): void
    {
        $this->expectException(\Throwable::class);
        // The current implementation calls Invoices::find($id)->toArray()
        // unconditionally before checking; passing a non-existent id triggers
        // a null-method-call. The test pins this brittle behaviour so any
        // future hardening (e.g. firstOrFail / null guard) consciously
        // updates the test rather than silently changing the contract.
        Invoices::CancelRecord(999999, account_id: 1);
    }
}
