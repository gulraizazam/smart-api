<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory revamp — turn the `stocks` ledger into the source of truth for
 * FIFO batches.
 *
 * Each `stocks` row with `stock_type='in'` becomes a batch:
 *   - `sale_price` captures the unit sale price set at receipt time
 *   - `remaining_quantity` tracks how much of that batch is still saleable
 *   - `location_id` / `warehouse_id` pin the batch to a physical place so
 *     FIFO can be applied per-centre rather than globally per-product
 *
 * `stock_type='out'` rows leave the new columns null — they're consumption
 * events, not batches.
 *
 * The FIFO consumer query needs to scan candidate batches quickly:
 *   WHERE product_id = ? AND location_id = ? AND stock_type='in'
 *     AND remaining_quantity > 0
 *   ORDER BY created_at ASC, id ASC
 *   FOR UPDATE
 * Indexed accordingly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('stocks', 'sale_price')) {
                $table->decimal('sale_price', 10, 2)->nullable()->after('quantity');
            }
            if (!Schema::hasColumn('stocks', 'remaining_quantity')) {
                $table->integer('remaining_quantity')->nullable()->after('sale_price');
            }
            if (!Schema::hasColumn('stocks', 'location_id')) {
                $table->unsignedBigInteger('location_id')->nullable()->after('remaining_quantity');
            }
            if (!Schema::hasColumn('stocks', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('location_id');
            }
        });

        // Indexes added in a second pass so they only reference columns that
        // were just created. Wrapped to stay idempotent on re-run.
        Schema::table('stocks', function (Blueprint $table) {
            $existing = collect(Schema::getIndexes('stocks'))->pluck('name')->all();

            if (!in_array('stocks_fifo_idx', $existing, true)) {
                $table->index(
                    ['product_id', 'location_id', 'stock_type', 'remaining_quantity', 'created_at'],
                    'stocks_fifo_idx',
                );
            }
            if (!in_array('stocks_location_id_idx', $existing, true)) {
                $table->index('location_id', 'stocks_location_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            // Drop indexes first so column drops don't fail on dependencies.
            $existing = collect(Schema::getIndexes('stocks'))->pluck('name')->all();

            if (in_array('stocks_fifo_idx', $existing, true)) {
                $table->dropIndex('stocks_fifo_idx');
            }
            if (in_array('stocks_location_id_idx', $existing, true)) {
                $table->dropIndex('stocks_location_id_idx');
            }
        });

        Schema::table('stocks', function (Blueprint $table) {
            foreach (['warehouse_id', 'location_id', 'remaining_quantity', 'sale_price'] as $col) {
                if (Schema::hasColumn('stocks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
