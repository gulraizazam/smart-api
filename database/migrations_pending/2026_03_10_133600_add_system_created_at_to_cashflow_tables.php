<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add immutable system_created_at column to tables used in cash flow calculations.
     * This column records the real DB insertion timestamp and is never editable by
     * application code (not in any model's $fillable).
     * 
     * Purpose: go-live filtering should use this instead of created_at, because
     * created_at can be backdated by users (e.g. refund date, payment date edits).
     */
    public function up(): void
    {
        $tables = ['package_advances', 'expenses', 'cash_transfers', 'staff_advances', 'staff_returns'];

        foreach ($tables as $tableName) {
            if (!Schema::hasColumn($tableName, 'system_created_at')) {
                // Add column with DEFAULT CURRENT_TIMESTAMP in one statement (MariaDB compatible)
                DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `system_created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `updated_at`");

                // Backfill existing records from created_at (best approximation)
                DB::statement("UPDATE `{$tableName}` SET `system_created_at` = `created_at`");
            }
        }
    }

    public function down(): void
    {
        $tables = ['package_advances', 'expenses', 'cash_transfers', 'staff_advances', 'staff_returns'];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'system_created_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('system_created_at');
                });
            }
        }
    }
};
