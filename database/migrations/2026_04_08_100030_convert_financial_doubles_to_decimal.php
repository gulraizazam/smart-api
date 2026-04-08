<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convert financial columns from double(11,2) to decimal(11,2).
 *
 * double uses IEEE 754 floating-point which cannot represent values like 0.10 exactly,
 * causing cumulative rounding errors in financial calculations.
 * decimal stores exact fixed-point values — the correct type for money.
 *
 * This is a data-preserving operation: MySQL/MariaDB ALTER MODIFY with same precision
 * retains all existing values. The only change is the storage engine (float → fixed-point).
 */
return new class extends Migration
{
    public function up(): void
    {
        // users table — commission
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('commission', 11, 2)->default(0)->change();
        });

        // services table — price
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('price', 11, 2)->default(0)->change();
        });

        // discounts table — amount
        Schema::table('discounts', function (Blueprint $table) {
            $table->decimal('amount', 11, 2)->default(0)->change();
        });

        // packages table — total_price, total_price_bk
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('total_price', 11, 2)->default(0)->change();
            $table->decimal('total_price_bk', 11, 2)->nullable()->change();
        });

        // package_advances table — cash_amount
        Schema::table('package_advances', function (Blueprint $table) {
            $table->decimal('cash_amount', 11, 2)->default(0)->change();
        });

        // invoices table — total_price
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('total_price', 11, 2)->default(0)->change();
        });

        // package_services table — 6 columns
        Schema::table('package_services', function (Blueprint $table) {
            $table->decimal('orignal_price', 11, 2)->default(0)->change();
            $table->decimal('price', 11, 2)->default(0)->change();
            $table->decimal('tax_exclusive_price', 11, 2)->default(0)->change();
            $table->decimal('tax_percenatage', 11, 2)->default(0)->change();
            $table->decimal('tax_price', 11, 2)->default(0)->change();
            $table->decimal('tax_including_price', 11, 2)->default(0)->change();
        });

        // bundle_package_services table — 6 columns
        Schema::table('bundle_package_services', function (Blueprint $table) {
            $table->decimal('orignal_price', 11, 2)->default(0)->change();
            $table->decimal('price', 11, 2)->default(0)->change();
            $table->decimal('tax_exclusive_price', 11, 2)->default(0)->change();
            $table->decimal('tax_percenatage', 11, 2)->default(0)->change();
            $table->decimal('tax_price', 11, 2)->default(0)->change();
            $table->decimal('tax_including_price', 11, 2)->default(0)->change();
        });

        // bundle_services_price_history table — 3 columns
        Schema::table('bundle_services_price_history', function (Blueprint $table) {
            $table->decimal('bundle_price', 11, 2)->default(0)->change();
            $table->decimal('bundle_services_price', 11, 2)->default(0)->change();
            $table->decimal('service_price', 11, 2)->default(0)->change();
        });

        // bundle_has_services table — 2 columns
        Schema::table('bundle_has_services', function (Blueprint $table) {
            $table->decimal('service_price', 11, 2)->default(0)->change();
            $table->decimal('calculated_price', 11, 2)->default(0)->change();
        });

        // package_bundles table — 8 columns
        Schema::table('package_bundles', function (Blueprint $table) {
            $table->decimal('discount_price', 11, 2)->default(0)->change();
            $table->decimal('service_price', 11, 2)->default(0)->change();
            $table->decimal('net_amount', 11, 2)->default(0)->change();
            $table->decimal('orignal_price', 11, 2)->default(0)->change();
            $table->decimal('tax_exclusive_net_amount', 11, 2)->default(0)->change();
            $table->decimal('tax_percenatage', 11, 2)->default(0)->change();
            $table->decimal('tax_price', 11, 2)->default(0)->change();
            $table->decimal('tax_including_price', 11, 2)->default(0)->change();
        });

        // bundles table — 2 columns
        Schema::table('bundles', function (Blueprint $table) {
            $table->decimal('price', 11, 2)->default(0)->change();
            $table->decimal('services_price', 11, 2)->default(0)->change();
        });

        // invoice_details table — 7 columns
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->decimal('discount_price', 11, 2)->default(0)->change();
            $table->decimal('service_price', 11, 2)->default(0)->change();
            $table->decimal('net_amount', 11, 2)->default(0)->change();
            $table->decimal('tax_exclusive_serviceprice', 11, 2)->default(0)->change();
            $table->decimal('tax_percenatage', 11, 2)->default(0)->change();
            $table->decimal('tax_price', 11, 2)->default(0)->change();
            $table->decimal('tax_including_price', 11, 2)->default(0)->change();
        });

        // plan_invoices table — total_price
        Schema::table('plan_invoices', function (Blueprint $table) {
            $table->decimal('total_price', 11, 2)->default(0)->change();
        });

        // discount_has_locations table — amount
        Schema::table('discount_has_locations', function (Blueprint $table) {
            $table->decimal('amount', 11, 2)->nullable()->change();
        });

        // permissions table — commission
        Schema::table('permissions', function (Blueprint $table) {
            $table->decimal('commission', 11, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        // Revert to double — data-preserving operation
        Schema::table('users', function (Blueprint $table) {
            $table->double('commission', 11, 2)->default(0)->change();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->double('price', 11, 2)->default(0)->change();
        });

        Schema::table('discounts', function (Blueprint $table) {
            $table->double('amount', 11, 2)->default(0)->change();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->double('total_price', 11, 2)->default(0)->change();
            $table->double('total_price_bk', 11, 2)->nullable()->change();
        });

        Schema::table('package_advances', function (Blueprint $table) {
            $table->double('cash_amount', 11, 2)->default(0)->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->double('total_price', 11, 2)->default(0)->change();
        });

        Schema::table('package_services', function (Blueprint $table) {
            $table->double('orignal_price', 11, 2)->default(0)->change();
            $table->double('price', 11, 2)->default(0)->change();
            $table->double('tax_exclusive_price', 11, 2)->default(0)->change();
            $table->double('tax_percenatage', 11, 2)->default(0)->change();
            $table->double('tax_price', 11, 2)->default(0)->change();
            $table->double('tax_including_price', 11, 2)->default(0)->change();
        });

        Schema::table('bundle_package_services', function (Blueprint $table) {
            $table->double('orignal_price', 11, 2)->default(0)->change();
            $table->double('price', 11, 2)->default(0)->change();
            $table->double('tax_exclusive_price', 11, 2)->default(0)->change();
            $table->double('tax_percenatage', 11, 2)->default(0)->change();
            $table->double('tax_price', 11, 2)->default(0)->change();
            $table->double('tax_including_price', 11, 2)->default(0)->change();
        });

        Schema::table('bundle_services_price_history', function (Blueprint $table) {
            $table->double('bundle_price', 11, 2)->default(0)->change();
            $table->double('bundle_services_price', 11, 2)->default(0)->change();
            $table->double('service_price', 11, 2)->default(0)->change();
        });

        Schema::table('bundle_has_services', function (Blueprint $table) {
            $table->double('service_price', 11, 2)->default(0)->change();
            $table->double('calculated_price', 11, 2)->default(0)->change();
        });

        Schema::table('package_bundles', function (Blueprint $table) {
            $table->double('discount_price', 11, 2)->default(0)->change();
            $table->double('service_price', 11, 2)->default(0)->change();
            $table->double('net_amount', 11, 2)->default(0)->change();
            $table->double('orignal_price', 11, 2)->default(0)->change();
            $table->double('tax_exclusive_net_amount', 11, 2)->default(0)->change();
            $table->double('tax_percenatage', 11, 2)->default(0)->change();
            $table->double('tax_price', 11, 2)->default(0)->change();
            $table->double('tax_including_price', 11, 2)->default(0)->change();
        });

        Schema::table('bundles', function (Blueprint $table) {
            $table->double('price', 11, 2)->default(0)->change();
            $table->double('services_price', 11, 2)->default(0)->change();
        });

        Schema::table('invoice_details', function (Blueprint $table) {
            $table->double('discount_price', 11, 2)->default(0)->change();
            $table->double('service_price', 11, 2)->default(0)->change();
            $table->double('net_amount', 11, 2)->default(0)->change();
            $table->double('tax_exclusive_serviceprice', 11, 2)->default(0)->change();
            $table->double('tax_percenatage', 11, 2)->default(0)->change();
            $table->double('tax_price', 11, 2)->default(0)->change();
            $table->double('tax_including_price', 11, 2)->default(0)->change();
        });

        Schema::table('plan_invoices', function (Blueprint $table) {
            $table->double('total_price', 11, 2)->default(0)->change();
        });

        Schema::table('discount_has_locations', function (Blueprint $table) {
            $table->double('amount', 11, 2)->nullable()->change();
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->double('commission', 11, 2)->default(0)->change();
        });
    }
};
