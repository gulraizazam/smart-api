<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            // Add lead_id column if not exists
            if (!Schema::hasColumn('activities', 'lead_id')) {
                $table->unsignedBigInteger('lead_id')->nullable()->after('patient_id');
            }
            
            // Add lead_status column to track status changes
            if (!Schema::hasColumn('activities', 'lead_status')) {
                $table->string('lead_status', 100)->nullable()->after('activity_type');
            }
            
            // Add lead_status_id for reference
            if (!Schema::hasColumn('activities', 'lead_status_id')) {
                $table->unsignedBigInteger('lead_status_id')->nullable()->after('lead_status');
            }
            
            // Add description column for detailed activity description
            if (!Schema::hasColumn('activities', 'description')) {
                $table->text('description')->nullable()->after('action');
            }
            
            // Add account_id for multi-tenancy
            if (!Schema::hasColumn('activities', 'account_id')) {
                $table->unsignedBigInteger('account_id')->nullable()->after('id');
            }
            
            // Add package_id column if not exists
            if (!Schema::hasColumn('activities', 'package_id')) {
                $table->unsignedBigInteger('package_id')->nullable()->after('plan_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $columns = ['lead_id', 'lead_status', 'lead_status_id', 'description', 'account_id', 'package_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('activities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
