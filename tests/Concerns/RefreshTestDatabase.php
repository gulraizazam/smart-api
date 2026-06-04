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
 * canonical schema snapshot at `database/schema/mariadb_testing-schema.sql`
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
     *
     * NOT SAFE under concurrent pest processes against the same database.
     * Two pest invocations sharing `cutera_test` will stomp on each other:
     * the second process drops tables the first is actively running tests
     * against. The fix is "don't run parallel pest on the same DB" — see
     * CLAUDE.md for the convention. A per-process DB or CI-level locking
     * would be needed if we ever want true parallel test execution.
     */
    protected function loadTestSchemaFromDump(): void
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();

        // Hard guard: never wipe a database whose name doesn't end in
        // `_test`. The mariadb_testing connection defaults to `cutera_test`
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

        $dumpPath = database_path('schema/mariadb_testing-schema.sql');

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

        $this->ensureExpenseAttachmentsTable($pdo);

        $this->ensurePasswordResetTokensTable($pdo);

        $this->ensureStaffTransfersTable($pdo);

        $this->ensurePackageBundlesAccountId($pdo);

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

            // Unique dedup key on appointments_daily_stats — added by
            // migration 2026_05_04_120000_dedup_and_index_appointments_daily_stats.
            // The cron's upsert relies on this for correct dedup.
            'ALTER TABLE `appointments_daily_stats` ADD UNIQUE INDEX `uniq_ads_appointment_date` (`appointment_id`, `scheduled_date`)',

            // sms_last_attempt_at column on appointments — added by
            // migration 2026_05_04_140000_add_sms_last_attempt_at_to_appointments.
            // The booking-SMS cron uses this for retry cadence.
            'ALTER TABLE `appointments` ADD COLUMN `sms_last_attempt_at` TIMESTAMP NULL DEFAULT NULL',
            'ALTER TABLE `appointments` ADD INDEX `idx_appointments_sms_last_attempt_at` (`sms_last_attempt_at`)',
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
     * Mirror the `create_expense_attachments_table` migration on the test
     * schema. The dump file predates the migration (2026-05-13), and the
     * cash-flow service unconditionally joins this table when computing
     * the Reused-receipt flag, so any test that exercises
     * `ExpenseService::create()` blows up with "table doesn't exist"
     * unless we provision it here.
     *
     * Keep in lockstep with
     * database/migrations/2026_05_13_120000_create_expense_attachments_table.php.
     */
    protected function ensureExpenseAttachmentsTable(\PDO $pdo): void
    {
        // CREATE … IF NOT EXISTS keeps this idempotent across the next
        // schema-dump regeneration: once the dump carries the table the
        // statement becomes a no-op, and we can retire this helper.
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `expense_attachments` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `account_id` INT UNSIGNED NOT NULL,
                `expense_id` BIGINT UNSIGNED NULL,
                `file_name` VARCHAR(255) NOT NULL,
                `file_path` VARCHAR(500) NOT NULL,
                `mime_type` VARCHAR(100) NOT NULL,
                `file_size` INT UNSIGNED NOT NULL,
                `sha256` CHAR(64) NOT NULL,
                `uploaded_by` INT UNSIGNED NOT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `expatt_account_expense_idx` (`account_id`, `expense_id`),
                KEY `expatt_account_sha_idx` (`account_id`, `sha256`),
                KEY `expatt_expense_idx` (`expense_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        // FKs are intentionally omitted in the test DB. The dump doesn't
        // build them for sibling tables either (the `dropStaleSchemaForeignKeys`
        // helper actively removes some), so emitting them here would just
        // create one-off divergence — the production migration has the
        // real FK definitions and that's the contract that matters.
    }

    /**
     * Laravel's default password-reset table — never landed in the
     * dev `crm` schema (the project predates Laravel's built-in
     * notification flow, and we use a curated schema dump rather
     * than running framework migrations). Tests that exercise the
     * password-reset flow fail with "Table doesn't exist" without it.
     *
     * Matches `framework/database/migrations/0001_01_01_000000_create_users_table.php`.
     */
    protected function ensurePasswordResetTokensTable(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
                `email` VARCHAR(255) NOT NULL,
                `token` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Phase-B of Cash Movements ships a brand-new `staff_transfers` table
     * but the test-DB dump pre-dates it. Mirror the production migration's
     * shape here so feature tests can write rows. Drop this helper once the
     * dump is regenerated (the CREATE IF NOT EXISTS makes the call a no-op
     * once the dump catches up).
     *
     * Keep in lockstep with
     * database/migrations/2026_05_13_130000_create_staff_transfers_table.php.
     */
    protected function ensureStaffTransfersTable(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `staff_transfers` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `account_id` BIGINT UNSIGNED NOT NULL,
                `from_user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Staff handing over the cash',
                `to_user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Staff receiving the cash',
                `amount` DECIMAL(15,2) NOT NULL,
                `description` TEXT NULL,
                `voided_at` TIMESTAMP NULL DEFAULT NULL,
                `void_reason` VARCHAR(100) NULL,
                `voided_by` BIGINT UNSIGNED NULL,
                `created_by` BIGINT UNSIGNED NOT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `stxf_account_from_idx` (`account_id`, `from_user_id`),
                KEY `stxf_account_to_idx` (`account_id`, `to_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        // FKs intentionally omitted — matches the pattern for expense_attachments.
    }

    /**
     * Mirror 2026_06_04_130200_add_account_id_to_package_bundles on the
     * test schema (the dump predates it). The PackageBundles model now
     * stamps `account_id` on create, so without the column any
     * authenticated insert in the suite would fail "unknown column".
     * Keep in lockstep with that migration. Drop this helper once the
     * dump is regenerated to carry the column.
     */
    protected function ensurePackageBundlesAccountId(\PDO $pdo): void
    {
        $statements = [
            'ALTER TABLE `package_bundles` ADD COLUMN `account_id` INT UNSIGNED NULL AFTER `package_id`',
            'ALTER TABLE `package_bundles` ADD INDEX `idx_package_bundles_account` (`account_id`)',
        ];

        foreach ($statements as $sql) {
            try {
                $pdo->exec($sql);
            } catch (\PDOException $e) {
                if (! preg_match('/(Duplicate|exists|already|check that column)/i', $e->getMessage())) {
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
