<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Brand;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderRefund;
use App\Models\PackageAdvances;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Services\Inventory\FifoConsumptionService;
use App\Services\Inventory\InsufficientStockException;
use App\Services\Order\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Feature tests for the 2026-05 inventory revamp:
 *
 *   - FIFO batches consumed oldest-first
 *   - Multi-batch lines split into separate order_detail rows
 *   - Server re-derives unit price from the batch (client price ignored)
 *   - Auto-discount: employee 20% / membership 10% / none
 *   - Refund restores qty back to the originating batches (LIFO of the
 *     consumption rows) and decrements order_detail.quantity
 *   - InsufficientStockException raised when oversupply requested
 */
class FifoOrderFlowTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    private function makeProduct(): Product
    {
        $brand = Brand::create([
            'account_id' => 1,
            'name' => 'Test Brand '.uniqid(),
            'status' => 1,
        ]);

        // The dev/test schema has `created_by` + `purchase_price` +
        // `product_type` + `slug` as NOT NULL with no defaults — fill
        // them. (`slug` is added by migration
        // 2026_04_16_120000_add_slug_to_products_table and is UNIQUE;
        // deriving it from the unique name keeps each row distinct.)
        $uid = uniqid();
        $product = new Product();
        $product->account_id = 1;
        $product->brand_id = $brand->id;
        $product->name = 'Test Product '.$uid;
        $product->slug = \Illuminate\Support\Str::slug($product->name);
        $product->sale_price = 1000;
        $product->purchase_price = 500;
        $product->product_type = 'for_sale';
        $product->status = 1;
        $product->active = 1;
        $product->created_by = Auth::id() ?? 1;
        $product->save();

        return $product;
    }

    private function makeBatch(int $productId, int $locationId, int $qty, float $price, ?string $createdAt = null): Stock
    {
        $stock = Stock::create([
            'account_id' => 1,
            'product_id' => $productId,
            'quantity' => $qty,
            'stock_type' => 'in',
            'sale_price' => $price,
            'remaining_quantity' => $qty,
            'location_id' => $locationId,
        ]);

        if ($createdAt) {
            $stock->created_at = $createdAt;
            $stock->save();
        }

        // Mirror cache so FifoConsumptionService::mirrorInventoryDecrement
        // has rows to walk.
        $inv = Inventory::where('product_id', $productId)
            ->where('location_id', $locationId)
            ->first();
        if ($inv) {
            $inv->update(['quantity' => (int) $inv->quantity + $qty]);
        } else {
            Inventory::create([
                'product_id' => $productId,
                'location_id' => $locationId,
                'quantity' => $qty,
                'is_saleable' => 1,
            ]);
        }

        return $stock;
    }

    public function test_fifo_consumes_oldest_batch_first(): void
    {
        $product = $this->makeProduct();
        $loc = (int) $this->defaultLocation->id;

        // Oldest batch should be picked first regardless of insertion order.
        $newer = $this->makeBatch($product->id, $loc, 5, 2000, '2026-05-10 09:00:00');
        $older = $this->makeBatch($product->id, $loc, 5, 1000, '2026-05-01 09:00:00');

        $fifo = app(FifoConsumptionService::class);
        $consumed = $fifo->consume($product->id, $loc, 3, 1);

        $this->assertCount(1, $consumed);
        $this->assertSame($older->id, $consumed[0]->stockId);
        $this->assertSame(3, $consumed[0]->quantity);
        $this->assertSame(1000.0, $consumed[0]->salePrice);

        $this->assertSame(2, (int) $older->fresh()->remaining_quantity);
        $this->assertSame(5, (int) $newer->fresh()->remaining_quantity);
    }

    public function test_multi_batch_consumption_splits_lines(): void
    {
        $product = $this->makeProduct();
        $loc = (int) $this->defaultLocation->id;
        $b1 = $this->makeBatch($product->id, $loc, 10, 1000, '2026-05-01 09:00:00');
        $b2 = $this->makeBatch($product->id, $loc, 10, 2000, '2026-05-10 09:00:00');

        $patient = User::factory()->create(['account_id' => 1, 'user_type_id' => 1, 'active' => 1]);

        $order = app(OrderService::class)->createOrder([
            'sold_to' => 'patient',
            'patient_id' => $patient->id,
            'location_id' => $loc,
            'payment_mode' => (int) $this->cashPaymentMode->id,
            'product_id' => [$product->id],
            'quantity' => [15],
        ]);

        $this->assertNotNull($order);
        $details = OrderDetail::where('order_id', $order->id)->orderBy('id')->get();
        $this->assertCount(2, $details, 'A 15-unit order across 10+10 batches must split into 2 lines.');

        // First line draws from older batch at 1000.
        $this->assertSame(10, (int) $details[0]->quantity);
        $this->assertSame(1000.0, (float) $details[0]->sale_price);
        // Second line spills into newer batch at 2000.
        $this->assertSame(5, (int) $details[1]->quantity);
        $this->assertSame(2000.0, (float) $details[1]->sale_price);

        $this->assertSame(0, (int) $b1->fresh()->remaining_quantity);
        $this->assertSame(5, (int) $b2->fresh()->remaining_quantity);

        // Total = 10*1000 + 5*2000 = 20000 (no discount — patient with no membership).
        $this->assertSame(20000.0, (float) $order->fresh()->total_price);
    }

    public function test_server_ignores_client_unit_price(): void
    {
        $product = $this->makeProduct();
        $loc = (int) $this->defaultLocation->id;
        $this->makeBatch($product->id, $loc, 5, 750);

        $patient = User::factory()->create(['account_id' => 1, 'user_type_id' => 1, 'active' => 1]);

        $order = app(OrderService::class)->createOrder([
            'sold_to' => 'patient',
            'patient_id' => $patient->id,
            'location_id' => $loc,
            'payment_mode' => (int) $this->cashPaymentMode->id,
            'product_id' => [$product->id],
            'quantity' => [2],
            // Adversarial client tries to set $0.01 unit price — server
            // must ignore and use the batch price.
            'product_price' => [0.01],
            'grand_total' => 0.02,
        ]);

        $details = OrderDetail::where('order_id', $order->id)->get();
        $this->assertSame(750.0, (float) $details[0]->sale_price);
        $this->assertSame(1500.0, (float) $order->fresh()->total_price);
    }

    public function test_employee_buyer_gets_20_percent_discount(): void
    {
        $product = $this->makeProduct();
        $loc = (int) $this->defaultLocation->id;
        $this->makeBatch($product->id, $loc, 10, 1000);

        $employee = User::factory()->create(['account_id' => 1, 'user_type_id' => 2, 'active' => 1]);

        $order = app(OrderService::class)->createOrder([
            'sold_to' => 'employee',
            'employee_id' => $employee->id,
            'location_id' => $loc,
            'payment_mode' => (int) $this->cashPaymentMode->id,
            'product_id' => [$product->id],
            'quantity' => [3],
        ]);

        $this->assertSame('employee', $order->buyer_type);
        $this->assertSame(20.0, (float) $order->auto_discount_percent);

        $detail = OrderDetail::where('order_id', $order->id)->first();
        $this->assertSame(1000.0, (float) $detail->sale_price);
        $this->assertSame(200.0, (float) $detail->discount_price);
        $this->assertSame(800.0, (float) $detail->sale_price_after_discount);
        // 800 net * 3 units = 2400
        $this->assertSame(2400.0, (float) $order->fresh()->total_price);
    }

    public function test_insufficient_stock_throws_and_rolls_back(): void
    {
        $product = $this->makeProduct();
        $loc = (int) $this->defaultLocation->id;
        $batch = $this->makeBatch($product->id, $loc, 2, 1000);

        $patient = User::factory()->create(['account_id' => 1, 'user_type_id' => 1, 'active' => 1]);

        $ordersBefore = Order::count();

        $thrown = null;
        try {
            app(OrderService::class)->createOrder([
                'sold_to' => 'patient',
                'patient_id' => $patient->id,
                'location_id' => $loc,
                'payment_mode' => (int) $this->cashPaymentMode->id,
                'product_id' => [$product->id],
                'quantity' => [5],
            ]);
        } catch (InsufficientStockException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertSame(5, $thrown->requested);
        $this->assertSame(2, $thrown->available);

        // Rollback — no order header, no decrement.
        $this->assertSame($ordersBefore, Order::count());
        $this->assertSame(2, (int) $batch->fresh()->remaining_quantity);
    }

    public function test_refund_restores_qty_to_originating_batch(): void
    {
        $product = $this->makeProduct();
        $loc = (int) $this->defaultLocation->id;
        $batch = $this->makeBatch($product->id, $loc, 10, 1000);

        $patient = User::factory()->create(['account_id' => 1, 'user_type_id' => 1, 'active' => 1]);

        $svc = app(OrderService::class);
        $order = $svc->createOrder([
            'sold_to' => 'patient',
            'patient_id' => $patient->id,
            'location_id' => $loc,
            'payment_mode' => (int) $this->cashPaymentMode->id,
            'product_id' => [$product->id],
            'quantity' => [4],
        ]);
        $this->assertSame(6, (int) $batch->fresh()->remaining_quantity);

        $result = $svc->processRefund((int) $order->id, [
            'product_id' => [$product->id],
            'quantity' => [3],
        ]);

        $this->assertTrue($result['success']);
        // Refund restored 3 → batch back up to 9.
        $this->assertSame(9, (int) $batch->fresh()->remaining_quantity);

        // order_detail.quantity decremented from 4 to 1.
        $detail = OrderDetail::where('order_id', $order->id)->first();
        $this->assertSame(1, (int) $detail->quantity);

        // Refund header recorded.
        $refund = OrderRefund::where('order_id', $order->id)->first();
        $this->assertNotNull($refund);
        $this->assertSame(3, (int) $refund->quantity);
        $this->assertSame(3000.0, (float) $refund->total_price);
    }

    public function test_refund_more_than_sold_aborts_transaction(): void
    {
        $product = $this->makeProduct();
        $loc = (int) $this->defaultLocation->id;
        $batch = $this->makeBatch($product->id, $loc, 10, 1000);

        $patient = User::factory()->create(['account_id' => 1, 'user_type_id' => 1, 'active' => 1]);

        $svc = app(OrderService::class);
        $order = $svc->createOrder([
            'sold_to' => 'patient',
            'patient_id' => $patient->id,
            'location_id' => $loc,
            'payment_mode' => (int) $this->cashPaymentMode->id,
            'product_id' => [$product->id],
            'quantity' => [2],
        ]);

        $remainingBefore = (int) $batch->fresh()->remaining_quantity;

        $result = $svc->processRefund((int) $order->id, [
            'product_id' => [$product->id],
            'quantity' => [5], // > sold quantity of 2
        ]);

        $this->assertFalse($result['success']);
        // Rollback — batch untouched, order_detail.quantity unchanged.
        $this->assertSame($remainingBefore, (int) $batch->fresh()->remaining_quantity);
        $detail = OrderDetail::where('order_id', $order->id)->first();
        $this->assertSame(2, (int) $detail->quantity);
        $this->assertSame(0, OrderRefund::where('order_id', $order->id)->count());
    }

    /**
     * The OrderService::writeOrderRevenueLedger path must always pin the
     * resulting package_advances row to the originating order via
     * `order_id`. Without this link the OrderObserver delete-cascade
     * (next test) can't find which ledger row to reverse, and the
     * General Sales / Revenue Detail reports lose the ability to
     * distinguish plan revenue from inventory revenue.
     */
    public function test_order_create_writes_linked_package_advance(): void
    {
        $product = $this->makeProduct();
        $loc = (int) $this->defaultLocation->id;
        $this->makeBatch($product->id, $loc, 5, 800);

        $patient = User::factory()->create(['account_id' => 1, 'user_type_id' => 1, 'active' => 1]);

        $order = app(OrderService::class)->createOrder([
            'sold_to' => 'patient',
            'patient_id' => $patient->id,
            'location_id' => $loc,
            'payment_mode' => (int) $this->cashPaymentMode->id,
            'product_id' => [$product->id],
            'quantity' => [2],
        ]);

        $ledger = PackageAdvances::where('order_id', $order->id)->first();
        $this->assertNotNull($ledger, 'A package_advances row must be written for every order.');
        $this->assertSame('in', $ledger->cash_flow);
        $this->assertSame((float) $order->total_price, (float) $ledger->cash_amount);
        $this->assertSame($order->id, (int) $ledger->order_id);
        $this->assertSame(0, (int) $ledger->is_refund);
    }

    /**
     * Hard-deleting an order must cascade through `OrderObserver::deleted`
     * to soft-delete the linked package_advances row(s). That trips
     * `PackageAdvanceObserver::deleted` which decrements the cash pool.
     * Also asserts the consumption rows + order_details + stocks ledger
     * rows tied to the order are cleaned up, and that the FIFO batch's
     * `remaining_quantity` is restored (otherwise future sales would
     * silently over-draw).
     */
    public function test_order_delete_cascades_to_package_advances_and_restores_batch(): void
    {
        $product = $this->makeProduct();
        $loc = (int) $this->defaultLocation->id;
        $batch = $this->makeBatch($product->id, $loc, 10, 500);
        $batchBefore = (int) $batch->fresh()->remaining_quantity;

        $patient = User::factory()->create(['account_id' => 1, 'user_type_id' => 1, 'active' => 1]);

        $order = app(OrderService::class)->createOrder([
            'sold_to' => 'patient',
            'patient_id' => $patient->id,
            'location_id' => $loc,
            'payment_mode' => (int) $this->cashPaymentMode->id,
            'product_id' => [$product->id],
            'quantity' => [3],
        ]);

        // Sanity checks on the pre-delete state.
        $this->assertSame($batchBefore - 3, (int) $batch->fresh()->remaining_quantity);
        $this->assertSame(1, PackageAdvances::where('order_id', $order->id)->count());

        $result = Order::DeleteRecord($order->id);
        $this->assertTrue($result->get('status'));

        // OrderObserver fired → linked ledger row deleted (soft).
        $this->assertSame(
            0,
            PackageAdvances::where('order_id', $order->id)->whereNull('deleted_at')->count(),
            'Linked package_advances must be soft-deleted by OrderObserver.',
        );
        $this->assertSame(
            1,
            PackageAdvances::withTrashed()->where('order_id', $order->id)->count(),
            'Soft-deleted row must still exist as an audit trail.',
        );

        // FIFO batch fully restored — otherwise the next sale would
        // silently consume stock the deleted order had already eaten.
        $this->assertSame($batchBefore, (int) $batch->fresh()->remaining_quantity);

        // Children cleaned up.
        $this->assertNull(Order::find($order->id));
        $this->assertSame(0, OrderDetail::where('order_id', $order->id)->count());
        $this->assertSame(0, Stock::where('order_id', $order->id)->count());
    }
}
