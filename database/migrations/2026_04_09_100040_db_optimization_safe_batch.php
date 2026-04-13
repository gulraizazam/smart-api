<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        );
        return ! empty($rows);
    }

    private function addIndex(string $table, string $column, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        if ($this->indexExists($table, $name)) {
            return;
        }
        try {
            Schema::table($table, fn (Blueprint $t) => $t->index($column, $name));
        } catch (\Throwable $e) {
            \Log::warning("Skipping index {$name}: " . $e->getMessage());
        }
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $name)) {
            return;
        }
        try {
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
        } catch (\Throwable $e) {
            \Log::warning("Skipping dropIndex {$name}: " . $e->getMessage());
        }
    }

    private function safeModify(string $table, string $column, string $sql): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        try {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$sql}");
        } catch (\Throwable $e) {
            \Log::warning("Skipping MODIFY {$table}.{$column}: " . $e->getMessage());
        }
    }

    public function up(): void
    {
        // Phase 1 - Data repair
        if (Schema::hasTable('package_advances')) {
            try {
                DB::table('package_advances')->where('id', 185978)->update([
                    'created_at'        => '2024-01-01 00:00:00',
                    'updated_at'        => '2024-01-01 00:00:00',
                    'system_created_at' => '2024-01-01 00:00:00',
                ]);
                DB::table('package_advances')->where('id', 524978)->update([
                    'created_at'        => '2025-01-25 23:50:32',
                    'system_created_at' => '2025-01-25 23:50:32',
                ]);
            } catch (\Throwable $e) {
                \Log::warning('Phase 1 repair: ' . $e->getMessage());
            }
        }

        // Phase 2 - Add created_at indexes
        $this->addIndex('leads_services', 'created_at', 'idx_leads_services_created_at');
        $this->addIndex('package_services', 'created_at', 'idx_package_services_created_at');
        $this->addIndex('package_bundles', 'created_at', 'idx_package_bundles_created_at');
        $this->addIndex('lead_comments', 'created_at', 'idx_lead_comments_created_at');
        $this->addIndex('resource_has_rota_days', 'created_at', 'idx_rhrd_created_at');

        // Phase 3 - Right-size verified VARCHARs
        $this->safeModify('leads', 'phone', 'VARCHAR(30) NOT NULL');
        $this->safeModify('activities', 'location', 'VARCHAR(100) DEFAULT NULL');
        $this->safeModify('activities', 'action', 'VARCHAR(50) NOT NULL');
        $this->safeModify('activities', 'activity_type', 'VARCHAR(50) DEFAULT NULL');
        $this->safeModify('activities', 'appointment_type', 'VARCHAR(100) DEFAULT NULL');
        $this->safeModify('plan_invoices', 'invoice_number', 'VARCHAR(30) NOT NULL');

        // Phase 4a - TEXT -> VARCHAR
        $this->safeModify('activities', 'description', 'VARCHAR(2000) DEFAULT NULL');
        $this->safeModify('package_advances', 'refund_note', 'VARCHAR(2000) DEFAULT NULL');
        $this->safeModify('sms_logs', 'text', 'VARCHAR(1000) DEFAULT NULL');

        // Phase 4b - audit_trail_changes
        $this->safeModify('audit_trail_changes', 'field_before', 'VARCHAR(8000) DEFAULT NULL');
        $this->safeModify('audit_trail_changes', 'field_after', 'VARCHAR(8000) DEFAULT NULL');
        $this->safeModify('audit_trail_changes', 'field_name', 'VARCHAR(64) DEFAULT NULL');

        // Phase 5 - Collation fix
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
            $this->safeModify($table, $col, "LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci {$nullClause}");
        }
    }

    public function down(): void
    {
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
            $this->safeModify($table, $col, "LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin {$nullClause}");
        }

        $this->safeModify('audit_trail_changes', 'field_before', 'TEXT DEFAULT NULL');
        $this->safeModify('audit_trail_changes', 'field_after', 'TEXT DEFAULT NULL');
        $this->safeModify('audit_trail_changes', 'field_name', 'VARCHAR(500) DEFAULT NULL');

        $this->safeModify('activities', 'description', 'TEXT DEFAULT NULL');
        $this->safeModify('package_advances', 'refund_note', 'LONGTEXT DEFAULT NULL');
        $this->safeModify('sms_logs', 'text', 'TEXT DEFAULT NULL');

        $this->safeModify('leads', 'phone', 'VARCHAR(255) NOT NULL');
        $this->safeModify('activities', 'location', 'VARCHAR(400) DEFAULT NULL');
        $this->safeModify('activities', 'action', 'VARCHAR(255) NOT NULL');
        $this->safeModify('activities', 'activity_type', 'VARCHAR(255) DEFAULT NULL');
        $this->safeModify('activities', 'appointment_type', 'VARCHAR(300) DEFAULT NULL');
        $this->safeModify('plan_invoices', 'invoice_number', 'VARCHAR(255) NOT NULL');

        $this->dropIndex('leads_services', 'idx_leads_services_created_at');
        $this->dropIndex('package_services', 'idx_package_services_created_at');
        $this->dropIndex('package_bundles', 'idx_package_bundles_created_at');
        $this->dropIndex('lead_comments', 'idx_lead_comments_created_at');
        $this->dropIndex('resource_has_rota_days', 'idx_rhrd_created_at');
    }
};
