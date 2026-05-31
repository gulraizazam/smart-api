<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function safeModify(string $table, string $column, string $sql): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        try {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$sql}");
        } catch (\Throwable $e) {
            \Log::warning("Skipping MODIFY {$table}.{$column}: " . $e->getMessage());
        }
    }

    public function up(): void
    {
        $this->safeModify('bundles', 'price', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
        $this->safeModify('bundles', 'services_price', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');

        $this->safeModify('bundle_has_services', 'service_price', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
        $this->safeModify('bundle_has_services', 'calculated_price', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');

        $this->safeModify('bundle_services_price_history', 'bundle_price', 'DECIMAL(11,2) DEFAULT NULL');
        $this->safeModify('bundle_services_price_history', 'bundle_services_price', 'DECIMAL(11,2) DEFAULT NULL');
        $this->safeModify('bundle_services_price_history', 'service_price', 'DECIMAL(11,2) DEFAULT NULL');

        $this->safeModify('discounts', 'amount', 'DECIMAL(11,2) NOT NULL');

        $this->safeModify('invoices', 'total_price', 'DECIMAL(11,2) NOT NULL');

        $this->safeModify('invoice_details', 'discount_price', 'DECIMAL(11,2) DEFAULT NULL');
        $this->safeModify('invoice_details', 'service_price', 'DECIMAL(11,2) NOT NULL');
        $this->safeModify('invoice_details', 'tax_exclusive_serviceprice', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
        $this->safeModify('invoice_details', 'tax_percenatage', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
        $this->safeModify('invoice_details', 'tax_price', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
        $this->safeModify('invoice_details', 'tax_including_price', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
        $this->safeModify('invoice_details', 'net_amount', 'DECIMAL(11,2) NOT NULL');

        $this->safeModify('orders', 'total_price', 'DECIMAL(8,2) DEFAULT NULL');

        $this->safeModify('order_details', 'sale_price', 'DECIMAL(8,2) NOT NULL');
        $this->safeModify('order_details', 'discount_price', 'DECIMAL(8,2) DEFAULT NULL');
        $this->safeModify('order_details', 'sale_price_after_discount', 'DECIMAL(8,2) NOT NULL');

        $this->safeModify('order_refunds', 'total_price', 'DECIMAL(8,2) NOT NULL');

        $this->safeModify('packages', 'total_price', 'DECIMAL(11,2) NOT NULL');
        $this->safeModify('packages', 'total_price_bk', 'DECIMAL(11,2) DEFAULT 0.00');

        $this->safeModify('package_advances', 'cash_amount', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');

        $this->safeModify('package_bundles', 'discount_price', 'DECIMAL(11,2) DEFAULT NULL');
        $this->safeModify('package_bundles', 'service_price', 'DECIMAL(11,2) NOT NULL');
        $this->safeModify('package_bundles', 'net_amount', 'DECIMAL(11,2) NOT NULL');
        $this->safeModify('package_bundles', 'tax_exclusive_net_amount', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
        $this->safeModify('package_bundles', 'tax_percenatage', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
        $this->safeModify('package_bundles', 'tax_price', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
        $this->safeModify('package_bundles', 'tax_including_price', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
        $this->safeModify('package_bundles', 'orignal_price', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');

        $this->safeModify('package_services', 'orignal_price', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
        $this->safeModify('package_services', 'price', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
        $this->safeModify('package_services', 'tax_exclusive_price', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
        $this->safeModify('package_services', 'tax_percenatage', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
        $this->safeModify('package_services', 'tax_price', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
        $this->safeModify('package_services', 'tax_including_price', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');

        $this->safeModify('plan_invoices', 'total_price', 'DECIMAL(11,2) NOT NULL');
        $this->safeModify('products', 'sale_price', 'DECIMAL(8,2) NOT NULL');
        $this->safeModify('roles', 'commission', 'DECIMAL(11,2) NOT NULL');
        $this->safeModify('services', 'price', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
        $this->safeModify('users', 'commission', 'DECIMAL(11,2) NOT NULL DEFAULT 0.00');
    }

    public function down(): void
    {
        $reverts = [
            ['bundles', 'price', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['bundles', 'services_price', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['bundle_has_services', 'service_price', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['bundle_has_services', 'calculated_price', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['bundle_services_price_history', 'bundle_price', 'DOUBLE(11,2) DEFAULT NULL'],
            ['bundle_services_price_history', 'bundle_services_price', 'DOUBLE(11,2) DEFAULT NULL'],
            ['bundle_services_price_history', 'service_price', 'DOUBLE(11,2) DEFAULT NULL'],
            ['discounts', 'amount', 'DOUBLE(11,2) NOT NULL'],
            ['invoices', 'total_price', 'DOUBLE(11,2) NOT NULL'],
            ['invoice_details', 'discount_price', 'DOUBLE(11,2) DEFAULT NULL'],
            ['invoice_details', 'service_price', 'DOUBLE(11,2) NOT NULL'],
            ['invoice_details', 'tax_exclusive_serviceprice', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['invoice_details', 'tax_percenatage', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['invoice_details', 'tax_price', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['invoice_details', 'tax_including_price', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['invoice_details', 'net_amount', 'DOUBLE(11,2) NOT NULL'],
            ['orders', 'total_price', 'DOUBLE(8,2) DEFAULT NULL'],
            ['order_details', 'sale_price', 'DOUBLE(8,2) NOT NULL'],
            ['order_details', 'discount_price', 'DOUBLE(8,2) DEFAULT NULL'],
            ['order_details', 'sale_price_after_discount', 'DOUBLE(8,2) NOT NULL'],
            ['order_refunds', 'total_price', 'DOUBLE(8,2) NOT NULL'],
            ['packages', 'total_price', 'DOUBLE(11,2) NOT NULL'],
            ['packages', 'total_price_bk', 'DOUBLE(11,2) DEFAULT 0.00'],
            ['package_advances', 'cash_amount', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['package_bundles', 'discount_price', 'DOUBLE(11,2) DEFAULT NULL'],
            ['package_bundles', 'service_price', 'DOUBLE(11,2) NOT NULL'],
            ['package_bundles', 'net_amount', 'DOUBLE(11,2) NOT NULL'],
            ['package_bundles', 'tax_exclusive_net_amount', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['package_bundles', 'tax_percenatage', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['package_bundles', 'tax_price', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['package_bundles', 'tax_including_price', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['package_bundles', 'orignal_price', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['package_services', 'orignal_price', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['package_services', 'price', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['package_services', 'tax_exclusive_price', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['package_services', 'tax_percenatage', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['package_services', 'tax_price', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['package_services', 'tax_including_price', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['plan_invoices', 'total_price', 'DOUBLE(11,2) NOT NULL'],
            ['products', 'sale_price', 'DOUBLE(8,2) NOT NULL'],
            ['roles', 'commission', 'DOUBLE(11,2) NOT NULL'],
            ['services', 'price', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            ['users', 'commission', 'DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
        ];

        foreach ($reverts as [$table, $col, $sql]) {
            $this->safeModify($table, $col, $sql);
        }
    }
};
