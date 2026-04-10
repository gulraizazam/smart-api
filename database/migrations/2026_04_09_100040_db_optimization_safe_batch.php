<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Safe database optimization batch (5 phases).
     *
     * Phase 1: Repair 2 invalid package_advances.created_at rows
     * Phase 2: Add created_at indexes on 5 large tables
     * Phase 3: Right-size 6 verified VARCHAR columns
     * Phase 4a: Convert 3 TEXT/LONGTEXT columns → VARCHAR (small/medium tables)
     * Phase 4b: audit_trail_changes (7.5M rows, partitioned) — TEXT/VARCHAR rebuild
     * Phase 5: Fix utf8mb4_bin collation on 7 JSON-storing longtext columns
     *
     * All changes are data-preserving, schema-additive or schema-preserving,
     * and read-compatible. Verified against actual data lengths with safety headroom.
     */
    public function up(): void
    {
        // ========================================
        // Phase 1 — Data repair (2 rows, instant)
        // ========================================
        // Row 185978: created_at, updated_at, AND system_created_at all invalid;
        // use a fixed sentinel
        DB::table('package_advances')
            ->where('id', 185978)
            ->update([
                'created_at'        => '2024-01-01 00:00:00',
                'updated_at'        => '2024-01-01 00:00:00',
                'system_created_at' => '2024-01-01 00:00:00',
            ]);

        // Row 524978: updated_at is valid; use it for created_at + system_created_at
        DB::table('package_advances')
            ->where('id', 524978)
            ->update([
                'created_at'        => '2025-01-25 23:50:32',
                'system_created_at' => '2025-01-25 23:50:32',
            ]);

        // ========================================
        // Phase 2 — Add created_at indexes (additive, INPLACE)
        // ========================================
        Schema::table('leads_services', function ($t) {
            $t->index('created_at', 'idx_leads_services_created_at');
        });
        Schema::table('package_services', function ($t) {
            $t->index('created_at', 'idx_package_services_created_at');
        });
        Schema::table('package_bundles', function ($t) {
            $t->index('created_at', 'idx_package_bundles_created_at');
        });
        Schema::table('lead_comments', function ($t) {
            $t->index('created_at', 'idx_lead_comments_created_at');
        });
        Schema::table('resource_has_rota_days', function ($t) {
            $t->index('created_at', 'idx_rhrd_created_at');
        });

        // ========================================
        // Phase 3 — Right-size verified VARCHARs
        // ========================================
        // leads.phone: max=16 → 30 (87% headroom)
        DB::statement("ALTER TABLE leads MODIFY phone VARCHAR(30) NOT NULL");

        // activities: 4 columns in one ALTER (single rebuild pass)
        DB::statement("ALTER TABLE activities
            MODIFY location VARCHAR(100) DEFAULT NULL,
            MODIFY action VARCHAR(50) NOT NULL,
            MODIFY activity_type VARCHAR(50) DEFAULT NULL,
            MODIFY appointment_type VARCHAR(100) DEFAULT NULL");

        // plan_invoices.invoice_number: max=15 → 30, also part of unique index
        DB::statement("ALTER TABLE plan_invoices MODIFY invoice_number VARCHAR(30) NOT NULL");

        // ========================================
        // Phase 4a — TEXT → VARCHAR (3 small/medium tables)
        // ========================================
        // activities.description: max=1033 → VARCHAR(2000) (94% headroom)
        DB::statement("ALTER TABLE activities MODIFY description VARCHAR(2000) DEFAULT NULL");

        // package_advances.refund_note: max=967 → VARCHAR(2000) (107% headroom)
        DB::statement("ALTER TABLE package_advances MODIFY refund_note VARCHAR(2000) DEFAULT NULL");

        // sms_logs.text: max=327 → VARCHAR(1000) (206% headroom)
        DB::statement("ALTER TABLE sms_logs MODIFY text VARCHAR(1000) DEFAULT NULL");

        // ========================================
        // Phase 4b — audit_trail_changes (7.5M rows, partitioned)
        // ========================================
        // Single bulk ALTER — one rebuild pass, 5-15 min expected
        // field_before/after: max=6580 → VARCHAR(8000) (22% headroom)
        // field_name: SQL identifier, ANSI max 64 → varchar(64)
        DB::statement("ALTER TABLE audit_trail_changes
            MODIFY field_before VARCHAR(8000) DEFAULT NULL,
            MODIFY field_after VARCHAR(8000) DEFAULT NULL,
            MODIFY field_name VARCHAR(64) DEFAULT NULL");

        // ========================================
        // Phase 5 — utf8mb4_bin → utf8mb4_unicode_ci
        // ========================================
        $collationFixes = [
            ['cashflow_audit_logs',          'old_values',       'NULL'],
            ['cashflow_audit_logs',          'new_values',       'NULL'],
            ['cashflow_notifications',       'data',             'NULL'],
            ['custom_form_feedback_details', 'content',          'NULL'],
            ['custom_form_fields',           'content',          'NULL'],
            ['period_locks',                 'balance_snapshot', 'NULL'],
            ['student_verifications',        'document_paths',   'NOT NULL'],
        ];

        foreach ($collationFixes as [$table, $col, $null]) {
            $nullClause = $null === 'NOT NULL' ? 'NOT NULL' : 'DEFAULT NULL';
            DB::statement("ALTER TABLE `$table` MODIFY `$col` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci $nullClause");
        }
    }

    public function down(): void
    {
        // Phase 5 reversal — back to utf8mb4_bin
        $collationReverts = [
            ['cashflow_audit_logs',          'old_values',       'NULL'],
            ['cashflow_audit_logs',          'new_values',       'NULL'],
            ['cashflow_notifications',       'data',             'NULL'],
            ['custom_form_feedback_details', 'content',          'NULL'],
            ['custom_form_fields',           'content',          'NULL'],
            ['period_locks',                 'balance_snapshot', 'NULL'],
            ['student_verifications',        'document_paths',   'NOT NULL'],
        ];
        foreach ($collationReverts as [$table, $col, $null]) {
            $nullClause = $null === 'NOT NULL' ? 'NOT NULL' : 'DEFAULT NULL';
            DB::statement("ALTER TABLE `$table` MODIFY `$col` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin $nullClause");
        }

        // Phase 4b reversal — back to TEXT / varchar(500)
        DB::statement("ALTER TABLE audit_trail_changes
            MODIFY field_before TEXT DEFAULT NULL,
            MODIFY field_after TEXT DEFAULT NULL,
            MODIFY field_name VARCHAR(500) DEFAULT NULL");

        // Phase 4a reversal
        DB::statement("ALTER TABLE activities MODIFY description TEXT DEFAULT NULL");
        DB::statement("ALTER TABLE package_advances MODIFY refund_note LONGTEXT DEFAULT NULL");
        DB::statement("ALTER TABLE sms_logs MODIFY text TEXT DEFAULT NULL");

        // Phase 3 reversal — back to original sizes
        DB::statement("ALTER TABLE leads MODIFY phone VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE activities
            MODIFY location VARCHAR(400) DEFAULT NULL,
            MODIFY action VARCHAR(255) NOT NULL,
            MODIFY activity_type VARCHAR(255) DEFAULT NULL,
            MODIFY appointment_type VARCHAR(300) DEFAULT NULL");
        DB::statement("ALTER TABLE plan_invoices MODIFY invoice_number VARCHAR(255) NOT NULL");

        // Phase 2 reversal — drop the 5 added indexes
        Schema::table('leads_services', fn($t) => $t->dropIndex('idx_leads_services_created_at'));
        Schema::table('package_services', fn($t) => $t->dropIndex('idx_package_services_created_at'));
        Schema::table('package_bundles', fn($t) => $t->dropIndex('idx_package_bundles_created_at'));
        Schema::table('lead_comments', fn($t) => $t->dropIndex('idx_lead_comments_created_at'));
        Schema::table('resource_has_rota_days', fn($t) => $t->dropIndex('idx_rhrd_created_at'));

        // Phase 1 NOT reversed — corrupt timestamps should not be restored
    }
};
