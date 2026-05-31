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
        if ($this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($columns, $name) {
            $t->index($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! $this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($name) {
            $t->dropIndex($name);
        });
    }

    public function up(): void
    {
        if (! Schema::hasTable('audit_trails')) {
            return;
        }
        $this->addIndex('audit_trails', ['parent_id', 'id'], 'idx_audit_trails_parent_id');
        $this->addIndex('audit_trails', ['audit_trail_table_name', 'audit_trail_action_name'], 'idx_audit_trails_table_action');
        $this->addIndex('audit_trails', 'created_at', 'idx_audit_trails_created_at');
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_trails')) {
            return;
        }
        $this->dropIndex('audit_trails', 'idx_audit_trails_parent_id');
        $this->dropIndex('audit_trails', 'idx_audit_trails_table_action');
        $this->dropIndex('audit_trails', 'idx_audit_trails_created_at');
    }
};
