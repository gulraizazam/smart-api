<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * These indexes optimize the patients datatable query which filters by:
     * - user_type_id, account_id, active, deleted_at (base conditions)
     * - created_at (ordering)
     * - name, phone (search filters)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Composite index for base patient query conditions
            // This covers: WHERE user_type_id = ? AND account_id = ? AND deleted_at IS NULL AND active = ?
            $table->index(['user_type_id', 'account_id', 'active', 'deleted_at'], 'idx_users_patient_base');
            
            // Index for ordering by created_at (descending queries benefit from this)
            $table->index(['created_at'], 'idx_users_created_at');
            
            // Composite index for patient listing with ordering
            // Covers the full query pattern: WHERE conditions + ORDER BY created_at DESC
            $table->index(['user_type_id', 'account_id', 'active', 'created_at'], 'idx_users_patient_listing');
        });

        // Add index on memberships table for the LEFT JOIN
        Schema::table('memberships', function (Blueprint $table) {
            // Composite index for the JOIN condition: patient_id AND active = 1
            $table->index(['patient_id', 'active'], 'idx_memberships_patient_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_patient_base');
            $table->dropIndex('idx_users_created_at');
            $table->dropIndex('idx_users_patient_listing');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex('idx_memberships_patient_active');
        });
    }
};
