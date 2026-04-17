<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fix-forward: StoreProductRequest validates sale_price as nullable and a
// separate updateSalePrice endpoint exists for setting it later, but the
// rewritten create_products_table migration declared the column NOT NULL.
// Aligning the schema with the validation rule + original nullable intent.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'sale_price')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('sale_price', 10, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // No-op: reverting to NOT NULL would fail on rows inserted after this
        // migration that legitimately have no sale price set yet.
    }
};
