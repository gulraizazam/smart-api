<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Services\Order\OrderService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * OrderService::bulkDeleteOrders deletes an order's detail rows with a single
 * bulk DELETE per order instead of loading each detail and deleting it
 * one-by-one. OrderDetail has no SoftDeletes, no deleting/deleted event and
 * no observer, so the bulk delete bypasses no cascade or audit side-effect.
 *
 * Pins:
 *   1. The orders and ALL their detail rows are gone afterwards (behavior).
 *   2. The number of DELETE statements for detail rows does NOT grow with the
 *      number of detail rows — revert to the row-by-row loop and it climbs by
 *      one DELETE per detail row.
 */
class OrderBulkDeleteQueryCountTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        // order_details.product_id is NOT NULL — one shared product is enough
        // for these rows (bulkDeleteOrders never reads the product).
        $brand = \App\Models\Brand::create([
            'account_id' => 1,
            'name' => 'Bulk Delete Brand '.uniqid(),
            'status' => 1,
        ]);
        $product = new \App\Models\Product();
        $product->account_id = 1;
        $product->brand_id = $brand->id;
        $product->name = 'Bulk Delete Product '.uniqid();
        $product->slug = \Illuminate\Support\Str::slug($product->name);
        $product->product_type = 'for_sale';
        $product->created_by = auth()->id();
        $product->save();
        $this->productId = (int) $product->id;
    }

    private function makeOrderWithDetails(int $detailCount): Order
    {
        // Build rows directly (not via OrderFactory) — the factory pulls in
        // nested Warehouse/Patient factories and a `warehouse_id` column that
        // isn't on this schema. bulkDeleteOrders only touches orders +
        // order_details, so a minimal valid row is enough.
        $location = \App\Models\Locations::first();
        $order = Order::create([
            'account_id' => 1,
            'location_id' => $location->id,
            'order_type' => 'sale',
            'created_by' => auth()->id(),
        ]);

        for ($i = 0; $i < $detailCount; $i++) {
            OrderDetail::create([
                'account_id' => 1,
                'order_id' => $order->id,
                'product_id' => $this->productId,
                'quantity' => 1,
                'sale_price' => 100,
                'discount_price' => 0,
                'sale_price_after_discount' => 100,
                'order_type' => 'sale',
            ]);
        }

        return $order;
    }

    private function deleteDeleteCount(array $orderIds): int
    {
        $service = app(OrderService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->bulkDeleteOrders($orderIds);
        $deletes = collect(DB::getQueryLog())
            ->filter(fn (array $q): bool => str_starts_with(strtolower(trim($q['query'])), 'delete'))
            ->count();
        DB::disableQueryLog();

        return $deletes;
    }

    public function test_bulk_delete_removes_orders_and_all_their_details(): void
    {
        $order = $this->makeOrderWithDetails(3);

        $result = app(OrderService::class)->bulkDeleteOrders([$order->id]);

        $this->assertTrue($result['status']);
        $this->assertSame(0, Order::where('id', $order->id)->count());
        $this->assertSame(0, OrderDetail::where('order_id', $order->id)->count());
    }

    public function test_detail_deletes_are_bulk_not_per_row(): void
    {
        $smallOrder = $this->makeOrderWithDetails(1);
        $baseline = $this->deleteDeleteCount([$smallOrder->id]);

        $largeOrder = $this->makeOrderWithDetails(6);
        $withMany = $this->deleteDeleteCount([$largeOrder->id]);

        // Same number of DELETE statements regardless of how many detail rows
        // the order had — one bulk DELETE for the details, one for the order.
        // Row-by-row would make $withMany jump by ~5 over $baseline.
        $this->assertSame(
            $baseline,
            $withMany,
            "Detail DELETE count grew from {$baseline} to {$withMany} as detail rows increased — details are being deleted one-by-one (N+1)."
        );
    }
}
