<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert all 43 double columns to decimal for financial precision.
     * IEEE 754 double cannot represent 0.1 exactly — decimal avoids rounding.
     */
    public function up(): void
    {
        // bundles (2 cols)
        DB::statement('ALTER TABLE bundles
            MODIFY price DECIMAL(11,2) NOT NULL DEFAULT 0.00,
            MODIFY services_price DECIMAL(11,2) NOT NULL DEFAULT 0.00');

        // bundle_has_services (2 cols)
        DB::statement('ALTER TABLE bundle_has_services
            MODIFY service_price DECIMAL(11,2) NOT NULL DEFAULT 0.00,
            MODIFY calculated_price DECIMAL(11,2) NOT NULL DEFAULT 0.00');

        // bundle_services_price_history (3 cols)
        DB::statement('ALTER TABLE bundle_services_price_history
            MODIFY bundle_price DECIMAL(11,2) DEFAULT NULL,
            MODIFY bundle_services_price DECIMAL(11,2) DEFAULT NULL,
            MODIFY service_price DECIMAL(11,2) DEFAULT NULL');

        // discounts (1 col)
        DB::statement('ALTER TABLE discounts MODIFY amount DECIMAL(11,2) NOT NULL');

        // invoices (1 col) — 245K rows
        DB::statement('ALTER TABLE invoices MODIFY total_price DECIMAL(11,2) NOT NULL');

        // invoice_details (7 cols) — 244K rows
        DB::statement('ALTER TABLE invoice_details
            MODIFY discount_price DECIMAL(11,2) DEFAULT NULL,
            MODIFY service_price DECIMAL(11,2) NOT NULL,
            MODIFY tax_exclusive_serviceprice DECIMAL(11,2) NOT NULL DEFAULT 0.00,
            MODIFY tax_percenatage DECIMAL(11,2) NOT NULL DEFAULT 0.00,
            MODIFY tax_price DECIMAL(11,2) NOT NULL DEFAULT 0.00,
            MODIFY tax_including_price DECIMAL(11,2) NOT NULL DEFAULT 0.00,
            MODIFY net_amount DECIMAL(11,2) NOT NULL');

        // orders (1 col)
        DB::statement('ALTER TABLE orders MODIFY total_price DECIMAL(8,2) DEFAULT NULL');

        // order_details (3 cols)
        DB::statement('ALTER TABLE order_details
            MODIFY sale_price DECIMAL(8,2) NOT NULL,
            MODIFY discount_price DECIMAL(8,2) DEFAULT NULL,
            MODIFY sale_price_after_discount DECIMAL(8,2) NOT NULL');

        // order_refunds (1 col)
        DB::statement('ALTER TABLE order_refunds MODIFY total_price DECIMAL(8,2) NOT NULL');

        // packages (2 cols) — 46K rows
        DB::statement('ALTER TABLE packages
            MODIFY total_price DECIMAL(11,2) NOT NULL,
            MODIFY total_price_bk DECIMAL(11,2) DEFAULT 0.00');

        // package_advances (1 col) — 827K rows
        DB::statement('ALTER TABLE package_advances MODIFY cash_amount DECIMAL(11,2) NOT NULL DEFAULT 0.00');

        // package_bundles (8 cols) — 137K rows
        DB::statement('ALTER TABLE package_bundles
            MODIFY discount_price DECIMAL(11,2) DEFAULT NULL,
            MODIFY service_price DECIMAL(11,2) NOT NULL,
            MODIFY net_amount DECIMAL(11,2) NOT NULL,
            MODIFY tax_exclusive_net_amount DECIMAL(11,2) NOT NULL DEFAULT 0.00,
            MODIFY tax_percenatage DECIMAL(11,2) NOT NULL DEFAULT 0.00,
            MODIFY tax_price DECIMAL(11,2) NOT NULL DEFAULT 0.00,
            MODIFY tax_including_price DECIMAL(11,2) NOT NULL DEFAULT 0.00,
            MODIFY orignal_price DECIMAL(11,2) NOT NULL DEFAULT 0.00');

        // package_services (6 cols) — 220K rows
        DB::statement('ALTER TABLE package_services
            MODIFY orignal_price DECIMAL(11,2) NOT NULL DEFAULT 0.00,
            MODIFY price DECIMAL(11,2) NOT NULL DEFAULT 0.00,
            MODIFY tax_exclusive_price DECIMAL(11,2) NOT NULL DEFAULT 0.00,
            MODIFY tax_percenatage DECIMAL(11,2) NOT NULL DEFAULT 0.00,
            MODIFY tax_price DECIMAL(11,2) NOT NULL DEFAULT 0.00,
            MODIFY tax_including_price DECIMAL(11,2) NOT NULL DEFAULT 0.00');

        // plan_invoices (1 col) — 52K rows
        DB::statement('ALTER TABLE plan_invoices MODIFY total_price DECIMAL(11,2) NOT NULL');

        // products (1 col)
        DB::statement('ALTER TABLE products MODIFY sale_price DECIMAL(8,2) NOT NULL');

        // roles (1 col)
        DB::statement('ALTER TABLE roles MODIFY commission DECIMAL(11,2) NOT NULL');

        // services (1 col)
        DB::statement('ALTER TABLE services MODIFY price DECIMAL(11,2) NOT NULL DEFAULT 0.00');

        // users (1 col) — 213K rows
        DB::statement('ALTER TABLE users MODIFY commission DECIMAL(11,2) NOT NULL DEFAULT 0.00');
    }

    public function down(): void
    {
        // Reverse: decimal back to double (not recommended)
        $tables = [
            'bundles' => ['price DOUBLE(11,2) NOT NULL DEFAULT 0.00', 'services_price DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            'bundle_has_services' => ['service_price DOUBLE(11,2) NOT NULL DEFAULT 0.00', 'calculated_price DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            'bundle_services_price_history' => ['bundle_price DOUBLE(11,2) DEFAULT NULL', 'bundle_services_price DOUBLE(11,2) DEFAULT NULL', 'service_price DOUBLE(11,2) DEFAULT NULL'],
            'discounts' => ['amount DOUBLE(11,2) NOT NULL'],
            'invoices' => ['total_price DOUBLE(11,2) NOT NULL'],
            'invoice_details' => ['discount_price DOUBLE(11,2) DEFAULT NULL', 'service_price DOUBLE(11,2) NOT NULL', 'tax_exclusive_serviceprice DOUBLE(11,2) NOT NULL DEFAULT 0.00', 'tax_percenatage DOUBLE(11,2) NOT NULL DEFAULT 0.00', 'tax_price DOUBLE(11,2) NOT NULL DEFAULT 0.00', 'tax_including_price DOUBLE(11,2) NOT NULL DEFAULT 0.00', 'net_amount DOUBLE(11,2) NOT NULL'],
            'orders' => ['total_price DOUBLE(8,2) DEFAULT NULL'],
            'order_details' => ['sale_price DOUBLE(8,2) NOT NULL', 'discount_price DOUBLE(8,2) DEFAULT NULL', 'sale_price_after_discount DOUBLE(8,2) NOT NULL'],
            'order_refunds' => ['total_price DOUBLE(8,2) NOT NULL'],
            'packages' => ['total_price DOUBLE(11,2) NOT NULL', 'total_price_bk DOUBLE(11,2) DEFAULT 0.00'],
            'package_advances' => ['cash_amount DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            'package_bundles' => ['discount_price DOUBLE(11,2) DEFAULT NULL', 'service_price DOUBLE(11,2) NOT NULL', 'net_amount DOUBLE(11,2) NOT NULL', 'tax_exclusive_net_amount DOUBLE(11,2) NOT NULL DEFAULT 0.00', 'tax_percenatage DOUBLE(11,2) NOT NULL DEFAULT 0.00', 'tax_price DOUBLE(11,2) NOT NULL DEFAULT 0.00', 'tax_including_price DOUBLE(11,2) NOT NULL DEFAULT 0.00', 'orignal_price DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            'package_services' => ['orignal_price DOUBLE(11,2) NOT NULL DEFAULT 0.00', 'price DOUBLE(11,2) NOT NULL DEFAULT 0.00', 'tax_exclusive_price DOUBLE(11,2) NOT NULL DEFAULT 0.00', 'tax_percenatage DOUBLE(11,2) NOT NULL DEFAULT 0.00', 'tax_price DOUBLE(11,2) NOT NULL DEFAULT 0.00', 'tax_including_price DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            'plan_invoices' => ['total_price DOUBLE(11,2) NOT NULL'],
            'products' => ['sale_price DOUBLE(8,2) NOT NULL'],
            'roles' => ['commission DOUBLE(11,2) NOT NULL'],
            'services' => ['price DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
            'users' => ['commission DOUBLE(11,2) NOT NULL DEFAULT 0.00'],
        ];

        foreach ($tables as $table => $columns) {
            $mods = implode(', ', array_map(fn($c) => "MODIFY $c", $columns));
            DB::statement("ALTER TABLE $table $mods");
        }
    }
};
