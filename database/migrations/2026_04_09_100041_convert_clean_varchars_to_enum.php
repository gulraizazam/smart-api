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
        $this->safeModify('appointments', 'consultancy_type', "ENUM('in_person', 'treatment', 'virtual') DEFAULT NULL");
        $this->safeModify('sms_logs', 'log_type', "ENUM('sms', '2nd_sms', '3rd_sms', 'inventory') NOT NULL DEFAULT 'sms'");
    }

    public function down(): void
    {
        $this->safeModify('appointments', 'consultancy_type', 'VARCHAR(20) DEFAULT NULL');
        $this->safeModify('sms_logs', 'log_type', "VARCHAR(100) NOT NULL DEFAULT 'sms'");
    }
};
