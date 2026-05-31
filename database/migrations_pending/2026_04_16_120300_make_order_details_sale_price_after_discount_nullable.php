<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// order_details.sale_price_after_discount was declared NOT NULL with no
// default, but OrderDetail::createRecord never writes it (the code stores the
// post-discount value in `sale_price` directly). The column has no readers
// anywhere in the app — it's effectively dead. Making it nullable unblocks
// order creation without risking data written elsewhere.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_details', 'sale_price_after_discount')) {
            return;
        }

        Schema::table('order_details', function (Blueprint $table): void {
            $table->decimal('sale_price_after_discount', 8, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // No-op: rows inserted after this migration may legitimately have
        // NULL in this column, so reverting to NOT NULL would fail.
    }
};
