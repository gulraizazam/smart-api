<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
        $this->safeModify('cashflow_audit_logs', 'old_values', 'JSON NULL');
        $this->safeModify('cashflow_audit_logs', 'new_values', 'JSON NULL');
        $this->safeModify('cashflow_notifications', 'data', 'JSON NULL');
        $this->safeModify('custom_form_feedback_details', 'content', 'JSON NULL');
        $this->safeModify('custom_form_fields', 'content', 'JSON NULL');
        $this->safeModify('period_locks', 'balance_snapshot', 'JSON NULL');
    }

    public function down(): void
    {
        $this->safeModify('cashflow_audit_logs', 'old_values', 'LONGTEXT NULL');
        $this->safeModify('cashflow_audit_logs', 'new_values', 'LONGTEXT NULL');
        $this->safeModify('cashflow_notifications', 'data', 'LONGTEXT NULL');
        $this->safeModify('custom_form_feedback_details', 'content', 'TEXT NULL');
        $this->safeModify('custom_form_fields', 'content', 'TEXT NULL');
        $this->safeModify('period_locks', 'balance_snapshot', 'LONGTEXT NULL');
    }
};
