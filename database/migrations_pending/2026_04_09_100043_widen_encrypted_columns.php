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
        $this->safeModify('users', 'cnic', 'VARCHAR(500) DEFAULT NULL');
        $this->safeModify('user_operator_settings', 'password', 'VARCHAR(500) DEFAULT NULL');
        $this->safeModify('global_operator_settings', 'password', 'VARCHAR(500) DEFAULT NULL');
    }

    public function down(): void
    {
        $this->safeModify('users', 'cnic', 'VARCHAR(15) DEFAULT NULL');
        $this->safeModify('user_operator_settings', 'password', 'VARCHAR(50) DEFAULT NULL');
        $this->safeModify('global_operator_settings', 'password', 'VARCHAR(50) DEFAULT NULL');
    }
};
