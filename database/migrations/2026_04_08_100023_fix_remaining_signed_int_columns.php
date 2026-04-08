<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert remaining 34 signed INT FK columns + 1 signed PK to unsigned.
     *
     * Also fixes product_details.id PK: int(11) → int(10) unsigned (no FK refs).
     *
     * No negative values exist in any column. No FK constraints to drop.
     */
    public function up(): void
    {
        // ─── Fix product_details PK first (stocks/transfer_products reference it) ───
        DB::statement('ALTER TABLE product_details MODIFY id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT');

        // ─── Group 1: int(11) → int(10) unsigned (parent PK is int(10) unsigned) ───
        DB::statement('ALTER TABLE activities MODIFY account_id INT(10) UNSIGNED NULL');
        DB::statement('ALTER TABLE activities MODIFY invoice_id INT(10) UNSIGNED NULL');
        DB::statement('ALTER TABLE activities MODIFY lead_status_id INT(10) UNSIGNED NULL');
        DB::statement('ALTER TABLE activities MODIFY package_id INT(10) UNSIGNED NULL');
        DB::statement('ALTER TABLE activities MODIFY planId INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE announcements MODIFY user_id INT(10) UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE appointments MODIFY deleted_by INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE base_discount_services MODIFY bundle_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE feedback MODIFY treatment_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE get_discount_services MODIFY base_service_id INT(10) UNSIGNED NULL');
        DB::statement('ALTER TABLE get_discount_services MODIFY bundle_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE leads_services MODIFY lead_status_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE notifications MODIFY user_id INT(10) UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE orders MODIFY created_by INT(10) UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE orders MODIFY employee_id INT(10) UNSIGNED NULL');
        DB::statement('ALTER TABLE orders MODIFY patient_id INT(10) UNSIGNED NULL');
        DB::statement('ALTER TABLE orders MODIFY prescribed_by INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE package_services MODIFY sold_by INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE package_vouchers MODIFY main_service_id INT(10) UNSIGNED NULL');
        DB::statement('ALTER TABLE package_vouchers MODIFY user_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE patient_notes MODIFY created_by INT(10) UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE patient_notes MODIFY patient_id INT(10) UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE plan_invoices MODIFY created_by INT(10) UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE plan_invoices MODIFY package_advance_id INT(10) UNSIGNED NULL');
        DB::statement('ALTER TABLE plan_invoices MODIFY package_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE products MODIFY location_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE product_details MODIFY account_id INT(10) UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE stocks MODIFY product_detail_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE transfer_products MODIFY product_detail_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE users MODIFY created_by INT(10) UNSIGNED NULL');

        // ─── Group 2: int(11) → bigint(20) unsigned (parent PK is bigint(20) unsigned) ───
        DB::statement('ALTER TABLE orders MODIFY refund_order_id BIGINT(20) UNSIGNED NULL');
        DB::statement('ALTER TABLE order_details MODIFY inventory_id BIGINT(20) UNSIGNED NULL');
        DB::statement('ALTER TABLE stocks MODIFY order_id BIGINT(20) UNSIGNED NULL');
        DB::statement('ALTER TABLE transfer_products MODIFY child_product_id BIGINT(20) UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE product_details MODIFY id INT(11) NOT NULL AUTO_INCREMENT');

        $revertInt11Null = [
            'activities' => ['account_id','invoice_id','lead_status_id','package_id','planId'],
            'appointments' => ['deleted_by'],
            'base_discount_services' => ['bundle_id'],
            'feedback' => ['treatment_id'],
            'get_discount_services' => ['base_service_id','bundle_id'],
            'leads_services' => ['lead_status_id'],
            'orders' => ['employee_id','patient_id','prescribed_by'],
            'package_services' => ['sold_by'],
            'package_vouchers' => ['main_service_id','user_id'],
            'plan_invoices' => ['package_advance_id','package_id'],
            'products' => ['location_id'],
            'stocks' => ['product_detail_id'],
            'transfer_products' => ['product_detail_id'],
            'users' => ['created_by'],
        ];
        foreach ($revertInt11Null as $tbl => $cols) {
            foreach ($cols as $col) {
                DB::statement("ALTER TABLE $tbl MODIFY $col INT(11) NULL");
            }
        }

        $revertInt11NotNull = [
            'announcements' => ['user_id'],
            'notifications' => ['user_id'],
            'orders' => ['created_by'],
            'patient_notes' => ['created_by','patient_id'],
            'plan_invoices' => ['created_by'],
            'product_details' => ['account_id'],
        ];
        foreach ($revertInt11NotNull as $tbl => $cols) {
            foreach ($cols as $col) {
                DB::statement("ALTER TABLE $tbl MODIFY $col INT(11) NOT NULL");
            }
        }

        DB::statement('ALTER TABLE orders MODIFY refund_order_id INT(11) NULL');
        DB::statement('ALTER TABLE order_details MODIFY inventory_id INT(11) NULL');
        DB::statement('ALTER TABLE stocks MODIFY order_id INT(11) NULL');
        DB::statement('ALTER TABLE transfer_products MODIFY child_product_id INT(11) NOT NULL');
    }
};
