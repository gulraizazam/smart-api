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

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        foreach ($columns as $c) {
            if (! Schema::hasColumn($table, $c)) {
                return;
            }
        }
        if ($this->indexExists($table, $name)) {
            return;
        }
        try {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
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
        $this->addIndex('package_advances', ['cash_flow', 'patient_id', 'cash_amount'], 'idx_pa_cashflow_patient_amount');
        $this->addIndex('appointments', ['appointment_type_id', 'patient_id', 'id'], 'idx_appt_type_patient_id');
    }

    public function down(): void
    {
        $this->dropIndex('package_advances', 'idx_pa_cashflow_patient_amount');
        $this->dropIndex('appointments', 'idx_appt_type_patient_id');
    }
};
