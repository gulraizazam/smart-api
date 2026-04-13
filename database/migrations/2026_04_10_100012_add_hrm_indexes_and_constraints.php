<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Missing FK indexes
        Schema::table('employee_details', function (Blueprint $table) {
            $table->index('reporting_manager_id');
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->index('leave_type_id');
            $table->index('reviewed_by');
            $table->index(['user_id', 'start_date', 'end_date'], 'leave_apps_overlap_idx');
        });

        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->index('city_id');
            $table->index('location_id');
            $table->index('converted_user_id');
        });

        Schema::table('recruitment_interviews', function (Blueprint $table) {
            $table->index('interviewer_id');
        });

        // Composite indexes for filtered queries
        Schema::table('departments', function (Blueprint $table) {
            $table->index(['account_id', 'active'], 'departments_acct_active_idx');
        });

        Schema::table('designations', function (Blueprint $table) {
            $table->index(['account_id', 'active'], 'designations_acct_active_idx');
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->index(['account_id', 'active'], 'leave_types_acct_active_idx');
        });

        // Unique constraints to prevent duplicates per tenant
        Schema::table('departments', function (Blueprint $table) {
            $table->unique(['name', 'account_id', 'deleted_at'], 'departments_name_acct_unique');
        });

        Schema::table('designations', function (Blueprint $table) {
            $table->unique(['name', 'account_id', 'deleted_at'], 'designations_name_acct_unique');
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->unique(['name', 'account_id', 'deleted_at'], 'leave_types_name_acct_unique');
        });

    }

    public function down(): void
    {
        Schema::table('employee_details', function (Blueprint $table) {
            $table->dropIndex(['reporting_manager_id']);
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropIndex(['leave_type_id']);
            $table->dropIndex(['reviewed_by']);
            $table->dropIndex('leave_apps_overlap_idx');
        });

        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->dropIndex(['city_id']);
            $table->dropIndex(['location_id']);
            $table->dropIndex(['converted_user_id']);
        });

        Schema::table('recruitment_interviews', function (Blueprint $table) {
            $table->dropIndex(['interviewer_id']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropIndex('departments_acct_active_idx');
            $table->dropUnique('departments_name_acct_unique');
        });

        Schema::table('designations', function (Blueprint $table) {
            $table->dropIndex('designations_acct_active_idx');
            $table->dropUnique('designations_name_acct_unique');
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropIndex('leave_types_acct_active_idx');
            $table->dropUnique('leave_types_name_acct_unique');
        });

    }
};
