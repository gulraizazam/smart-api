<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Drop-in replacement for Laravel's RefreshDatabase trait that hydrates the
 * test database from a curated SQL schema dump instead of running the 275
 * historical migrations.
 *
 * Why: many of the legacy migrations were authored against a production-DB
 * shape that pre-dated the migration history (camelCase columns, FK columns
 * created via direct ALTER on the live DB, schema fix-ups that assumed prior
 * state). Running them on a fresh DB hits dozens of "duplicate column",
 * "unknown column", and "FK constraint malformed" failures. Maintaining a
 * canonical schema snapshot at `database/schema/mysql_testing-schema.sql`
 * (regenerated from the dev `crm` DB via `php database/schema/dump_schema.php`)
 * sidesteps the entire migration mess and gives tests a stable, fast,
 * production-shaped schema to run against.
 *
 * Per-test isolation is unchanged from RefreshDatabase: each test runs inside
 * a transaction that rolls back at teardown.
 */
trait RefreshTestDatabase
{
    use RefreshDatabase;

    /**
     * Override the migration step. Instead of running `migrate:fresh`, drop
     * every table in the test DB and re-load from the schema dump file.
     */
    protected function migrateDatabases(): void
    {
        $this->loadTestSchemaFromDump();
    }

    /**
     * Drop all tables in the current connection's database and execute the
     * SQL dump file. Idempotent — safe to call multiple times.
     */
    protected function loadTestSchemaFromDump(): void
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();

