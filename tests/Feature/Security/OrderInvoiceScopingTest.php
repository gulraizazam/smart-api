<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Locations;
use App\Models\Order;
use App\Models\Patients;
use App\Models\User;
use App\Services\Order\OrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Security audit 2026-06 — Order invoice account scoping (IDOR).
 *
 * The order-invoice read helpers must be scoped to the caller's account_id.
 * Previously getRefundDetail / getInvoiceData / getInvoiceJson used a bare
 * Order::find()/first(), letting a user with order permissions read any
 * tenant's order invoice (patient PII + financials) by guessing the id.
 * processRefund was already scoped — these tests pin the read helpers.
 *
 * Foreign (account 2) orders are created while logged OUT so the
 * GuardsTenantBoundary create-hook doesn't restamp them with account 1.
 * warehouse_id is null to avoid the Warehouse factory (a clinic sale uses
 * location_id); the scoped findOrFail throws before any relation is touched.
 */
class OrderInvoiceScopingTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->seedSecondAccount();
        $this->service = app(OrderService::class);
    }

    public function test_get_invoice_json_rejects_other_account(): void
    {
        $foreign = $this->foreignOrder();
        $this->actingAsAccountOne();

        $this->expectException(ModelNotFoundException::class);
        $this->service->getInvoiceJson($foreign->id);
    }

    public function test_get_invoice_data_rejects_other_account(): void
    {
        $foreign = $this->foreignOrder();
        $this->actingAsAccountOne();

        $this->expectException(ModelNotFoundException::class);
        $this->service->getInvoiceData($foreign->id);
    }

    public function test_get_refund_detail_returns_null_for_other_account(): void
    {
        $foreign = $this->foreignOrder();
        $this->actingAsAccountOne();

        $this->assertNull($this->service->getRefundDetail($foreign->id));
    }

    /**
     * Created logged-out so account_id=2 is honoured (not restamped to 1).
     * Inserted directly: OrderFactory references a `warehouse_id` column the
     * test schema doesn't carry, so we write the minimal real columns. The
     * scoped findOrFail throws on account before any relation is read, so a
     * bare order row is enough to exercise the boundary.
     */
    private function foreignOrder(): Order
    {
        $patient = Patients::factory()->create(['account_id' => 2]);
        $creator = User::factory()->create(['account_id' => 2]);
        $location = Locations::factory()->create(['account_id' => 2]);

        $id = DB::table('orders')->insertGetId([
            'patient_id' => $patient->id,
            'location_id' => $location->id,
            'total_price' => 1000.00,
            'order_type' => 'sale',
            'payment_mode' => (int) ($this->cashPaymentMode->id ?? 1),
            'created_by' => $creator->id,
            'account_id' => 2,
            'status' => 1,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::findOrFail($id);
    }

    private function actingAsAccountOne(): void
    {
        $this->actingAs(User::factory()->create(['account_id' => 1]));
    }

    private function seedSecondAccount(): void
    {
        DB::table('accounts')->updateOrInsert(
            ['id' => 2],
            [
                'name' => 'Second Account',
                'email' => 'second@example.com',
                'contact' => '0000000001',
                'suspended' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
