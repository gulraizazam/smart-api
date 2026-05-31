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
        $this->addIndex('activities', 'created_at', 'idx_activities_created_at');
        $this->addIndex('leads', 'created_at', 'idx_leads_created_at');
        $this->addIndex('invoice_details', 'created_at', 'idx_invoice_details_created_at');
        $this->addIndex('users', 'created_at', 'idx_users_created_at');
        $this->addIndex('appointments', 'created_at', 'idx_appointments_created_at');
    }

    public function down(): void
    {
        $this->dropIndex('activities', 'idx_activities_created_at');
        $this->dropIndex('leads', 'idx_leads_created_at');
        $this->dropIndex('invoice_details', 'idx_invoice_details_created_at');
        $this->dropIndex('users', 'idx_users_created_at');
        $this->dropIndex('appointments', 'idx_appointments_created_at');
    }
};
