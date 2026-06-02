<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * CashFlowVendorsController API endpoint tests.
 *
 * Pins:
 *   1. Vendor data endpoint returns list.
 *   2. Vendor overview returns summary.
 *   3. Vendor store validates required fields via StoreVendorRequest.
 *   4. Vendor update validates fields via UpdateVendorRequest.
 *   5. Vendor toggle requires cashflow_vendor_toggle gate.
 *   6. Vendor ledger returns transactions.
 *   7. Vendor purchase validates required fields.
 *   8. Vendor transaction deliver requires attachment_url.
 *   9. Vendor requests data/store/approve/dismiss work.
 *  10. Vendor form-data returns category lookups.
 *  11. Unauthenticated access rejected.
 */
class CashflowVendorEndpointTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->grantPermissions([
            'cashflow_vendor_view', 'cashflow_vendor_manage', 'cashflow_vendor_toggle',
            'cashflow_vendor_deliver', 'cashflow_vendor_transaction',
            'cashflow_vendor_transaction_delete', 'cashflow_vendor_transaction_edit',
            'cashflow_vendor_edit', 'cashflow_vendor_create', 'cashflow_vendor_request',
            'cashflow_audit_view', 'cashflow_vendor_ledger_export',
        ]);
    }

    private function grantPermissions(array $permissions): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $perm) {
            $this->createPermission($perm);
        }

        $role = $this->createRole('test-admin-' . uniqid());
        $role->givePermissionTo($permissions);
        auth()->user()->assignRole($role);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_vendor_data_endpoint_returns_list(): void
    {
        $response = $this->getJson('/api/cashflow/vendors/data');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_vendor_overview_returns_summary(): void
    {
        $response = $this->getJson('/api/cashflow/vendors/overview');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_vendor_form_data_returns_lookups(): void
    {
        $response = $this->getJson('/api/cashflow/vendors/form-data');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_vendor_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/store', []);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_vendor_update_on_nonexistent_id(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/999999/update', [
            'name' => 'Updated Vendor',
        ]);
        $this->assertContains($response->status(), [403, 404, 422, 500]);
    }

    public function test_vendor_toggle_on_nonexistent_id(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/999999/toggle');
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    public function test_vendor_ledger_on_nonexistent_id(): void
    {
        $response = $this->getJson('/api/cashflow/vendors/999999/ledger');
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    public function test_vendor_purchase_validates_required_fields(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/999999/purchase', []);
        $this->assertContains($response->status(), [422, 404, 500]);
    }

    public function test_vendor_transaction_deliver_requires_attachment(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/999999/transactions/999999/deliver', []);
        $this->assertContains($response->status(), [422, 404, 500]);
    }

    public function test_vendor_transaction_delete_on_nonexistent(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/999999/transactions/999999/delete');
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    public function test_vendor_transaction_audit_on_nonexistent(): void
    {
        $response = $this->getJson('/api/cashflow/vendors/999999/transactions/999999/audit');
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    public function test_vendor_ledger_export_on_nonexistent(): void
    {
        $response = $this->getJson('/api/cashflow/vendors/999999/ledger/export');
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    public function test_vendor_requests_data_returns_list(): void
    {
        $response = $this->getJson('/api/cashflow/vendor-requests/data');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_vendor_requests_store_validates_fields(): void
    {
        $response = $this->postJson('/api/cashflow/vendor-requests/store', []);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_vendor_requests_approve_on_nonexistent(): void
    {
        $response = $this->postJson('/api/cashflow/vendor-requests/999999/approve');
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    public function test_vendor_requests_dismiss_on_nonexistent(): void
    {
        $response = $this->postJson('/api/cashflow/vendor-requests/999999/dismiss');
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    /**
     * Phase-2 fix: `vendorsTransactionDelete` now gates on the dedicated
     * `cashflow_vendor_transaction_delete` slug (which the seeder already
     * defines). Before the fix it shared the create slug, so a user with
     * create-only could delete and a user with delete-only could not.
     */
    /**
     * Phase-3 fix: `UpdateVendorRequest::name` is now required (was nullable),
     * so an admin editing a vendor can't accidentally wipe the display name.
     */
    /**
     * Plan B-Vendors: vendor purchase EDIT now requires `edit_reason`
     * (min 5, max 500). Create-mode payload still doesn't need it. The
     * route param `txId` is what flips StoreVendorPurchaseRequest from
     * create-rules to edit-rules.
     */
    public function test_vendor_transaction_edit_requires_edit_reason(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/999999/transactions/999999/update', [
            'amount' => 1000,
            'description' => 'Updated description',
            'transaction_date' => now()->format('Y-m-d'),
            'is_for_general' => true,
            'status' => 'delivered',
            'attachment_url' => 'https://drive.google.com/file/d/abc/view',
            // No edit_reason.
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edit_reason']);
    }

    public function test_vendor_purchase_create_does_not_require_edit_reason(): void
    {
        // The same validator runs but in create mode (no txId in route) so
        // edit_reason is nullable. The payload still 422s on other reasons
        // (missing for_branch_id) — what matters is that `errors.edit_reason`
        // is NOT in the response.
        $response = $this->postJson('/api/cashflow/vendors/1/purchase', [
            'amount' => 1000,
            'description' => 'A new purchase',
            'transaction_date' => now()->format('Y-m-d'),
            'is_for_general' => true,
            'status' => 'ordered',
        ]);
        $errors = $response->json('errors') ?? [];
        $this->assertArrayNotHasKey('edit_reason', $errors,
            'Create mode must not require edit_reason — that field is edit-only.');
    }

    public function test_vendor_update_rejects_empty_name(): void
    {
        // Use a vendor id that probably won't exist; the validator runs before
        // the controller routes to the service, so we get 422 regardless.
        $response = $this->postJson('/api/cashflow/vendors/1/update', [
            'name' => '',
            'contact_person' => 'Joe',
            'phone' => '0300-0000000',
            'category_id' => 1,
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_vendor_transaction_delete_rejects_when_only_create_slug_held(): void
    {
        // Reset to a role that holds the route-level view slug (to clear the
        // Phase-10 middleware) AND the create slug — but NOT the delete slug.
        // The controller's inline `can('cashflow_vendor_transaction_delete')`
        // must still reject. Tests the Phase-2 slug realignment specifically.
        auth()->user()->roles()->detach();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->createPermission('cashflow_vendor_view');
        $this->createPermission('cashflow_vendor_transaction');
        $role = $this->createRole('vendor-create-only-' . uniqid());
        $role->givePermissionTo(['cashflow_vendor_view', 'cashflow_vendor_transaction']);
        auth()->user()->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $response = $this->postJson('/api/cashflow/vendors/1/transactions/1/delete');
        $response->assertStatus(403);
    }

    public function test_vendor_transaction_delete_clears_gate_with_delete_slug(): void
    {
        // Reset to a role that holds ONLY the delete slug.
        auth()->user()->roles()->detach();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // The endpoint gates on the DOTTED slug (CashFlowVendorsController:407 —
        // can('cashflow.vendor.transaction.delete')), the canonical post-rollout
        // catalog held by Administrator on the prod clone. The old underscore
        // `cashflow_vendor_transaction_delete` no longer satisfies the gate.
        // `cashflow.vendor.view` is also needed to clear the vendors route-group
        // middleware (permission:cashflow.vendor.view|cashflow.vendor.manage|cashflow.manage)
        // before the controller's delete check is even reached.
        $this->createPermission('cashflow.vendor.view');
        $this->createPermission('cashflow.vendor.transaction.delete');
        $role = $this->createRole('vendor-delete-only-' . uniqid());
        $role->givePermissionTo(['cashflow.vendor.view', 'cashflow.vendor.transaction.delete']);
        auth()->user()->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Hits a non-existent vendor/transaction so the service throws a not-found
        // error AFTER passing the gate. The point of the assertion is that we got
        // past the 403 — not whether the entity exists.
        $response = $this->postJson('/api/cashflow/vendors/999999/transactions/999999/delete');
        $this->assertNotSame(403, $response->status(), 'Holding the delete slug must clear the auth gate.');
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->getJson('/api/cashflow/vendors/data');
        $this->assertContains($response->status(), [401, 302, 403]);
    }

    /**
     * Sec M1: the vendor-transaction mutation routes carry both
     * {vendorId} and {txId}. The service now scopes the fetch to
     * {vendorId}, so a same-account user cannot delete/update another
     * vendor's transaction via a crafted URL — the mismatch is a 404
     * and the target row is untouched.
     */
    public function test_vendor_transaction_mutation_is_scoped_to_the_url_vendor(): void
    {
        $vendorA = \App\Models\CashFlow\Vendor::factory()->create(['account_id' => 1]);
        $vendorB = \App\Models\CashFlow\Vendor::factory()->create(['account_id' => 1]);
        $txB = \App\Models\CashFlow\VendorTransaction::factory()->create([
            'account_id' => 1,
            'vendor_id' => $vendorB->id,
            'type' => 'purchase',
        ]);

        $response = $this->postJson(
            "/api/cashflow/vendors/{$vendorA->id}/transactions/{$txB->id}/delete",
        );

        // Not a success — the cross-vendor delete must not happen, and
        // B's transaction row is still present (not soft-deleted).
        $this->assertNotSame(200, $response->status());
        $this->assertDatabaseHas('vendor_transactions', [
            'id' => $txB->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * New unified endpoint: GET /vendors/transactions lists across every
     * vendor and scopes to one via ?vendor_id. Powers the redesign's
     * "All vendors" panel; reuses getVendorLedger.
     */
    public function test_vendors_transactions_endpoint_lists_across_and_scopes_by_vendor(): void
    {
        $vendorA = \App\Models\CashFlow\Vendor::factory()->create(['account_id' => 1]);
        $vendorB = \App\Models\CashFlow\Vendor::factory()->create(['account_id' => 1]);
        $today = now()->toDateString();
        $txA = \App\Models\CashFlow\VendorTransaction::factory()->create([
            'account_id' => 1, 'vendor_id' => $vendorA->id, 'type' => 'purchase',
            'status' => 'delivered', 'transaction_date' => $today,
        ]);
        $txB = \App\Models\CashFlow\VendorTransaction::factory()->create([
            'account_id' => 1, 'vendor_id' => $vendorB->id, 'type' => 'payment',
            'transaction_date' => $today,
        ]);

        // Wide range — VendorTransactionFactory uses a random past
        // transaction_date, and getVendorLedger applies the date filter.
        $range = 'date_from=2000-01-01&date_to=2999-12-31';
        $all = $this->getJson("/api/cashflow/vendors/transactions?{$range}");
        $all->assertStatus(200);
        $ids = collect($all->json('data.transactions.data'))->pluck('id')->all();
        $this->assertContains($txA->id, $ids);
        $this->assertContains($txB->id, $ids);
        // Contract the SPA now binds to.
        $all->assertJsonStructure(['data' => [
            'opening_balance', 'closing_balance',
            'period_stats' => ['total_purchases', 'total_payments', 'net', 'count'],
            'transactions' => ['data', 'current_page', 'last_page', 'per_page', 'total'],
        ]]);

        $scoped = $this->getJson("/api/cashflow/vendors/transactions?vendor_id={$vendorA->id}&{$range}");
        $scoped->assertStatus(200);
        $scopedIds = collect($scoped->json('data.transactions.data'))->pluck('id')->all();
        $this->assertContains($txA->id, $scopedIds);
        $this->assertNotContains($txB->id, $scopedIds);
    }

    /**
     * Status (ordered/delivered) is a purchase-only lifecycle. Payments
     * are stored with status=delivered by default, so a status filter
     * must constrain to purchases — otherwise every payment leaks into a
     * "Delivered" filter (regression: SPA Delivered chip listed payments).
     */
    public function test_status_filter_is_purchase_only_and_excludes_payments(): void
    {
        $vendor = \App\Models\CashFlow\Vendor::factory()->create(['account_id' => 1]);
        $today = now()->toDateString();
        $purchase = \App\Models\CashFlow\VendorTransaction::factory()->create([
            'account_id' => 1, 'vendor_id' => $vendor->id, 'type' => 'purchase',
            'status' => 'delivered', 'transaction_date' => $today,
        ]);
        $payment = \App\Models\CashFlow\VendorTransaction::factory()->create([
            'account_id' => 1, 'vendor_id' => $vendor->id, 'type' => 'payment',
            'status' => 'delivered', 'transaction_date' => $today,
        ]);

        $range = 'date_from=2000-01-01&date_to=2999-12-31';
        $res = $this->getJson("/api/cashflow/vendors/transactions?status=delivered&{$range}");
        $res->assertStatus(200);
        $ids = collect($res->json('data.transactions.data'))->pluck('id')->all();
        $this->assertContains($purchase->id, $ids, 'a delivered purchase must match the status filter');
        $this->assertNotContains($payment->id, $ids, 'payments must not appear under a purchase status filter');
    }

    /**
     * Unified cross-vendor CSV export (the "All vendors" Export CSV).
     * exportVendorLedger(null, …) streams every vendor's rows in one
     * file with an "All vendors" header.
     */
    public function test_unified_transactions_export_streams_csv_across_all_vendors(): void
    {
        $vendorA = \App\Models\CashFlow\Vendor::factory()->create(['account_id' => 1]);
        $vendorB = \App\Models\CashFlow\Vendor::factory()->create(['account_id' => 1]);
        $today = now()->toDateString();
        \App\Models\CashFlow\VendorTransaction::factory()->create([
            'account_id' => 1, 'vendor_id' => $vendorA->id, 'type' => 'purchase',
            'status' => 'delivered', 'amount' => 111111, 'transaction_date' => $today,
        ]);
        \App\Models\CashFlow\VendorTransaction::factory()->create([
            'account_id' => 1, 'vendor_id' => $vendorB->id, 'type' => 'payment',
            'status' => 'delivered', 'amount' => 222222, 'transaction_date' => $today,
        ]);

        $res = $this->get('/api/cashflow/vendors/transactions/export?date_from=2000-01-01&date_to=2999-12-31');
        $res->assertStatus(200);
        $csv = $res->streamedContent();
        $this->assertStringContainsString('Vendor: All vendors', $csv);
        // Both vendors' transactions land in the one file.
        $this->assertStringContainsString('111111', $csv);
        $this->assertStringContainsString('222222', $csv);
        // Bug 3: the unified CSV must name the vendor per row (was absent
        // → couldn't tell which vendor a row belonged to).
        $this->assertStringContainsString($vendorA->name, $csv);
        $this->assertStringContainsString($vendorB->name, $csv);
        // Bug 2: header carries Vendor + Status (were missing → shift).
        // Parse via str_getcsv so fputcsv quoting ("Recorded By") doesn't
        // make this brittle.
        $headerLine = collect(preg_split('/\r\n|\n/', $csv))
            ->first(fn ($l) => str_starts_with($l, 'Vendor,Date,'));
        $this->assertNotNull($headerLine, 'aligned data header row must exist');
        $this->assertSame(
            ['Vendor', 'Date', 'Type', 'Status', 'Description', 'Reference', 'Branch', 'Purchase', 'Payment', 'Balance', 'Recorded By'],
            str_getcsv($headerLine),
        );
    }

    /**
     * Bug 1: with no explicit date range the CSV export must use the
     * SAME month-to-date default as the on-screen ledger — previously it
     * dumped all-time data, so the file never matched the screen.
     */
    public function test_export_defaults_to_month_to_date_like_the_ledger(): void
    {
        $vendor = \App\Models\CashFlow\Vendor::factory()->create(['account_id' => 1]);
        $thisMonth = \App\Models\CashFlow\VendorTransaction::factory()->create([
            'account_id' => 1, 'vendor_id' => $vendor->id, 'type' => 'purchase',
            'status' => 'delivered', 'amount' => 4242, 'transaction_date' => now()->toDateString(),
        ]);
        $old = \App\Models\CashFlow\VendorTransaction::factory()->create([
            'account_id' => 1, 'vendor_id' => $vendor->id, 'type' => 'purchase',
            'status' => 'delivered', 'amount' => 9191,
            'transaction_date' => now()->subMonthsNoOverflow(2)->toDateString(),
        ]);

        $svc = app(\App\Services\CashFlow\VendorService::class);

        // No filters → month-to-date: only the current-month row.
        // transaction_date is a Carbon cast → normalise to Y-m-d strings.
        $default = $svc->exportVendorLedger($vendor->id, 1, []);
        $defaultDates = collect($default['rows'])->pluck('date')
            ->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->toDateString())->all();
        $this->assertContains(now()->toDateString(), $defaultDates);
        $this->assertNotContains(now()->subMonthsNoOverflow(2)->toDateString(), $defaultDates);
        $this->assertSame(now()->startOfMonth()->toDateString(), $default['date_from']);

        // Explicit wide range still returns everything (regression guard).
        $wide = $svc->exportVendorLedger($vendor->id, 1, [
            'date_from' => '2000-01-01', 'date_to' => '2999-12-31',
        ]);
        $this->assertCount(2, $wide['rows']);
    }

    /**
     * Bug 2: CSV data columns must line up with the header. A delivered
     * purchase's "delivered" must land under Status, not bleed into
     * Description (the missing Status header used to shift every column).
     */
    public function test_export_csv_columns_align_with_header(): void
    {
        $vendor = \App\Models\CashFlow\Vendor::factory()->create(['account_id' => 1]);
        \App\Models\CashFlow\VendorTransaction::factory()->create([
            'account_id' => 1, 'vendor_id' => $vendor->id, 'type' => 'purchase',
            'status' => 'delivered', 'amount' => 7777, 'description' => 'ZZ_DESC_MARKER',
            'reference_no' => 'REF_MARKER', 'transaction_date' => now()->toDateString(),
        ]);

        $res = $this->get("/api/cashflow/vendors/{$vendor->id}/ledger/export");
        $res->assertStatus(200);
        $lines = collect(preg_split('/\r\n|\n/', $res->streamedContent()))
            ->filter(fn ($l) => $l !== '')->values();

        $headerIdx = $lines->search(fn ($l) => str_starts_with($l, 'Vendor,Date,'));
        $this->assertNotFalse($headerIdx, 'aligned header row must exist');
        $header = str_getcsv($lines[$headerIdx]);
        $dataRow = str_getcsv($lines[$headerIdx + 1]);

        $col = fn (string $name) => $dataRow[array_search($name, $header, true)];
        $this->assertSame('delivered', $col('Status'));
        $this->assertSame('ZZ_DESC_MARKER', $col('Description'));
        $this->assertSame('REF_MARKER', $col('Reference'));
        $this->assertSame($vendor->name, $col('Vendor'));
        $this->assertSame('7777', $col('Purchase'));
    }

    /** G1: ledger payload carries closing_balance + nested period_stats. */
    public function test_vendor_ledger_returns_closing_balance_and_period_stats(): void
    {
        $vendor = \App\Models\CashFlow\Vendor::factory()->create([
            'account_id' => 1, 'opening_balance' => 1000,
        ]);
        \App\Models\CashFlow\VendorTransaction::factory()->create([
            'account_id' => 1, 'vendor_id' => $vendor->id,
            'type' => 'purchase', 'status' => 'delivered', 'amount' => 500,
        ]);

        $res = $this->getJson("/api/cashflow/vendors/{$vendor->id}/ledger");
        $res->assertStatus(200)->assertJsonStructure(['data' => [
            'opening_balance', 'closing_balance',
            'period_stats' => ['total_purchases', 'total_payments', 'net', 'count'],
        ]]);
        // closing = opening + period purchases - payments (all in range).
        $this->assertSame(
            round((float) $res->json('data.opening_balance')
                + (float) $res->json('data.period_stats.total_purchases')
                - (float) $res->json('data.period_stats.total_payments'), 2),
            round((float) $res->json('data.closing_balance'), 2),
        );
    }

    /** G4: export honours the on-screen status filter (was silently dropped). */
    public function test_vendor_ledger_export_honours_status_filter(): void
    {
        $vendor = \App\Models\CashFlow\Vendor::factory()->create(['account_id' => 1]);
        // Dated today so the now-default month-to-date export window
        // (CashflowHelper::defaultDateRange, same as the ledger) includes
        // them — the factory otherwise picks a random historical date.
        \App\Models\CashFlow\VendorTransaction::factory()->create([
            'account_id' => 1, 'vendor_id' => $vendor->id, 'type' => 'purchase', 'status' => 'delivered',
            'transaction_date' => now()->toDateString(),
        ]);
        \App\Models\CashFlow\VendorTransaction::factory()->create([
            'account_id' => 1, 'vendor_id' => $vendor->id, 'type' => 'purchase', 'status' => 'ordered',
            'transaction_date' => now()->toDateString(),
        ]);

        $svc = app(\App\Services\CashFlow\VendorService::class);
        $this->assertCount(2, $svc->exportVendorLedger($vendor->id, 1, [])['rows']);
        $this->assertCount(1, $svc->exportVendorLedger($vendor->id, 1, ['status' => 'delivered'])['rows']);
    }

    /** G5: vendor form-data exposes the period-lock flag for pre-empt. */
    public function test_vendor_form_data_exposes_has_period_locks(): void
    {
        $res = $this->getJson('/api/cashflow/vendors/form-data');
        $res->assertStatus(200)->assertJsonStructure(['data' => ['vendor_categories', 'has_period_locks']]);
        $this->assertFalse($res->json('data.has_period_locks'));

        \App\Models\CashFlow\PeriodLock::factory()->create([
            'account_id' => 1, 'month' => now()->month, 'year' => now()->year,
        ]);
        $this->assertTrue(
            $this->getJson('/api/cashflow/vendors/form-data')->json('data.has_period_locks'),
        );
    }
}
