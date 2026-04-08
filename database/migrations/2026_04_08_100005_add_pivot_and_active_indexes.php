<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1. Add composite indexes on high-row-count pivot/junction tables.
     * 2. Add active column indexes on tables where active is frequently filtered.
     *
     * Pivot tables get (fk1, fk2) composites for efficient lookups on both directions.
     * Only tables with >500 rows get pivot composites — smaller tables don't benefit.
     */
    public function up(): void
    {
        // ─── Pivot table composites ───

        // leads_services (595K rows) — most queried pivot in the system
        Schema::table('leads_services', function (Blueprint $table) {
            $table->index(['lead_id', 'service_id'], 'idx_leads_services_lead_service');
        });

        // package_services (221K rows)
        Schema::table('package_services', function (Blueprint $table) {
            $table->index(['package_id', 'service_id'], 'idx_package_services_pkg_svc');
        });

        // package_bundles (139K rows) — already has separate indexes, add composite
        Schema::table('package_bundles', function (Blueprint $table) {
            $table->index(['package_id', 'bundle_id'], 'idx_package_bundles_pkg_bundle');
        });

        // bundle_has_services (1K rows) — small but frequently queried
        Schema::table('bundle_has_services', function (Blueprint $table) {
            $table->index(['bundle_id', 'service_id'], 'idx_bundle_has_services_composite');
        });

        // doctor_has_locations (1K rows) — queried for doctor schedule lookups
        Schema::table('doctor_has_locations', function (Blueprint $table) {
            $table->index(['user_id', 'location_id'], 'idx_doctor_has_locations_composite');
        });

        // discount_has_locations (394 rows)
        Schema::table('discount_has_locations', function (Blueprint $table) {
            $table->index(['discount_id', 'location_id'], 'idx_discount_has_locations_composite');
        });

        // resource_has_rota (544 rows) — queried for schedule building
        Schema::table('resource_has_rota', function (Blueprint $table) {
            $table->index(['resource_id', 'location_id'], 'idx_resource_has_rota_res_loc');
        });

        // ─── Active column indexes on large tables ───
        // Only adding where active is frequently used in WHERE clauses
        // and table is large enough to benefit (>10K rows)

        // services (314 rows) — queried with active=1 in nearly every page load
        // Small table but VERY frequently queried — compound with account_id
        Schema::table('services', function (Blueprint $table) {
            $table->index(['account_id', 'active', 'end_node'], 'idx_services_account_active_endnode');
        });

        // resources (388 rows) — frequently filtered by active
        Schema::table('resources', function (Blueprint $table) {
            $table->index(['account_id', 'active'], 'idx_resources_account_active');
        });

        // users (219K rows) — filtered by active in various lookups
        Schema::table('users', function (Blueprint $table) {
            $table->index(['account_id', 'active', 'deleted_at'], 'idx_users_account_active_deleted');
        });

        // leads (512K rows) — has scopeActive that filters active=1
        Schema::table('leads', function (Blueprint $table) {
            $table->index(['account_id', 'active', 'deleted_at'], 'idx_leads_account_active_deleted');
        });
    }

    public function down(): void
    {
        Schema::table('leads_services', function (Blueprint $table) {
            $table->dropIndex('idx_leads_services_lead_service');
        });

        Schema::table('package_services', function (Blueprint $table) {
            $table->dropIndex('idx_package_services_pkg_svc');
        });

        Schema::table('package_bundles', function (Blueprint $table) {
            $table->dropIndex('idx_package_bundles_pkg_bundle');
        });

        Schema::table('bundle_has_services', function (Blueprint $table) {
            $table->dropIndex('idx_bundle_has_services_composite');
        });

        Schema::table('doctor_has_locations', function (Blueprint $table) {
            $table->dropIndex('idx_doctor_has_locations_composite');
        });

        Schema::table('discount_has_locations', function (Blueprint $table) {
            $table->dropIndex('idx_discount_has_locations_composite');
        });

        Schema::table('resource_has_rota', function (Blueprint $table) {
            $table->dropIndex('idx_resource_has_rota_res_loc');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex('idx_services_account_active_endnode');
        });

        Schema::table('resources', function (Blueprint $table) {
            $table->dropIndex('idx_resources_account_active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_account_active_deleted');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('idx_leads_account_active_deleted');
        });
    }
};
