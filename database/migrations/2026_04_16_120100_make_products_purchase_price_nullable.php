<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fix-forward: products.purchase_price was added NOT NULL without a default by
// 2023_08_22_183843_add_purchase_price_to_products_table. Purchase price is
// tracked per stock batch (stocks.purchase_price), not on the product row, so
// Product::createRecord never sets it and inserts fail with SQLSTATE 1364.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'purchase_price')) {
            return;
        }

        // Backfill any legacy rows that were inserted with 0 so the nullable
        // switch doesn't change their displayed values.
        DB::table('products')->whereNull('purchase_price')->update(['purchase_price' => 0]);

        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('purchase_price', 10, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // No-op: reverting to NOT NULL would fail on rows inserted after this
        // migration that legitimately have no purchase price.
    }
};
