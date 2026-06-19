<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use Tests\TestCase;

/**
 * Guards every snapshot-backed permission migration against the rollback bug QA
 * found 2026-06-19: a `->truncate()` inside the down()'s DB::transaction() forces
 * an implicit commit in MariaDB, so the transaction's own commit throws
 * "There is no active transaction" and `migrate:rollback` fails. The clearing
 * must use `->delete()` (DML, no implicit commit).
 *
 * Static source scan (no DB) — teeth: revert any down() to truncate and this reddens.
 */
class PermissionMigrationRollbackSafetyTest extends TestCase
{
    public function test_snapshot_backed_permission_migrations_clear_with_delete_not_truncate(): void
    {
        $scanned = 0;

        foreach (glob(database_path('migrations/*.php')) as $file) {
            $src = (string) file_get_contents($file);

            // The snapshot-backed permission migrations create a *_backup table and
            // wrap their down() restore in a DB::transaction().
            if (! (str_contains($src, '_backup') && str_contains($src, 'DB::transaction') && str_contains($src, 'permission'))) {
                continue;
            }

            $this->assertStringNotContainsString(
                '->truncate(',
                $src,
                basename($file).': ->truncate() inside the transaction breaks migrate:rollback (implicit commit). Use ->delete().',
            );
            $scanned++;
        }

        $this->assertGreaterThanOrEqual(
            5,
            $scanned,
            'expected to scan the permission-cleanup migrations (R1, delete, Tier 1, Tier 2a, Tier 2b)',
        );
    }
}
