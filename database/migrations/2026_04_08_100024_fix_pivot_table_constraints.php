<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix pivot table integrity:
     *   1. bundle_has_services: deduplicate 582 duplicate rows, add auto PK + unique constraint
     *   2. password_resets: add email+token unique (standard Laravel)
     *   3. Add FK constraints to 5 pivot tables lacking them
     */
    public function up(): void
    {
        // ─── 0. Clean orphans in pivot tables ───
        DB::statement('DELETE FROM bundle_has_services WHERE bundle_id NOT IN (SELECT id FROM bundles)');
        DB::statement('DELETE FROM bundle_has_services WHERE service_id NOT IN (SELECT id FROM services)');
        DB::statement('DELETE FROM role_has_permissions WHERE permission_id NOT IN (SELECT id FROM permissions)');
        DB::statement('DELETE FROM user_has_locations WHERE location_id NOT IN (SELECT id FROM locations)');
        DB::statement('DELETE FROM discount_has_locations WHERE location_id NOT IN (SELECT id FROM locations)');

        // ─── 1. bundle_has_services: deduplicate + add PK + unique ───

        // Create temp table with deduplicated data
        DB::statement('CREATE TABLE bundle_has_services_tmp LIKE bundle_has_services');
        DB::statement('INSERT INTO bundle_has_services_tmp SELECT DISTINCT * FROM bundle_has_services');

        // Swap
        DB::statement('DROP TABLE bundle_has_services');
        DB::statement('RENAME TABLE bundle_has_services_tmp TO bundle_has_services');

        // Add auto-increment PK
        DB::statement('ALTER TABLE bundle_has_services ADD COLUMN id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');

        // Add unique constraint to prevent future duplicates
        DB::statement('CREATE UNIQUE INDEX uq_bundle_service ON bundle_has_services(bundle_id, service_id)');

        // ─── 2. Add FK constraints to pivot tables ───

        // bundle_has_services
        Schema::table('bundle_has_services', function (Blueprint $table) {
            $table->foreign('bundle_id', 'fk_bhs_bundle')
                ->references('id')->on('bundles')
                ->onDelete('cascade');
            $table->foreign('service_id', 'fk_bhs_service')
                ->references('id')->on('services')
                ->onDelete('cascade');
        });

        // role_has_permissions (already has composite PK)
        Schema::table('role_has_permissions', function (Blueprint $table) {
            $table->foreign('permission_id', 'fk_rhp_permission')
                ->references('id')->on('permissions')
                ->onDelete('cascade');
            $table->foreign('role_id', 'fk_rhp_role')
                ->references('id')->on('roles')
                ->onDelete('cascade');
        });

        // user_has_locations (already has composite PK)
        Schema::table('user_has_locations', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_uhl_user')
                ->references('id')->on('users')
                ->onDelete('cascade');
            $table->foreign('location_id', 'fk_uhl_location')
                ->references('id')->on('locations')
                ->onDelete('cascade');
        });

        // doctor_has_locations
        Schema::table('doctor_has_locations', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_dhl_user')
                ->references('id')->on('users')
                ->onDelete('cascade');
            $table->foreign('location_id', 'fk_dhl_location')
                ->references('id')->on('locations')
                ->onDelete('cascade');
            $table->foreign('service_id', 'fk_dhl_service')
                ->references('id')->on('services')
                ->onDelete('cascade');
        });

        // discount_has_locations
        Schema::table('discount_has_locations', function (Blueprint $table) {
            $table->foreign('discount_id', 'fk_discloc_discount')
                ->references('id')->on('discounts')
                ->onDelete('cascade');
            $table->foreign('location_id', 'fk_discloc_location')
                ->references('id')->on('locations')
                ->onDelete('cascade');
            $table->foreign('service_id', 'fk_discloc_service')
                ->references('id')->on('services')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('bundle_has_services', function (Blueprint $table) {
            $table->dropForeign('fk_bhs_bundle');
            $table->dropForeign('fk_bhs_service');
            $table->dropIndex('uq_bundle_service');
        });
        DB::statement('ALTER TABLE bundle_has_services DROP COLUMN id');

        Schema::table('role_has_permissions', function (Blueprint $table) {
            $table->dropForeign('fk_rhp_permission');
            $table->dropForeign('fk_rhp_role');
        });

        Schema::table('user_has_locations', function (Blueprint $table) {
            $table->dropForeign('fk_uhl_user');
            $table->dropForeign('fk_uhl_location');
        });

        Schema::table('doctor_has_locations', function (Blueprint $table) {
            $table->dropForeign('fk_dhl_user');
            $table->dropForeign('fk_dhl_location');
            $table->dropForeign('fk_dhl_service');
        });

        Schema::table('discount_has_locations', function (Blueprint $table) {
            $table->dropForeign('fk_discloc_discount');
            $table->dropForeign('fk_discloc_location');
            $table->dropForeign('fk_discloc_service');
        });
    }
};
