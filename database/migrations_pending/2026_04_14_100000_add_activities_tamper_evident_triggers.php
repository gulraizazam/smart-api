<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Adds MariaDB triggers that make `activities` and `activities_archive`
 * tamper-evident. HIPAA §164.312(c)(1) integrity requirement: audit rows
 * must be protected from improper alteration or destruction.
 *
 * Behavior:
 *   - Direct DELETE or UPDATE raises SIGNAL SQLSTATE '45000' and is rejected.
 *   - Authorized callers (the archive command, the one-time reclassify
 *     command) set a session variable before their mutation:
 *
 *         SET @activities_mutation_allowed = 1;
 *
 *     The trigger checks this variable. Any DELETE/UPDATE without it fails.
 *
 *   - Session variables reset at connection close, so accidental leaks of
 *     the "unlocked" state do not persist into a later request.
 *
 * Shared-hosting may deny CREATE TRIGGER without SUPER/TRIGGER privilege,
 * so every DDL is wrapped in try/catch and logged — the migration does
 * not hard-fail if triggers can't be created (rollback plan: host
 * operators must grant privilege; re-running this migration succeeds
 * once the privilege is in place).
 *
 * Append-only enforcement is defense-in-depth, not absolute — a DBA with
 * SUPER can DROP TRIGGER. Treat this as one layer alongside access
 * controls on the DB user account.
 */
return new class extends Migration
{
    private const TABLES = ['activities', 'activities_archive'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->installTriggers($table);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            $this->safeUnprepared("DROP TRIGGER IF EXISTS trg_{$table}_no_update");
            $this->safeUnprepared("DROP TRIGGER IF EXISTS trg_{$table}_no_delete");
        }
    }

    private function installTriggers(string $table): void
    {
        $this->safeUnprepared("DROP TRIGGER IF EXISTS trg_{$table}_no_update");
        $this->safeUnprepared(<<<SQL
            CREATE TRIGGER trg_{$table}_no_update
            BEFORE UPDATE ON `{$table}`
            FOR EACH ROW
            BEGIN
                IF @activities_mutation_allowed IS NULL OR @activities_mutation_allowed != 1 THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = '{$table} is tamper-evident: set @activities_mutation_allowed=1 in an authorized context before UPDATE';
                END IF;
            END
            SQL
        );

        $this->safeUnprepared("DROP TRIGGER IF EXISTS trg_{$table}_no_delete");
        $this->safeUnprepared(<<<SQL
            CREATE TRIGGER trg_{$table}_no_delete
            BEFORE DELETE ON `{$table}`
            FOR EACH ROW
            BEGIN
                IF @activities_mutation_allowed IS NULL OR @activities_mutation_allowed != 1 THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = '{$table} is tamper-evident: set @activities_mutation_allowed=1 in an authorized context before DELETE';
                END IF;
            END
            SQL
        );
    }

    private function safeUnprepared(string $sql): void
    {
        try {
            DB::unprepared($sql);
        } catch (Throwable $e) {
            Log::warning('activities trigger DDL failed', [
                'event' => 'migration.activities_triggers.ddl_failed',
                'error' => $e->getMessage(),
            ]);
        }
    }
};
