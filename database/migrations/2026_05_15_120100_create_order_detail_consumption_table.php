<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory revamp — records which FIFO batch each order_detail consumed
 * from, and how much.
 *
 * On order create the FIFO consumer writes one row per batch drawn. On
 * refund the reversal walks these rows (LIFO of consumption order) and
 * adds `remaining_quantity` back to the originating `stocks` row, so a
 * refund restores stock to the exact same batches it came from.
 *
 * The `stock_id` references a `stocks` row of `stock_type='in'`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_detail_consumption')) {
            return;
        }

        Schema::create('order_detail_consumption', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_detail_id');
            $table->unsignedBigInteger('stock_id')->comment('stocks.id, stock_type=in (batch row)');
            $table->integer('quantity');
            $table->decimal('sale_price', 10, 2)->comment('Frozen at consumption time — used for refund symmetry and reporting');
            $table->unsignedBigInteger('account_id');
            $table->timestamps();

            $table->foreign('order_detail_id')->references('id')->on('order_details');
            $table->foreign('stock_id')->references('id')->on('stocks');

            $table->index(['order_detail_id'], 'odc_order_detail_idx');
            $table->index(['stock_id'], 'odc_stock_idx');
            $table->index(['account_id'], 'odc_account_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_detail_consumption');
    }
};
