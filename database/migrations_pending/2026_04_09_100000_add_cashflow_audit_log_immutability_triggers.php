<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds MariaDB triggers that raise SIGNAL SQLSTATE on any UPDATE or DELETE
 * attempt against cashflow_audit_logs. Shared-hosting may deny CREATE TRIGGER
 * without SUPER/TRIGGER privilege, so every DDL is wrapped in try/catch.
 */
return new class extends Migration
{
    private function safeUnprepared(string $sql): void
    {
        try {
            DB::unprepared($sql);
        } catch (\Throwable $e) {
            \Log::warning('Trigger DDL failed: ' . $e->getMessage());
        }
    }

    public function up(): void
    {
        if (! Schema::hasTable('cashflow_audit_logs')) {
            return;
        }

        $this->safeUnprepared('DROP TRIGGER IF EXISTS trg_cashflow_audit_logs_no_update');
        $this->safeUnprepared(<<<'SQL'
            CREATE TRIGGER trg_cashflow_audit_logs_no_update
            BEFORE UPDATE ON cashflow_audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'cashflow_audit_logs is immutable: UPDATE rejected';
            END
        SQL);

        $this->safeUnprepared('DROP TRIGGER IF EXISTS trg_cashflow_audit_logs_no_delete');
        $this->safeUnprepared(<<<'SQL'
            CREATE TRIGGER trg_cashflow_audit_logs_no_delete
            BEFORE DELETE ON cashflow_audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'cashflow_audit_logs is immutable: DELETE rejected';
            END
        SQL);
    }

    public function down(): void
    {
        $this->safeUnprepared('DROP TRIGGER IF EXISTS trg_cashflow_audit_logs_no_update');
        $this->safeUnprepared('DROP TRIGGER IF EXISTS trg_cashflow_audit_logs_no_delete');
    }
};
