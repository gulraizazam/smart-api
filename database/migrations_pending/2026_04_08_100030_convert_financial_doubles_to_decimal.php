<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            \Log::warning("Skipping {$table}.{$column}: " . $e->getMessage());
        }
    }

    public function up(): void
    {
        $this->safeChange('users', 'commission', fn (Blueprint $t) => $t->decimal('commission', 11, 2)->default(0)->change());
        $this->safeChange('services', 'price', fn (Blueprint $t) => $t->decimal('price', 11, 2)->default(0)->change());
        $this->safeChange('discounts', 'amount', fn (Blueprint $t) => $t->decimal('amount', 11, 2)->default(0)->change());
        $this->safeChange('packages', 'total_price', fn (Blueprint $t) => $t->decimal('total_price', 11, 2)->default(0)->change());
        $this->safeChange('packages', 'total_price_bk', fn (Blueprint $t) => $t->decimal('total_price_bk', 11, 2)->nullable()->change());
        $this->safeChange('package_advances', 'cash_amount', fn (Blueprint $t) => $t->decimal('cash_amount', 11, 2)->default(0)->change());
        $this->safeChange('invoices', 'total_price', fn (Blueprint $t) => $t->decimal('total_price', 11, 2)->default(0)->change());

        foreach (['orignal_price', 'price', 'tax_exclusive_price', 'tax_percenatage', 'tax_price', 'tax_including_price'] as $col) {
            $this->safeChange('package_services', $col, fn (Blueprint $t) => $t->decimal($col, 11, 2)->default(0)->change());
            $this->safeChange('bundle_package_services', $col, fn (Blueprint $t) => $t->decimal($col, 11, 2)->default(0)->change());
        }

        foreach (['bundle_price', 'bundle_services_price', 'service_price'] as $col) {
            $this->safeChange('bundle_services_price_history', $col, fn (Blueprint $t) => $t->decimal($col, 11, 2)->default(0)->change());
        }

        foreach (['service_price', 'calculated_price'] as $col) {
            $this->safeChange('bundle_has_services', $col, fn (Blueprint $t) => $t->decimal($col, 11, 2)->default(0)->change());
        }

        foreach (['discount_price', 'service_price', 'net_amount', 'orignal_price', 'tax_exclusive_net_amount', 'tax_percenatage', 'tax_price', 'tax_including_price'] as $col) {
            $this->safeChange('package_bundles', $col, fn (Blueprint $t) => $t->decimal($col, 11, 2)->default(0)->change());
        }

        foreach (['price', 'services_price'] as $col) {
            $this->safeChange('bundles', $col, fn (Blueprint $t) => $t->decimal($col, 11, 2)->default(0)->change());
        }

        foreach (['discount_price', 'service_price', 'net_amount', 'tax_exclusive_serviceprice', 'tax_percenatage', 'tax_price', 'tax_including_price'] as $col) {
            $this->safeChange('invoice_details', $col, fn (Blueprint $t) => $t->decimal($col, 11, 2)->default(0)->change());
        }

        $this->safeChange('plan_invoices', 'total_price', fn (Blueprint $t) => $t->decimal('total_price', 11, 2)->default(0)->change());
        $this->safeChange('discount_has_locations', 'amount', fn (Blueprint $t) => $t->decimal('amount', 11, 2)->nullable()->change());
        $this->safeChange('permissions', 'commission', fn (Blueprint $t) => $t->decimal('commission', 11, 2)->default(0)->change());
    }

    public function down(): void
    {
        $this->safeChange('users', 'commission', fn (Blueprint $t) => $t->double('commission', 11, 2)->default(0)->change());
        $this->safeChange('services', 'price', fn (Blueprint $t) => $t->double('price', 11, 2)->default(0)->change());
        $this->safeChange('discounts', 'amount', fn (Blueprint $t) => $t->double('amount', 11, 2)->default(0)->change());
        $this->safeChange('packages', 'total_price', fn (Blueprint $t) => $t->double('total_price', 11, 2)->default(0)->change());
        $this->safeChange('packages', 'total_price_bk', fn (Blueprint $t) => $t->double('total_price_bk', 11, 2)->nullable()->change());
        $this->safeChange('package_advances', 'cash_amount', fn (Blueprint $t) => $t->double('cash_amount', 11, 2)->default(0)->change());
        $this->safeChange('invoices', 'total_price', fn (Blueprint $t) => $t->double('total_price', 11, 2)->default(0)->change());

        foreach (['orignal_price', 'price', 'tax_exclusive_price', 'tax_percenatage', 'tax_price', 'tax_including_price'] as $col) {
            $this->safeChange('package_services', $col, fn (Blueprint $t) => $t->double($col, 11, 2)->default(0)->change());
            $this->safeChange('bundle_package_services', $col, fn (Blueprint $t) => $t->double($col, 11, 2)->default(0)->change());
        }
        foreach (['bundle_price', 'bundle_services_price', 'service_price'] as $col) {
            $this->safeChange('bundle_services_price_history', $col, fn (Blueprint $t) => $t->double($col, 11, 2)->default(0)->change());
        }
        foreach (['service_price', 'calculated_price'] as $col) {
            $this->safeChange('bundle_has_services', $col, fn (Blueprint $t) => $t->double($col, 11, 2)->default(0)->change());
        }
        foreach (['discount_price', 'service_price', 'net_amount', 'orignal_price', 'tax_exclusive_net_amount', 'tax_percenatage', 'tax_price', 'tax_including_price'] as $col) {
            $this->safeChange('package_bundles', $col, fn (Blueprint $t) => $t->double($col, 11, 2)->default(0)->change());
        }
        foreach (['price', 'services_price'] as $col) {
            $this->safeChange('bundles', $col, fn (Blueprint $t) => $t->double($col, 11, 2)->default(0)->change());
        }
        foreach (['discount_price', 'service_price', 'net_amount', 'tax_exclusive_serviceprice', 'tax_percenatage', 'tax_price', 'tax_including_price'] as $col) {
            $this->safeChange('invoice_details', $col, fn (Blueprint $t) => $t->double($col, 11, 2)->default(0)->change());
        }

        $this->safeChange('plan_invoices', 'total_price', fn (Blueprint $t) => $t->double('total_price', 11, 2)->default(0)->change());
        $this->safeChange('discount_has_locations', 'amount', fn (Blueprint $t) => $t->double('amount', 11, 2)->nullable()->change());
        $this->safeChange('permissions', 'commission', fn (Blueprint $t) => $t->double('commission', 11, 2)->default(0)->change());
    }
};
