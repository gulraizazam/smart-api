<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add FK constraints to tables that were fixed with unsigned types.
     *
     * Orphan cleanup:
     *   package_vouchers.service_id: 21 orphans → SET NULL
     *   package_vouchers.voucher_id: 21 orphans (voucher 116) → SET NULL
     *   leads.child_service_id: 12,767 rows with value 0 → SET NULL
     *   package_bundles.membership_code_id: 1 orphan (id 3331) → SET NULL
     *
     * Tables receiving FKs: activities, base_discount_services, discount_role,
     *   feedback, get_discount_services, invoices, leads, package_bundles,
     *   package_services, package_vouchers, plan_invoices, product_details,
     *   stocks, user_has_warehouses, discounts, membership_types
     */
    public function up(): void
    {
        // ─── Step 1: Clean orphans ───
        DB::statement('UPDATE package_vouchers SET service_id = NULL WHERE service_id IS NOT NULL AND service_id NOT IN (SELECT id FROM services)');
        DB::statement('UPDATE package_vouchers SET voucher_id = NULL WHERE voucher_id IS NOT NULL AND voucher_id NOT IN (SELECT id FROM vouchers)');
        DB::statement('UPDATE leads SET child_service_id = NULL WHERE child_service_id = 0 OR (child_service_id IS NOT NULL AND child_service_id NOT IN (SELECT id FROM services))');
        DB::statement('UPDATE package_bundles SET membership_code_id = NULL WHERE membership_code_id IS NOT NULL AND membership_code_id NOT IN (SELECT id FROM memberships)');

        // ─── Step 2: Add FK constraints ───

        // activities (0 FKs → adding 1)
        Schema::table('activities', function (Blueprint $table) {
            $table->foreign('lead_id', 'fk_activities_lead')
                ->references('id')->on('leads')
                ->onDelete('set null');
        });

        // base_discount_services (0 FKs → adding 2)
        Schema::table('base_discount_services', function (Blueprint $table) {
            $table->foreign('service_id', 'fk_base_disc_svc_service')
                ->references('id')->on('services')
                ->onDelete('set null');
            $table->foreign('discount_id', 'fk_base_disc_svc_discount')
                ->references('id')->on('discounts')
                ->onDelete('set null');
        });

        // discount_role (0 FKs → adding 2, NOT NULL → CASCADE)
        Schema::table('discount_role', function (Blueprint $table) {
            $table->foreign('discount_id', 'fk_disc_role_discount')
                ->references('id')->on('discounts')
                ->onDelete('cascade');
            $table->foreign('role_id', 'fk_disc_role_role')
                ->references('id')->on('roles')
                ->onDelete('cascade');
        });

        // feedback (0 FKs → adding 1)
        Schema::table('feedback', function (Blueprint $table) {
            $table->foreign('appointment_id', 'fk_feedback_appointment')
                ->references('id')->on('appointments')
                ->onDelete('set null');
        });

        // get_discount_services (0 FKs → adding 2)
        Schema::table('get_discount_services', function (Blueprint $table) {
            $table->foreign('service_id', 'fk_get_disc_svc_service')
                ->references('id')->on('services')
                ->onDelete('set null');
            $table->foreign('discount_id', 'fk_get_disc_svc_discount')
                ->references('id')->on('discounts')
                ->onDelete('set null');
        });

        // invoices.package_id (adding 1 more FK)
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('package_id', 'fk_invoices_package')
                ->references('id')->on('packages')
                ->onDelete('set null');
        });

        // leads.child_service_id (adding 1 more FK)
        Schema::table('leads', function (Blueprint $table) {
            $table->foreign('child_service_id', 'fk_leads_child_service')
                ->references('id')->on('services')
                ->onDelete('set null');
        });

        // package_bundles (adding 3 more FKs)
        Schema::table('package_bundles', function (Blueprint $table) {
            $table->foreign('base_service_id', 'fk_pkg_bundles_base_svc')
                ->references('id')->on('services')
                ->onDelete('set null');
            $table->foreign('membership_code_id', 'fk_pkg_bundles_membership')
                ->references('id')->on('memberships')
                ->onDelete('set null');
            $table->foreign('membership_type_id', 'fk_pkg_bundles_memtype')
                ->references('id')->on('membership_types')
                ->onDelete('set null');
        });

        // package_services (adding 1 more FK)
        Schema::table('package_services', function (Blueprint $table) {
            $table->foreign('base_service_id', 'fk_pkg_svc_base_service')
                ->references('id')->on('services')
                ->onDelete('set null');
        });

        // package_vouchers (0 FKs → adding 3)
        Schema::table('package_vouchers', function (Blueprint $table) {
            $table->foreign('package_id', 'fk_pkg_vouchers_package')
                ->references('id')->on('packages')
                ->onDelete('set null');
            $table->foreign('service_id', 'fk_pkg_vouchers_service')
                ->references('id')->on('services')
                ->onDelete('set null');
            $table->foreign('voucher_id', 'fk_pkg_vouchers_voucher')
                ->references('id')->on('vouchers')
                ->onDelete('set null');
        });

        // plan_invoices.location_id (NOT NULL → RESTRICT)
        Schema::table('plan_invoices', function (Blueprint $table) {
            $table->foreign('location_id', 'fk_plan_inv_location')
                ->references('id')->on('locations')
                ->onDelete('restrict');
        });

        // product_details.product_id (NOT NULL → CASCADE)
        Schema::table('product_details', function (Blueprint $table) {
            $table->foreign('product_id', 'fk_prod_details_product')
                ->references('id')->on('products')
                ->onDelete('cascade');
        });

        // stocks.location_id
        Schema::table('stocks', function (Blueprint $table) {
            $table->foreign('location_id', 'fk_stocks_location')
                ->references('id')->on('locations')
                ->onDelete('set null');
        });

        // user_has_warehouses (NOT NULL → CASCADE, pivot table)
        Schema::table('user_has_warehouses', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_user_wh_user')
                ->references('id')->on('users')
                ->onDelete('cascade');
            $table->foreign('warehouse_id', 'fk_user_wh_warehouse')
                ->references('id')->on('warehouses')
                ->onDelete('cascade');
        });

        // discounts.customer_type_id
        Schema::table('discounts', function (Blueprint $table) {
            $table->foreign('customer_type_id', 'fk_discounts_custtype')
                ->references('id')->on('membership_types')
                ->onDelete('set null');
        });

        // membership_types.parent_id (self-referencing)
        Schema::table('membership_types', function (Blueprint $table) {
            $table->foreign('parent_id', 'fk_memtypes_parent')
                ->references('id')->on('membership_types')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('activities', fn (Blueprint $table) => $table->dropForeign('fk_activities_lead'));

        Schema::table('base_discount_services', function (Blueprint $table) {
            $table->dropForeign('fk_base_disc_svc_service');
            $table->dropForeign('fk_base_disc_svc_discount');
        });

        Schema::table('discount_role', function (Blueprint $table) {
            $table->dropForeign('fk_disc_role_discount');
            $table->dropForeign('fk_disc_role_role');
        });

        Schema::table('feedback', fn (Blueprint $table) => $table->dropForeign('fk_feedback_appointment'));

        Schema::table('get_discount_services', function (Blueprint $table) {
            $table->dropForeign('fk_get_disc_svc_service');
            $table->dropForeign('fk_get_disc_svc_discount');
        });

        Schema::table('invoices', fn (Blueprint $table) => $table->dropForeign('fk_invoices_package'));
        Schema::table('leads', fn (Blueprint $table) => $table->dropForeign('fk_leads_child_service'));

        Schema::table('package_bundles', function (Blueprint $table) {
            $table->dropForeign('fk_pkg_bundles_base_svc');
            $table->dropForeign('fk_pkg_bundles_membership');
            $table->dropForeign('fk_pkg_bundles_memtype');
        });

        Schema::table('package_services', fn (Blueprint $table) => $table->dropForeign('fk_pkg_svc_base_service'));

        Schema::table('package_vouchers', function (Blueprint $table) {
            $table->dropForeign('fk_pkg_vouchers_package');
            $table->dropForeign('fk_pkg_vouchers_service');
            $table->dropForeign('fk_pkg_vouchers_voucher');
        });

        Schema::table('plan_invoices', fn (Blueprint $table) => $table->dropForeign('fk_plan_inv_location'));
        Schema::table('product_details', fn (Blueprint $table) => $table->dropForeign('fk_prod_details_product'));
        Schema::table('stocks', fn (Blueprint $table) => $table->dropForeign('fk_stocks_location'));

        Schema::table('user_has_warehouses', function (Blueprint $table) {
            $table->dropForeign('fk_user_wh_user');
            $table->dropForeign('fk_user_wh_warehouse');
        });

        Schema::table('discounts', fn (Blueprint $table) => $table->dropForeign('fk_discounts_custtype'));
        Schema::table('membership_types', fn (Blueprint $table) => $table->dropForeign('fk_memtypes_parent'));
    }
};
