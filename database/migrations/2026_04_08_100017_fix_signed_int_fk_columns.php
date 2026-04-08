<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert signed INT/BIGINT FK columns to unsigned to match parent table PKs.
     *
     * Type mismatch prevents adding FK constraints and wastes sign bit.
     * No negative values exist in any of these columns.
     *
     * Three groups:
     *   1. int(11) → int(10) unsigned  (parent PK is int(10) unsigned)
     *   2. int(11) → bigint(20) unsigned  (parent PK is bigint(20) unsigned)
     *   3. bigint(20) signed → bigint(20) unsigned  (parent PK is bigint(20) unsigned)
     */
    public function up(): void
    {
        // ─── Group 1: int(11) → int(10) unsigned ───
        // Parent tables with int(10) unsigned PK: leads, services, discounts, roles,
        // appointments, packages, locations, users

        DB::statement('ALTER TABLE activities MODIFY lead_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE base_discount_services MODIFY service_id INT(10) UNSIGNED NULL');
        DB::statement('ALTER TABLE base_discount_services MODIFY discount_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE discount_role MODIFY discount_id INT(10) UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE discount_role MODIFY role_id INT(10) UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE feedback MODIFY appointment_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE get_discount_services MODIFY service_id INT(10) UNSIGNED NULL');
        DB::statement('ALTER TABLE get_discount_services MODIFY discount_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE invoices MODIFY package_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE leads MODIFY child_service_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE package_bundles MODIFY base_service_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE package_services MODIFY base_service_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE package_vouchers MODIFY package_id INT(10) UNSIGNED NULL');
        DB::statement('ALTER TABLE package_vouchers MODIFY service_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE plan_invoices MODIFY location_id INT(10) UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE stocks MODIFY location_id INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE user_has_warehouses MODIFY user_id INT(10) UNSIGNED NOT NULL');

        // ─── Group 2: int(11) → bigint(20) unsigned ───
        // Parent tables with bigint(20) unsigned PK: vouchers, products, warehouses

        DB::statement('ALTER TABLE package_vouchers MODIFY voucher_id BIGINT(20) UNSIGNED NULL');

        DB::statement('ALTER TABLE product_details MODIFY product_id BIGINT(20) UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE user_has_warehouses MODIFY warehouse_id BIGINT(20) UNSIGNED NOT NULL');

        // ─── Group 3: bigint(20) signed → bigint(20) unsigned ───
        // Parent tables with bigint(20) unsigned PK: memberships, membership_types

        DB::statement('ALTER TABLE discounts MODIFY customer_type_id BIGINT(20) UNSIGNED NULL');

        DB::statement('ALTER TABLE membership_types MODIFY parent_id BIGINT(20) UNSIGNED NULL');

        DB::statement('ALTER TABLE package_bundles MODIFY membership_code_id BIGINT(20) UNSIGNED NULL');
        DB::statement('ALTER TABLE package_bundles MODIFY membership_type_id BIGINT(20) UNSIGNED NULL');
    }

    public function down(): void
    {
        // Revert Group 1: back to int(11)
        DB::statement('ALTER TABLE activities MODIFY lead_id INT(11) NULL');
        DB::statement('ALTER TABLE base_discount_services MODIFY service_id INT(11) NULL');
        DB::statement('ALTER TABLE base_discount_services MODIFY discount_id INT(11) NULL');
        DB::statement('ALTER TABLE discount_role MODIFY discount_id INT(11) NOT NULL');
        DB::statement('ALTER TABLE discount_role MODIFY role_id INT(11) NOT NULL');
        DB::statement('ALTER TABLE feedback MODIFY appointment_id INT(11) NULL');
        DB::statement('ALTER TABLE get_discount_services MODIFY service_id INT(11) NULL');
        DB::statement('ALTER TABLE get_discount_services MODIFY discount_id INT(11) NULL');
        DB::statement('ALTER TABLE invoices MODIFY package_id INT(11) NULL');
        DB::statement('ALTER TABLE leads MODIFY child_service_id INT(11) NULL');
        DB::statement('ALTER TABLE package_bundles MODIFY base_service_id INT(11) NULL');
        DB::statement('ALTER TABLE package_services MODIFY base_service_id INT(11) NULL');
        DB::statement('ALTER TABLE package_vouchers MODIFY package_id INT(11) NULL');
        DB::statement('ALTER TABLE package_vouchers MODIFY service_id INT(11) NULL');
        DB::statement('ALTER TABLE plan_invoices MODIFY location_id INT(11) NOT NULL');
        DB::statement('ALTER TABLE stocks MODIFY location_id INT(11) NULL');
        DB::statement('ALTER TABLE user_has_warehouses MODIFY user_id INT(11) NOT NULL');

        // Revert Group 2: back to int(11)
        DB::statement('ALTER TABLE package_vouchers MODIFY voucher_id INT(11) NULL');
        DB::statement('ALTER TABLE product_details MODIFY product_id INT(11) NOT NULL');
        DB::statement('ALTER TABLE user_has_warehouses MODIFY warehouse_id INT(11) NOT NULL');

        // Revert Group 3: back to bigint(20) signed
        DB::statement('ALTER TABLE discounts MODIFY customer_type_id BIGINT(20) NULL');
        DB::statement('ALTER TABLE membership_types MODIFY parent_id BIGINT(20) NULL');
        DB::statement('ALTER TABLE package_bundles MODIFY membership_code_id BIGINT(20) NULL');
        DB::statement('ALTER TABLE package_bundles MODIFY membership_type_id BIGINT(20) NULL');
    }
};
