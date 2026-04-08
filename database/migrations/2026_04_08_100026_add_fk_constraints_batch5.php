<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix remaining type mismatches and add FK constraints to uncovered tables.
     *
     * Type fixes (bigint(20) → int(10) unsigned, to match parent PK):
     *   orders.location_id, orders.payment_mode, orders.updated_by
     *   memberships.patient_id, memberships.created_by, memberships.updated_by, memberships.deleted_by
     *   inventories.location_id
     *
     * Orphan cleanup:
     *   leads_services.lead_id: 628 orphans (NOT NULL) → DELETE
     *
     * FK constraints added: users(2), leads_services(4), sms_logs(3), orders(4),
     *   order_details(3), memberships(3), inventories(3), stocks(3), appointment_comments(2)
     */
    public function up(): void
    {
        // ─── Step 1: Fix type mismatches ───
        DB::statement('ALTER TABLE orders MODIFY location_id INT(10) UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE orders MODIFY payment_mode INT(10) UNSIGNED NULL');
        DB::statement('ALTER TABLE orders MODIFY updated_by INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE memberships MODIFY patient_id INT(10) UNSIGNED NULL');
        DB::statement('ALTER TABLE memberships MODIFY created_by INT(10) UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE memberships MODIFY updated_by INT(10) UNSIGNED NULL');
        DB::statement('ALTER TABLE memberships MODIFY deleted_by INT(10) UNSIGNED NULL');

        DB::statement('ALTER TABLE inventories MODIFY location_id INT(10) UNSIGNED NULL');

        // ─── Step 2: Clean orphans ───
        DB::statement('DELETE FROM leads_services WHERE lead_id NOT IN (SELECT id FROM leads)');

        // ─── Step 3: Add FK constraints ───

        // users
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('account_id', 'fk_users_account')
                ->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('created_by', 'fk_users_creator')
                ->references('id')->on('users')->onDelete('set null');
        });

        // leads_services (600K rows — most critical)
        Schema::table('leads_services', function (Blueprint $table) {
            $table->foreign('lead_id', 'fk_ls_lead')
                ->references('id')->on('leads')->onDelete('cascade');
            $table->foreign('service_id', 'fk_ls_service')
                ->references('id')->on('services')->onDelete('cascade');
            $table->foreign('lead_status_id', 'fk_ls_status')
                ->references('id')->on('lead_statuses')->onDelete('set null');
            $table->foreign('child_service_id', 'fk_ls_child_service')
                ->references('id')->on('services')->onDelete('set null');
        });

        // sms_logs (remaining 3)
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->foreign('lead_id', 'fk_sms_lead')
                ->references('id')->on('leads')->onDelete('set null');
            $table->foreign('invoice_id', 'fk_sms_invoice')
                ->references('id')->on('invoices')->onDelete('set null');
            $table->foreign('package_id', 'fk_sms_package')
                ->references('id')->on('packages')->onDelete('set null');
        });

        // orders
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('account_id', 'fk_orders_account')
                ->references('id')->on('accounts')->onDelete('restrict');
            $table->foreign('patient_id', 'fk_orders_patient')
                ->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'fk_orders_creator')
                ->references('id')->on('users')->onDelete('restrict');
            $table->foreign('location_id', 'fk_orders_location')
                ->references('id')->on('locations')->onDelete('restrict');
        });

        // order_details
        Schema::table('order_details', function (Blueprint $table) {
            $table->foreign('order_id', 'fk_od_order')
                ->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('product_id', 'fk_od_product')
                ->references('id')->on('products')->onDelete('cascade');
            $table->foreign('account_id', 'fk_od_account')
                ->references('id')->on('accounts')->onDelete('restrict');
        });

        // memberships
        Schema::table('memberships', function (Blueprint $table) {
            $table->foreign('membership_type_id', 'fk_mem_type')
                ->references('id')->on('membership_types')->onDelete('restrict');
            $table->foreign('patient_id', 'fk_mem_patient')
                ->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'fk_mem_creator')
                ->references('id')->on('users')->onDelete('restrict');
        });

        // inventories
        Schema::table('inventories', function (Blueprint $table) {
            $table->foreign('product_id', 'fk_inv_product')
                ->references('id')->on('products')->onDelete('cascade');
            $table->foreign('warehouse_id', 'fk_inv_warehouse')
                ->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('location_id', 'fk_inv_location')
                ->references('id')->on('locations')->onDelete('set null');
        });

        // stocks
        Schema::table('stocks', function (Blueprint $table) {
            $table->foreign('account_id', 'fk_stocks_account')
                ->references('id')->on('accounts')->onDelete('restrict');
            $table->foreign('product_id', 'fk_stocks_product')
                ->references('id')->on('products')->onDelete('cascade');
            $table->foreign('order_id', 'fk_stocks_order')
                ->references('id')->on('orders')->onDelete('set null');
        });

        // appointment_comments
        Schema::table('appointment_comments', function (Blueprint $table) {
            $table->foreign('appointment_id', 'fk_ac_appointment')
                ->references('id')->on('appointments')->onDelete('cascade');
            $table->foreign('created_by', 'fk_ac_creator')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('fk_users_account');
            $table->dropForeign('fk_users_creator');
        });

        Schema::table('leads_services', function (Blueprint $table) {
            $table->dropForeign('fk_ls_lead');
            $table->dropForeign('fk_ls_service');
            $table->dropForeign('fk_ls_status');
            $table->dropForeign('fk_ls_child_service');
        });

        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropForeign('fk_sms_lead');
            $table->dropForeign('fk_sms_invoice');
            $table->dropForeign('fk_sms_package');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign('fk_orders_account');
            $table->dropForeign('fk_orders_patient');
            $table->dropForeign('fk_orders_creator');
            $table->dropForeign('fk_orders_location');
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->dropForeign('fk_od_order');
            $table->dropForeign('fk_od_product');
            $table->dropForeign('fk_od_account');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropForeign('fk_mem_type');
            $table->dropForeign('fk_mem_patient');
            $table->dropForeign('fk_mem_creator');
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropForeign('fk_inv_product');
            $table->dropForeign('fk_inv_warehouse');
            $table->dropForeign('fk_inv_location');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign('fk_stocks_account');
            $table->dropForeign('fk_stocks_product');
            $table->dropForeign('fk_stocks_order');
        });

        Schema::table('appointment_comments', function (Blueprint $table) {
            $table->dropForeign('fk_ac_appointment');
            $table->dropForeign('fk_ac_creator');
        });

        // Revert type fixes
        DB::statement('ALTER TABLE orders MODIFY location_id BIGINT(20) UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE orders MODIFY payment_mode BIGINT(20) UNSIGNED NULL');
        DB::statement('ALTER TABLE orders MODIFY updated_by BIGINT(20) UNSIGNED NULL');
        DB::statement('ALTER TABLE memberships MODIFY patient_id BIGINT(20) UNSIGNED NULL');
        DB::statement('ALTER TABLE memberships MODIFY created_by BIGINT(20) UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE memberships MODIFY updated_by BIGINT(20) UNSIGNED NULL');
        DB::statement('ALTER TABLE memberships MODIFY deleted_by BIGINT(20) UNSIGNED NULL');
        DB::statement('ALTER TABLE inventories MODIFY location_id BIGINT(20) UNSIGNED NULL');
    }
};
