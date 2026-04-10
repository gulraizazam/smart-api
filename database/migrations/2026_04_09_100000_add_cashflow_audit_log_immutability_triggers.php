<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The audit found that cashflow_audit_logs is the immutable record of every
 * state transition on the financial spine — but the table had no UPDATE or
 * DELETE protection at the database level. Eloquent-level guards on the
 * model only catch calls that go through Eloquent; raw query-builder writes
 * (DB::table(...)->update()) bypass them entirely.
 *
 * This migration adds two MariaDB triggers that raise SIGNAL SQLSTATE on
 * any UPDATE or DELETE attempt against the table. Combined with the
 * Eloquent overrides on the CashflowAuditLog model, this guarantees that
 * once a row is written, it cannot be silently rewritten — by any path.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SIGNAL SQLSTATE '45000' is the standard "user-defined error" code
        // recognised by MariaDB / MySQL. Surface a clear message so callers
        // see *why* the operation was rejected.
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trg_cashflow_audit_logs_no_update;
            CREATE TRIGGER trg_cashflow_audit_logs_no_update
            BEFORE UPDATE ON cashflow_audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'cashflow_audit_logs is immutable: UPDATE rejected';
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trg_cashflow_audit_logs_no_delete;
            CREATE TRIGGER trg_cashflow_audit_logs_no_delete
            BEFORE DELETE ON cashflow_audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'cashflow_audit_logs is immutable: DELETE rejected';
            END;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_cashflow_audit_logs_no_update;');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_cashflow_audit_logs_no_delete;');
    }
};
