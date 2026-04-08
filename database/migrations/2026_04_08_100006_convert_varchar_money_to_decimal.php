<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert 9 varchar money columns to DECIMAL(12,2).
     *
     * Data audit results (all clean, zero non-numeric values):
     *   activities.amount:              389K rows, max 365,000.00, 225K nulls
     *   base_discount_services:         0 rows
     *   bundle_has_services:            1,078 rows, all NULL
     *   get_discount_services:          0 rows
     *   product_details:                91 rows, all empty strings → becomes 0.00
     *   refunds.refund_amount:          0 rows
     *   user_vouchers.total_amount:     48 rows, max 30,000.00
     *
     * MariaDB automatically converts '' → 0.00 and numeric strings → decimal
     * during ALTER COLUMN. Empty strings are cleaned to NULL first for safety.
     */
    public function up(): void
    {
        // Clean empty strings to NULL before type conversion (prevents silent 0.00)
        DB::statement("UPDATE product_details SET purchase_price = NULL WHERE purchase_price = ''");
        DB::statement("UPDATE product_details SET total_purchase_price = NULL WHERE total_purchase_price = ''");

        Schema::table('activities', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable()->change();
        });

        Schema::table('base_discount_services', function (Blueprint $table) {
            $table->decimal('service_price', 12, 2)->nullable()->change();
        });

        Schema::table('bundle_has_services', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->nullable()->change();
        });

        Schema::table('get_discount_services', function (Blueprint $table) {
            $table->decimal('service_price', 12, 2)->nullable()->change();
            $table->decimal('discount_amount', 12, 2)->nullable()->change();
        });

        Schema::table('product_details', function (Blueprint $table) {
            $table->decimal('purchase_price', 12, 2)->nullable()->default(0)->change();
            $table->decimal('total_purchase_price', 12, 2)->nullable()->default(0)->change();
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->decimal('refund_amount', 12, 2)->default(0)->change();
        });

        Schema::table('user_vouchers', function (Blueprint $table) {
            $table->decimal('total_amount', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('amount', 300)->nullable()->change();
        });

        Schema::table('base_discount_services', function (Blueprint $table) {
            $table->string('service_price', 300)->nullable()->change();
        });

        Schema::table('bundle_has_services', function (Blueprint $table) {
            $table->string('discount_amount', 500)->nullable()->change();
        });

        Schema::table('get_discount_services', function (Blueprint $table) {
            $table->string('service_price', 300)->nullable()->change();
            $table->string('discount_amount', 300)->nullable()->change();
        });

        Schema::table('product_details', function (Blueprint $table) {
            $table->string('purchase_price', 300)->change();
            $table->string('total_purchase_price', 300)->change();
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->string('refund_amount', 191)->change();
        });

        Schema::table('user_vouchers', function (Blueprint $table) {
            $table->string('total_amount', 500)->nullable()->change();
        });
    }
};
