<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function safeChange(string $table, string $column, callable $cb): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        try {
            Schema::table($table, $cb);
        } catch (\Throwable $e) {
            \Log::warning("Skipping change on {$table}.{$column}: " . $e->getMessage());
        }
    }

    public function up(): void
    {
        if (Schema::hasTable('product_details')) {
            try {
                if (Schema::hasColumn('product_details', 'purchase_price')) {
                    DB::statement("UPDATE product_details SET purchase_price = NULL WHERE purchase_price = ''");
                }
                if (Schema::hasColumn('product_details', 'total_purchase_price')) {
                    DB::statement("UPDATE product_details SET total_purchase_price = NULL WHERE total_purchase_price = ''");
                }
            } catch (\Throwable $e) {
                \Log::warning('product_details cleanup skipped: ' . $e->getMessage());
            }
        }

        $this->safeChange('activities', 'amount', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable()->change();
        });

        $this->safeChange('base_discount_services', 'service_price', function (Blueprint $table) {
            $table->decimal('service_price', 12, 2)->nullable()->change();
        });

        $this->safeChange('bundle_has_services', 'discount_amount', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->nullable()->change();
        });

        $this->safeChange('get_discount_services', 'service_price', function (Blueprint $table) {
            $table->decimal('service_price', 12, 2)->nullable()->change();
        });
        $this->safeChange('get_discount_services', 'discount_amount', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->nullable()->change();
        });

        $this->safeChange('product_details', 'purchase_price', function (Blueprint $table) {
            $table->decimal('purchase_price', 12, 2)->nullable()->default(0)->change();
        });
        $this->safeChange('product_details', 'total_purchase_price', function (Blueprint $table) {
            $table->decimal('total_purchase_price', 12, 2)->nullable()->default(0)->change();
        });

        $this->safeChange('refunds', 'refund_amount', function (Blueprint $table) {
            $table->decimal('refund_amount', 12, 2)->default(0)->change();
        });

        $this->safeChange('user_vouchers', 'total_amount', function (Blueprint $table) {
            $table->decimal('total_amount', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        $this->safeChange('activities', 'amount', function (Blueprint $table) {
            $table->string('amount', 300)->nullable()->change();
        });

        $this->safeChange('base_discount_services', 'service_price', function (Blueprint $table) {
            $table->string('service_price', 300)->nullable()->change();
        });

        $this->safeChange('bundle_has_services', 'discount_amount', function (Blueprint $table) {
            $table->string('discount_amount', 500)->nullable()->change();
        });

        $this->safeChange('get_discount_services', 'service_price', function (Blueprint $table) {
            $table->string('service_price', 300)->nullable()->change();
        });
        $this->safeChange('get_discount_services', 'discount_amount', function (Blueprint $table) {
            $table->string('discount_amount', 300)->nullable()->change();
        });

        $this->safeChange('product_details', 'purchase_price', function (Blueprint $table) {
            $table->string('purchase_price', 300)->change();
        });
        $this->safeChange('product_details', 'total_purchase_price', function (Blueprint $table) {
            $table->string('total_purchase_price', 300)->change();
        });

        $this->safeChange('refunds', 'refund_amount', function (Blueprint $table) {
            $table->string('refund_amount', 191)->change();
        });

        $this->safeChange('user_vouchers', 'total_amount', function (Blueprint $table) {
            $table->string('total_amount', 500)->nullable()->change();
        });
    }
};
