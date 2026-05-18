<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory revamp — reconciliation for 2026_05_15_120300.
 *
 * The prior backfill iterated `inventories` rows and skipped duplicate
 * (product, location) pairs via an existence check. On this tenant a
 * single (product, location) has many `inventories` rows (one per
 * historical receipt) — the prior backfill therefore created the
 * synthetic batch from the *first* row's quantity, undercounting all the
 * others.
 *
 * Fix: for every distinct (product, location, warehouse, account) tuple
 * with positive on-hand stock, set the synthetic batch's
 * `remaining_quantity` and `quantity` to the SUM of all `inventories`
 * rows for that tuple. Create the synthetic batch if it's missing.
 *
 * Synthetic batches are identified by the same signature as the prior
 * migration: stock_type='in', sale_price NOT NULL, order_id NULL,
 * product_detail_id NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasTransferId = Schema::hasColumn('stocks', 'transfer_id');

        $totals = DB::table('inventories as i')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('i.quantity', '>', 0)
            ->select([
                'i.product_id',
                'i.location_id',
                'i.warehouse_id',
                'p.sale_price',
                'p.account_id',
                DB::raw('SUM(i.quantity) as on_hand'),
            ])
            ->groupBy('i.product_id', 'i.location_id', 'i.warehouse_id', 'p.sale_price', 'p.account_id')
            ->get();

        $now = now();
        foreach ($totals as $row) {
            $existing = DB::table('stocks')
                ->where('product_id', $row->product_id)
                ->where('location_id', $row->location_id)
                ->where('stock_type', 'in')
                ->whereNull('order_id')
                ->whereNull('product_detail_id')
                ->whereNotNull('sale_price')
                ->when($hasTransferId, fn ($q) => $q->whereNull('transfer_id'))
                ->first();

            if ($existing) {
                DB::table('stocks')
                    ->where('id', $existing->id)
                    ->update([
                        'quantity' => (int) $row->on_hand,
                        'remaining_quantity' => (int) $row->on_hand,
                        'sale_price' => $row->sale_price,
                        'warehouse_id' => $row->warehouse_id,
                        'updated_at' => $now,
                    ]);
            } else {
                $insert = [
                    'account_id' => $row->account_id,
                    'product_id' => $row->product_id,
                    'product_detail_id' => null,
                    'order_id' => null,
                    'quantity' => (int) $row->on_hand,
                    'stock_type' => 'in',
                    'sale_price' => $row->sale_price,
                    'remaining_quantity' => (int) $row->on_hand,
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
    }

    public function down(): void
    {
        // No-op — this is a data fix that cannot be cleanly reversed without
        // losing the corrected totals. The 120300 down() removes the
        // synthetic batches if a full rollback of the revamp is needed.
    }
};
