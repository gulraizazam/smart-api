<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function safeModify(string $table, string $column, string $typeSql): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        try {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$typeSql}");
        } catch (\Throwable $e) {
            \Log::warning("Skipping MODIFY {$table}.{$column}: " . $e->getMessage());
        }
    }

    public function up(): void
    {
        $this->safeModify('sms_logs', 'to', 'VARCHAR(50) NULL');
        $this->safeModify('sms_logs', 'mask', 'VARCHAR(20) NULL');
        $this->safeModify('sms_logs', 'sms_data', 'VARCHAR(100) NULL');
        $this->safeModify('appointments', 'reason', 'VARCHAR(500) NULL');
        $this->safeModify('sms_logs', 'error_msg', 'VARCHAR(500) NULL');
        $this->safeModify('locations', 'address', 'VARCHAR(191) NULL');
        $this->safeModify('users', 'address', 'VARCHAR(191) NULL');
        $this->safeModify('packages', 'plan_name', 'VARCHAR(191) NULL');
        $this->safeModify('patient_notes', 'note', 'VARCHAR(500) NULL');
        $this->safeModify('lead_comments', 'comment', 'VARCHAR(500) NULL');
        $this->safeModify('locations', 'google_map', 'VARCHAR(500) NULL');
    }

    public function down(): void
    {
        $this->safeModify('sms_logs', 'to', 'TEXT NULL');
        $this->safeModify('sms_logs', 'mask', 'TEXT NULL');
        $this->safeModify('sms_logs', 'sms_data', 'TEXT NULL');
        $this->safeModify('appointments', 'reason', 'TEXT NULL');
        $this->safeModify('sms_logs', 'error_msg', 'TEXT NULL');
        $this->safeModify('locations', 'address', 'TEXT NULL');
        $this->safeModify('users', 'address', 'TEXT NULL');
        $this->safeModify('packages', 'plan_name', 'TEXT NULL');
        $this->safeModify('patient_notes', 'note', 'TEXT NULL');
        $this->safeModify('lead_comments', 'comment', 'TEXT NULL');
        $this->safeModify('locations', 'google_map', 'TEXT NULL');
    }
};
