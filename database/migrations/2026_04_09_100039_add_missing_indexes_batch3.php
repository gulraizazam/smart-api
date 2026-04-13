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

    private function addIndex(string $table, array|string $columns, string $name, bool $unique = false): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        $cols = (array) $columns;
        foreach ($cols as $c) {
            if (! Schema::hasColumn($table, $c)) {
                return;
            }
        }
        if ($this->indexExists($table, $name)) {
            return;
        }
        try {
            Schema::table($table, function (Blueprint $t) use ($cols, $name, $unique) {
                $unique ? $t->unique($cols, $name) : $t->index($cols, $name);
            });
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

    public function up(): void
    {
        $this->addIndex('appointments_daily_stats', 'scheduled_date', 'idx_ads_scheduled_date');
        $this->addIndex('appointments_daily_stats', ['centre_id', 'scheduled_date'], 'idx_ads_centre_date');
        $this->addIndex('resource_has_rota_days', 'date', 'idx_rota_days_date');
        $this->addIndex('permissions', ['name', 'guard_name'], 'permissions_name_guard_name_unique', true);
        $this->addIndex('roles', ['name', 'guard_name'], 'roles_name_guard_name_unique', true);
    }

    public function down(): void
    {
        $this->dropIndex('appointments_daily_stats', 'idx_ads_scheduled_date');
        $this->dropIndex('appointments_daily_stats', 'idx_ads_centre_date');
        $this->dropIndex('resource_has_rota_days', 'idx_rota_days_date');
        $this->dropIndex('permissions', 'permissions_name_guard_name_unique');
        $this->dropIndex('roles', 'roles_name_guard_name_unique');
    }
};
