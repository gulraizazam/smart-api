<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix missing indexes found during deep index audit.
     *
     * 1. appointments_daily_stats.scheduled_date — 178K rows, full table scan on dashboard
     * 2. resource_has_rota_days.date — 237K rows, full scan on standalone date queries
     * 3. permissions (name, guard_name) — Spatie standard unique constraint missing
     * 4. roles (name, guard_name) — Spatie standard unique constraint missing
     */
    public function up(): void
    {
        // 1. appointments_daily_stats: dashboard queries use
        //    whereBetween('scheduled_date', ...) + whereIn('centre_id', ...)
        //    Composite (centre_id, scheduled_date) serves both the arrival report
        //    and the chart queries. A standalone scheduled_date index also added
        //    for queries that don't filter by centre.
        Schema::table('appointments_daily_stats', function ($table) {
            $table->index('scheduled_date', 'idx_ads_scheduled_date');
            $table->index(['centre_id', 'scheduled_date'], 'idx_ads_centre_date');
        });

        // 2. resource_has_rota_days: whereIn('date', $dates) does full scan of 237K rows
        Schema::table('resource_has_rota_days', function ($table) {
            $table->index('date', 'idx_rota_days_date');
        });

        // 3. permissions: Spatie standard unique constraint — prevents duplicate names
        Schema::table('permissions', function ($table) {
            $table->unique(['name', 'guard_name'], 'permissions_name_guard_name_unique');
        });

        // 4. roles: Spatie standard unique constraint — prevents duplicate names
        Schema::table('roles', function ($table) {
            $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('appointments_daily_stats', function ($table) {
            $table->dropIndex('idx_ads_scheduled_date');
            $table->dropIndex('idx_ads_centre_date');
        });

        Schema::table('resource_has_rota_days', function ($table) {
            $table->dropIndex('idx_rota_days_date');
        });

        Schema::table('permissions', function ($table) {
            $table->dropIndex('permissions_name_guard_name_unique');
        });

        Schema::table('roles', function ($table) {
            $table->dropIndex('roles_name_guard_name_unique');
        });
    }
};
