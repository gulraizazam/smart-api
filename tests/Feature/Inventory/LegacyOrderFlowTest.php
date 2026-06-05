<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Brand;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Stock;
use App\Services\Order\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Legacy (crm2) order/inventory flow — the FIFO batch revamp was reverted so
 * crm3 matches crm2 on the shared DB (the FIFO columns aren't deployed there).
 *
 * Invariants pinned here:
 *   - A sale decrements `inventories.quantity` (the source of truth) and writes
 *     ONE `stocks` OUT audit row + ONE `order_detail` per line.
 *   - Unit price is server-derived from the inventory/product sale price — the
 *     client-supplied `product_price` is ignored.
 *   - Employee buyers get an automatic 20% discount (stored on `orders.discount`).
 *   - Out-of-stock is rejected by validateOrderStock.
 *   - Refund and delete both restore `inventories.quantity`.
 *   - No FIFO `remaining_quantity` / `order_detail_consumption` is touched.
 */
class LegacyOrderFlowTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    private function makeProduct(float $salePrice = 1000): Product
    {
        $brand = Brand::create([
            'account_id' => 1,
            'name' => 'Test Brand '.uniqid(),
            'status' => 1,
        ]);

        $uid = uniqid();
        $product = new Product();
        $product->account_id = 1;
        $product->brand_id = $brand->id;
        $product->name = 'Test Product '.$uid;
        $product->slug = \Illuminate\Support\Str::slug($product->name);
        $product->sale_price = $salePrice;
        $product->purchase_price = 500;
        $product->product_type = 'for_sale';
        $product->status = 1;
        $product->active = 1;
        $product->created_by = Auth::id() ?? 1;
        $product->save();

        return $product;
    }

    /**
     * Seed on-hand stock at a centre the crm2 way: an `inventories` row
     * (source of truth + sale price) plus a `stocks` IN audit row (drives the
     * available calc). Returns the inventory row id used as `inventory_id`.
     */
    private function stockAt(int $productId, int $locationId, int $qty, float $salePrice): int
    {
        Stock::create([
            'account_id' => 1,
            'product_id' => $productId,
            'quantity' => $qty,
            'stock_type' => 'in',
            'location_id' => $locationId,
        ]);

        $inv = Inventory::create([
            'product_id' => $productId,
            'location_id' => $locationId,
            'quantity' => $qty,
            'sale_price' => $salePrice,
            'is_saleable' => 1,
        ]);

        return (int) $inv->id;
    }

    private function employeeSalePayload(int $productId, int $invId, int $locationId, int $qty, float $clientPrice = 0): array
    {
        return [
            'product_id' => [$productId],
            'quantity' => [$qty],
            'product_price' => [$clientPrice],
            'inventory_id' => [$invId],
            'payment_mode' => 1,
            'sold_to' => 'employee',
            'employee_id' => Auth::id(),
            'location_id' => $locationId,
            'discount' => 0,
        ];
    }

    public function test_sale_decrements_inventory_and_writes_out_ledger(): void
    {
        $loc = (int) $this->defaultLocation->id;
        $product = $this->makeProduct(1000);
        $invId = $this->stockAt($product->id, $loc, 10, 800);

        $order = app(OrderService::class)->createOrder(
            $this->employeeSalePayload($product->id, $invId, $loc, 3),
        );

        // Inventory cache (source of truth) decremented.
        $this->assertSame(7, (int) Inventory::find($invId)->quantity);

        // One audit OUT row, one order_detail — no batch splitting.
        $this->assertSame(1, Stock::where('order_id', $order->id)->where('stock_type', 'out')->count());
        $this->assertSame(1, OrderDetail::where('order_id', $order->id)->count());

        // Employee buyer → 20% off the inventory sale price (800).
        $detail = OrderDetail::where('order_id', $order->id)->first();
        $this->assertSame(3, (int) $detail->quantity);
        $this->assertSame(800.0, (float) $detail->sale_price);
        $this->assertSame(640.0, (float) $detail->sale_price_after_discount);
        $this->assertSame(1920.0, (float) $order->total_price); // 3 × 640
        $this->assertSame(20.0, (float) $order->discount);

        // No FIFO ledger written.
        $this->assertSame(0, DB::table('order_detail_consumption')->count());
    }

    public function test_server_ignores_client_supplied_price(): void
    {
        $loc = (int) $this->defaultLocation->id;
        $product = $this->makeProduct(1000);
        $invId = $this->stockAt($product->id, $loc, 5, 800);

        // Client lies about the price; server must use the inventory price.
        $order = app(OrderService::class)->createOrder(
            $this->employeeSalePayload($product->id, $invId, $loc, 1, clientPrice: 99999),
        );

        $detail = OrderDetail::where('order_id', $order->id)->first();
        $this->assertSame(800.0, (float) $detail->sale_price);
    }

    public function test_out_of_stock_is_rejected(): void
    {
        $loc = (int) $this->defaultLocation->id;
        $product = $this->makeProduct(1000);
        $invId = $this->stockAt($product->id, $loc, 2, 800);

        $err = app(OrderService::class)->validateOrderStock(
            $this->employeeSalePayload($product->id, $invId, $loc, 5),
        );

        $this->assertNotNull($err);
        $this->assertStringContainsString('out of stock', strtolower($err));
    }

    public function test_refund_restores_inventory(): void
    {
        $loc = (int) $this->defaultLocation->id;
        $product = $this->makeProduct(1000);
        $invId = $this->stockAt($product->id, $loc, 10, 800);

        $svc = app(OrderService::class);
        $order = $svc->createOrder($this->employeeSalePayload($product->id, $invId, $loc, 4));
        $this->assertSame(6, (int) Inventory::find($invId)->quantity);

        $res = $svc->processRefund((int) $order->id, [
            'product_id' => [$product->id],
            'quantity' => [2],
            'product_price' => [800],
        ]);

        $this->assertTrue($res['success']);
        $this->assertSame(8, (int) Inventory::find($invId)->quantity); // 6 + 2 restored
    }

    public function test_delete_restores_inventory_and_removes_order(): void
    {
        $loc = (int) $this->defaultLocation->id;
        $product = $this->makeProduct(1000);
        $invId = $this->stockAt($product->id, $loc, 10, 800);

        $svc = app(OrderService::class);
        $order = $svc->createOrder($this->employeeSalePayload($product->id, $invId, $loc, 3));
        $this->assertSame(7, (int) Inventory::find($invId)->quantity);

        $result = Order::DeleteRecord($order->id);
        $this->assertTrue($result['status']);

        $this->assertSame(10, (int) Inventory::find($invId)->quantity); // restored
        $this->assertNull(Order::find($order->id));
    }
}
