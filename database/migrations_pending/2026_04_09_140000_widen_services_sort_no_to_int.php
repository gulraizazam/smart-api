<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen `services.sort_no` from tinyint(3) unsigned to int unsigned so the
 * id-based sort_no assignment can't overflow once auto_increment crosses 255.
 */
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
        $this->safeModify('services', 'sort_no', 'INT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        $this->safeModify('services', 'sort_no', 'TINYINT(3) UNSIGNED NOT NULL DEFAULT 0');
    }
};
