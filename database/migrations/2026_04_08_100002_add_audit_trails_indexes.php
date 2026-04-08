<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing indexes to audit_trails (1.4M rows) and audit_trail_changes (11.7M rows).
     *
     * Query patterns identified from codebase:
     * - audit_trails: WHERE parent_id = '0' ORDER BY id DESC (listing page)
     * - audit_trails: eager-loaded relationships via audit_trail_table_name, audit_trail_action_name
     * - audit_trail_changes: WHERE audit_trail_id = X (already indexed after dedup migration)
     */
    public function up(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            // Covers: WHERE parent_id = '0' ORDER BY id DESC (main listing query)
            $table->index(['parent_id', 'id'], 'idx_audit_trails_parent_id');

            // Covers: filtering/joining by table and action (BelongsTo relationships)
            $table->index(['audit_trail_table_name', 'audit_trail_action_name'], 'idx_audit_trails_table_action');

            // Covers: date-range queries on audit log
            $table->index('created_at', 'idx_audit_trails_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->dropIndex('idx_audit_trails_parent_id');
            $table->dropIndex('idx_audit_trails_table_action');
            $table->dropIndex('idx_audit_trails_created_at');
        });
    }
};
