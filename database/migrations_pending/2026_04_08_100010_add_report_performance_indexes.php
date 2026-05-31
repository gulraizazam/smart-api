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

    private function addIndex(string $table, array|string $columns, string $name): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
    }

    public function up(): void
    {
        $this->addIndex('package_advances', ['account_id', 'created_at'], 'idx_pa_account_created');
        $this->addIndex('invoices', ['account_id', 'deleted_at', 'created_at'], 'idx_invoices_account_deleted_created');
    }

    public function down(): void
    {
        $this->dropIndex('package_advances', 'idx_pa_account_created');
        $this->dropIndex('invoices', 'idx_invoices_account_deleted_created');
    }
};
