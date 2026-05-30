<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repoint the permission pivot foreign keys to the live `permissions` table.
 *
 * On some environments (staging: u572390775_staging) a leftover duplicate
 * table `permissions1` exists, and `role_has_permissions.permission_id`
 * (and possibly `model_has_permissions.permission_id`) has its FK bound to
 * `permissions1` instead of `permissions`. InnoDB FKs follow table renames,
 * so a historical rename/restore left the constraint pointing at the stale
 * copy. Existing grants still resolve because the old ids overlap, but any
 * NEW permission (e.g. the dashboard.* tab perms, ids >= 619) lives only in
 * `permissions`, so assigning it violates the misbound FK:
 *
 *   SQLSTATE[23000] 1452 ... CONSTRAINT `fk_rhp_permission`
 *   FOREIGN KEY (`permission_id`) REFERENCES `permissions1` (`id`)
 *
 * The app (Spatie config `permission.table_names.permissions = 'permissions'`)
 * reads `permissions`, and the role-editor renders its tree from `permissions`
 * — so `permissions` is authoritative and `permissions1` is dead weight.
 * This migration drops any permission-pivot FK whose referenced table is NOT
 * `permissions`, prunes rows orphaned w.r.t. `permissions`, and re-adds the
 * FK against `permissions`.
 *
 * Fully guarded + idempotent: on environments without `permissions1` / with
 * the FK already correct (e.g. local `crm`, the test schema), every step is
 * a no-op. `permissions1` itself is intentionally left in place — dropping a
 * data table is a manual, audited step, not an automatic migration.
 *
 * Mirrors the defensive helper style of
 * 2026_04_08_100024_fix_pivot_table_constraints.php.
 */
return new class extends Migration
{
    /** Pivot tables whose `permission_id` FK must reference `permissions`. */
    private const PIVOTS = [
        'role_has_permissions' => 'fk_rhp_permission',
        'model_has_permissions' => 'fk_mhp_permission',
    ];

    private function permissionFk(string $table): ?object
    {
        $rows = DB::select(
            'SELECT CONSTRAINT_NAME AS name, REFERENCED_TABLE_NAME AS ref '
            . 'FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? '
            . "AND COLUMN_NAME = 'permission_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1",
            [$table]
        );

        return $rows[0] ?? null;
    }

    private function dropFkByName(string $table, string $name): void
    {
        try {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign($name));
        } catch (\Throwable $e) {
            \Log::warning("Skipping dropForeign {$name} on {$table}: " . $e->getMessage());
        }
    }

    private function safeStatement(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (\Throwable $e) {
            \Log::warning('Statement failed: ' . $e->getMessage());
        }
    }

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach (self::PIVOTS as $table => $fkName) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'permission_id')) {
                continue;
            }

            $fk = $this->permissionFk($table);

            // No FK at all (local `crm`, the test schema) → do NOT invent one;
            // this migration only *repoints* an existing misbound constraint.
            if ($fk === null) {
                continue;
            }

            // Already correctly bound → nothing to do.
            if ($fk->ref === 'permissions') {
                continue;
            }

            // Drop the misbound FK (whatever it's named / wherever it points).
            $this->dropFkByName($table, $fk->name);

            // Back up the orphaned rows BEFORE pruning them. down() is
            // intentionally irreversible, so this snapshot is the ONLY recovery
            // path if the prune removes a live grant on prod (go-live §3). This
            // prune branch never runs on clean dev/test (no misbound FK) — it
            // only fires where a stale `permissions1` binding exists, exactly
            // where the deleted rows might be real grants. Mirrors the
            // backup-table pattern of 2026_05_22_120000_sunset_legacy_permission_assignments.
            $backup = "{$table}_repoint_backup";
            $this->safeStatement("DROP TABLE IF EXISTS {$backup}");
            $this->safeStatement(
                "CREATE TABLE {$backup} AS "
                . "SELECT * FROM {$table} WHERE permission_id NOT IN (SELECT id FROM permissions)"
            );

            // Prune rows orphaned relative to the authoritative table so the
            // corrected FK can be created (mirrors 2026_04_08_100024 line 70).
            $this->safeStatement(
                "DELETE FROM {$table} WHERE permission_id NOT IN (SELECT id FROM permissions)"
            );

            // Re-add the FK against the live `permissions` table.
            try {
                Schema::table($table, function (Blueprint $t) use ($fkName): void {
                    $t->foreign('permission_id', $fkName)
                        ->references('id')->on('permissions')
                        ->onDelete('cascade');
                });
            } catch (\Throwable $e) {
                \Log::warning("Skipping re-add {$fkName} on {$table}: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: re-binding these FKs to a stale
        // `permissions1` copy would re-introduce the integrity bug. The
        // corrected state (FK -> permissions) is the only valid one.
    }
};
