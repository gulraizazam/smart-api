<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a `package_advances` row to the originating `orders` row.
 *
 * Inventory sales write a `package_advances` row inside
 * OrderService::createOrder / processRefund (added in the prior task so
 * the existing revenue reports pick them up). To make the lifecycle
 * symmetric — i.e. so the new OrderObserver can reverse the pool credit
 * when an order is deleted — we need a direct link from the ledger row
 * back to the order. Without it, the cascade would have to guess by
 * (account, location, amount, timestamp), which gets ambiguous when two
 * clerks book the same SKU at the same centre in the same minute.
 *
 * No FK constraint, consistent with the sibling `package_id` and
 * `invoice_id` columns on this table — they're indexed but unconstrained
 * because the parent tables get hard-deleted in some legacy paths and a
 * strict FK would block.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_advances', function (Blueprint $table) {
            if (!Schema::hasColumn('package_advances', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->after('invoice_id');
            }
        });

        Schema::table('package_advances', function (Blueprint $table) {
            $existing = collect(Schema::getIndexes('package_advances'))->pluck('name')->all();
            if (!in_array('package_advances_order_id_idx', $existing, true)) {
                $table->index('order_id', 'package_advances_order_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('package_advances', function (Blueprint $table) {
            $existing = collect(Schema::getIndexes('package_advances'))->pluck('name')->all();
            if (in_array('package_advances_order_id_idx', $existing, true)) {
                $table->dropIndex('package_advances_order_id_idx');
            }
        });

        Schema::table('package_advances', function (Blueprint $table) {
            if (Schema::hasColumn('package_advances', 'order_id')) {
                $table->dropColumn('order_id');
            }
        });
    }
};