        // Hard guard: never wipe a database whose name doesn't end in
        // `_test`. The mysql_testing connection defaults to `ALLURA_test`
        // but a misconfigured env could point at the dev `crm` database;
        // catching that here prevents accidental data loss.
        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Refusing to wipe database `{$database}` — test database name must end in `_test`. "
                .'Check DB_TEST_DATABASE in phpunit.xml / .env.testing.'
            );
        }

        // Schema::dropAllTables() respects FK relationships and disables
        // FK checks for the duration of the drop, so it works regardless
        // of the order tables are dropped in.
        Schema::disableForeignKeyConstraints();
        Schema::dropAllTables();
        Schema::enableForeignKeyConstraints();

        $dumpPath = database_path('schema/mysql_testing-schema.sql');

        if (! is_file($dumpPath)) {
            throw new RuntimeException(
                "Test schema dump not found at {$dumpPath}. "
                .'Run `php database/schema/dump_schema.php` from a workstation with the dev `crm` DB available to regenerate.'
            );
        }

        $sql = file_get_contents($dumpPath);

        if ($sql === false || $sql === '') {
            throw new RuntimeException("Test schema dump at {$dumpPath} is empty or unreadable.");
        }

        // Execute the dump as a single multi-statement payload. The schema
        // file uses `;` as the statement terminator and contains no DELIMITER
        // tricks, so a naive split is safe.
        $statements = array_filter(
            array_map('trim', preg_split('/;\s*\n/', $sql)),
            static fn (string $stmt): bool => $stmt !== '' && ! str_starts_with($stmt, '--'),
        );

        $pdo = $connection->getPdo();

        // FK checks must be disabled for the entire load. The dump file
        // contains a `SET FOREIGN_KEY_CHECKS=0` statement at the top, but
        // we strip empty lines and comments before splitting; setting it
        // here on the live PDO connection is the safe belt-and-braces.
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($statements as $stmt) {
                $pdo->exec($stmt);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->dropStaleSchemaForeignKeys($pdo);

        $this->applyVoucherHardening($pdo);

        $this->installCashflowAuditLogTriggers($pdo);

        RefreshDatabaseState::$migrated = true;
    }

    /**
     * Mirror the production `harden_voucher_tables` migration on the
     * test schema. The dump file pre-dates the migration, so without
     * this the test DB would lack the soft-delete column, the new
     * FK + index pair, and the corrected decimal(10,2) precision on
     * `package_vouchers.amount` (the dump still has decimal(10,0),
     * which silently truncates partial-rupee redemptions).
     *
     * Keep this in lockstep with
     * database/migrations/2026_04_15_162039_harden_voucher_tables.php.
     * If that migration evolves, update this method to match.
     */
    protected function applyVoucherHardening(\PDO $pdo): void
    {
        $statements = [
            // Soft-delete column on user_vouchers.
            'ALTER TABLE `user_vouchers` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL',

            // Indexes for the new redemption / datatable filter shape.
            'ALTER TABLE `user_vouchers` ADD INDEX `user_vouchers_user_voucher_index` (`user_id`, `voucher_id`)',
            'ALTER TABLE `user_vouchers` ADD INDEX `user_vouchers_voucher_id_index` (`voucher_id`)',

            // Fix the dropped-precision amount column on package_vouchers.
            'ALTER TABLE `package_vouchers` MODIFY COLUMN `amount` DECIMAL(10,2) NOT NULL DEFAULT 0',

            // Indexes for the package-vouchers filter shape.
            'ALTER TABLE `package_vouchers` ADD INDEX `package_vouchers_voucher_user_index` (`voucher_id`, `user_id`)',
            'ALTER TABLE `package_vouchers` ADD INDEX `package_vouchers_package_random_id_index` (`package_random_id`)',
        ];

        foreach ($statements as $sql) {
            try {
                $pdo->exec($sql);
            } catch (\PDOException $e) {
                // Idempotency: tolerate "already exists" / "doesn't exist"
                // when the dump is regenerated to include these in future.
                if (! preg_match('/(Duplicate|exists|already|doesn\'t exist|check that column)/i', $e->getMessage())) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Drop stale foreign keys from the schema dump that no longer reflect
     * the current data model.
     *
     * `user_vouchers.voucher_id` and `package_vouchers.voucher_id` FK the
     * legacy `vouchers` table, but vouchers are now stored as rows in
     * `discounts` with `discount_type='voucher'` — so any modern code
     * path that inserts a user_vouchers / package_vouchers row will
     * violate the stale FK even though the reference is correct.
     * Production has grown around this (FKs likely removed there) and
     * the test DB has to follow suit so feature tests can exercise the
     * real voucher flow.
     *
     * Keep this list *minimal* — each entry represents a known-bad FK
     * in the schema dump; removing it should be backed by an audit
     * note, not a convenience.
     */
    protected function dropStaleSchemaForeignKeys(\PDO $pdo): void
    {
        $staleForeignKeys = [
            'user_vouchers' => ['fk_user_vouchers_voucher_id'],
            'package_vouchers' => ['fk_pkg_vouchers_voucher'],
            'voucher_has_locations' => ['fk_voucher_has_locations_voucher_id'],
        ];

        foreach ($staleForeignKeys as $table => $constraints) {
            foreach ($constraints as $constraint) {
                // MariaDB has no IF EXISTS on DROP FOREIGN KEY, so
                // swallow the "doesn't exist" error quietly.
                try {
                    $pdo->exec("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
                } catch (\PDOException $e) {
                    // Ignore — FK already removed or never existed.
                }
            }
        }
    }

    /**
     * Install the cashflow_audit_logs immutability triggers.
     *
     * The migration at
     * `database/migrations/2026_04_09_100000_add_cashflow_audit_log_immutability_triggers.php`
     * is the production source of truth for these triggers, but the test DB
     * is hydrated from the schema dump (not from migrations) so the triggers
     * have to be re-applied here every time the test DB is rebuilt.
     *
     * MariaDB compound trigger bodies use `BEGIN ... END` blocks containing
     * inner `;` separators. The schema-dump loader splits on `;\n` which
     * would break the body, so we install triggers here directly via PDO
     * after the dump load completes — one PDO::exec() call per trigger so
     * each is parsed as a single statement.
     */
    protected function installCashflowAuditLogTriggers(\PDO $pdo): void
    {
        $pdo->exec('DROP TRIGGER IF EXISTS trg_cashflow_audit_logs_no_update');
        $pdo->exec(<<<'SQL'
            CREATE TRIGGER trg_cashflow_audit_logs_no_update
            BEFORE UPDATE ON cashflow_audit_logs
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'cashflow_audit_logs is immutable: UPDATE rejected'
        SQL);

        $pdo->exec('DROP TRIGGER IF EXISTS trg_cashflow_audit_logs_no_delete');
        $pdo->exec(<<<'SQL'
            CREATE TRIGGER trg_cashflow_audit_logs_no_delete
            BEFORE DELETE ON cashflow_audit_logs
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'cashflow_audit_logs is immutable: DELETE rejected'
        SQL);
    }
}
