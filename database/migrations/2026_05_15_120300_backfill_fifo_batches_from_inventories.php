<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory revamp — one-time backfill that seeds the new FIFO batch shape
 * from pre-existing inventory rows.
 *
 * Strategy: for every `inventories` row with quantity > 0, write a single
 * synthetic `stocks` row of type='in' priced at the current
 * `products.sale_price`, with `remaining_quantity = inventories.quantity`
 * and the same location/warehouse pinning.
 *
 * Why this shape:
 *  - Pre-FIFO sales had no per-batch price; using the current global price
 *    is the only honest backfill we can do.
 *  - Existing `stocks.in` rows did not carry sale_price either, so we
 *    can't simply repurpose them; the synthetic row supersedes them for
 *    FIFO consumption.
 *  - One row per (product, location) keeps the cutover small. Future
 *    receipts append new batches; existing stock will deplete first.
 *
 * Idempotent: re-running skips inventories that already have a backfill
 * row (matched by a `transfer_id IS NULL AND order_id IS NULL` IN-stock
 * row with `sale_price` populated for the same product/location pair —
 * the synthetic shape).
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('inventories as i')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('i.quantity', '>', 0)
            ->select([
                'i.product_id',
                'i.location_id',
                'i.warehouse_id',
                'i.quantity',
                'p.sale_price',
                'p.account_id',
            ])
            ->get();

        $hasTransferId = Schema::hasColumn('stocks', 'transfer_id');
        $now = now();
        foreach ($rows as $row) {
            // Skip if a backfill batch already exists for this (product,
            // location) — keeps the migration idempotent on re-runs.
            $existsQuery = DB::table('stocks')
                ->where('product_id', $row->product_id)
                ->where('location_id', $row->location_id)
                ->where('stock_type', 'in')
                ->whereNull('order_id')
                ->whereNotNull('sale_price');
            if ($hasTransferId) {
                $existsQuery->whereNull('transfer_id');
            }
            if ($existsQuery->exists()) {
                continue;
            }

            $insert = [
                'account_id' => $row->account_id,
                'product_id' => $row->product_id,
                'product_detail_id' => null,
                'order_id' => null,
                'quantity' => $row->quantity,
                'stock_type' => 'in',
                'sale_price' => $row->sale_price,
                'remaining_quantity' => $row->quantity,
                'location_id' => $row->location_id,
                'warehouse_id' => $row->warehouse_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($hasTransferId) {
                $insert['transfer_id'] = null;
            }
            DB::table('stocks')->insert($insert);
        }
    }

    public function down(): void
    {
        // Identify backfill rows by their synthetic signature: stock_type=in,
        // sale_price NOT NULL, order_id NULL, product_detail_id NULL.
        $query = DB::table('stocks')
            ->where('stock_type', 'in')
            ->whereNotNull('sale_price')
            ->whereNull('order_id')
            ->whereNull('product_detail_id');
        if (Schema::hasColumn('stocks', 'transfer_id')) {
            $query->whereNull('transfer_id');
        }
        $query->delete();
    }
};
