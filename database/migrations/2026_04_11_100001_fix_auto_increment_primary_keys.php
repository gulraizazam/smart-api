<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix all tables that lost their AUTO_INCREMENT and PRIMARY KEY on the `id` column.
 *
 * Root cause: database import/restore stripped AUTO_INCREMENT and PK from ~100 tables.
 * This migration detects and repairs every affected table in one pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        $dbName = DB::getDatabaseName();
        $tables = DB::select('SHOW TABLES');
        $key = 'Tables_in_' . $dbName;

        foreach ($tables as $table) {
            $name = $table->$key;

            // Check if table has an `id` column
            $columns = DB::select("SHOW COLUMNS FROM `{$name}` WHERE Field = 'id'");
            if (empty($columns)) {
                continue;
            }

            $col = $columns[0];

            // Skip if already has auto_increment
            if (stripos($col->Extra, 'auto_increment') !== false) {
                continue;
            }

            // Only fix integer-type id columns
            if (stripos($col->Type, 'int') === false) {
                continue;
            }

            // Build the correct column definition preserving the original type
            $type = $col->Type; // e.g. "int(10) unsigned", "bigint(20) unsigned"
            $unsigned = stripos($type, 'unsigned') !== false;

            // Normalize type
            if (stripos($type, 'bigint') !== false) {
                $colDef = 'BIGINT(20) UNSIGNED';
            } elseif (stripos($type, 'int(11)') !== false && ! $unsigned) {
                $colDef = 'INT(11)';
            } else {
                $colDef = 'INT(10) UNSIGNED';
            }

            // Check if a PRIMARY KEY already exists
            $indexes = DB::select("SHOW INDEX FROM `{$name}` WHERE Key_name = 'PRIMARY'");
            $hasPK = ! empty($indexes);

            try {
                // Fix invalid zero-dates that block ALTER TABLE
                foreach (['created_at', 'updated_at', 'deleted_at'] as $tsCol) {
                    $hasTsCol = DB::select("SHOW COLUMNS FROM `{$name}` WHERE Field = ?", [$tsCol]);
                    if (! empty($hasTsCol)) {
                        DB::statement("UPDATE `{$name}` SET `{$tsCol}` = NULL WHERE `{$tsCol}` = '0000-00-00 00:00:00'");
                    }
                }

                // Check if table is partitioned (PK must include partition column)
                $partitions = DB::select(
                    'SELECT PARTITION_EXPRESSION FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND PARTITION_EXPRESSION IS NOT NULL LIMIT 1',
                    [$name]
                );
                $isPartitioned = ! empty($partitions) && ! empty($partitions[0]->PARTITION_EXPRESSION);

                if ($hasPK) {
                    DB::statement("ALTER TABLE `{$name}` MODIFY `id` {$colDef} NOT NULL AUTO_INCREMENT");
                } elseif ($isPartitioned) {
                    // Partitioned tables: PK must include the partition column
                    $partExpr = $partitions[0]->PARTITION_EXPRESSION;
                    // Extract column name from expression like "unix_timestamp(`created_at`)"
                    if (preg_match('/`(\w+)`/', $partExpr, $m)) {
                        $partCol = $m[1];
                        DB::statement("ALTER TABLE `{$name}` MODIFY `id` {$colDef} NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`, `{$partCol}`)");
                    }
                } else {
                    DB::statement("ALTER TABLE `{$name}` MODIFY `id` {$colDef} NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)");
                }
            } catch (\Exception $e) {
                logger()->warning("Failed to fix AUTO_INCREMENT on {$name}: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // Intentionally not reversible — removing AUTO_INCREMENT would break the application
    }
};
