<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The UI offers two top-level discount modes: Simple and Configurable.
 * The legacy enum only accepted Fixed/Percentage/Configurable, so saving a
 * Simple discount truncated to '' and failed under strict mode.
 *
 * Rollback: down() restores the prior enum. Rows already storing 'Simple'
 * would truncate on rollback — caller must convert them first.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('discounts')) {
            return;
        }

        $this->safeUnprepared(
            "ALTER TABLE `discounts` MODIFY COLUMN `type` ENUM('Fixed','Percentage','Configurable','Simple') NOT NULL"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('discounts')) {
            return;
        }

        $this->safeUnprepared(
            "ALTER TABLE `discounts` MODIFY COLUMN `type` ENUM('Fixed','Percentage','Configurable') NOT NULL"
        );
    }

    private function safeUnprepared(string $sql): void
    {
        try {
            DB::unprepared($sql);
        } catch (\Throwable $e) {
            Log::warning('discounts.type_enum.alter_failed', [
                'event' => 'discounts.type_enum.alter_failed',
                'error' => $e->getMessage(),
            ]);
        }
    }
};
