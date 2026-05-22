<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sunset the legacy snake_case permission catalog (parent_id=157,
 * status=0) at the role-assignment level. Every gate check in the
 * codebase still reads the legacy slugs, but the role editor only
 * surfaces the new dotted catalog (parent_id=909, status=1). That meant
 * toggling a permission off in the UI was a no-op for any role seeded
 * before the dotted catalog existed — the legacy row in
 * `role_has_permissions` still granted the perm and the controller's
 * `Gate::allows('plans_cash_edit')` kept passing.
 *
 * This migration brings every role to a single source of truth:
 *
 *   1. Recreate two dotted slugs that were deleted earlier or never
 *      existed but are needed for the backfill: `plans.cash.edit`,
 *      `packages.delete`.
 *
 *   2. For every (role, legacy_slug) pair where the role does not also
 *      hold the dotted equivalent, grant the dotted slug. No role
 *      loses an effective permission — the alias bridge in
 *      App\Support\PermissionAliasMap maps dotted ↔ legacy at gate-check
 *      time, so `Gate::allows('plans_cash_edit')` keeps passing for
 *      anyone who now holds `plans.cash.edit` only.
 *
 *   3. Strip every `role_has_permissions` row whose permission name is
 *      a legacy key in the alias map. After this, role-editor toggles
 *      against the dotted catalog actually take effect.
 *
 * The legacy `permissions` table rows themselves are kept — the alias
 * map still references both names, deleting them risks orphan FK
 * scenarios in audit/log tables, and nothing in the codebase relies on
 * the row's existence once no role grants it.
 *
 * Rollback: the `down()` method re-inserts every (role_id, perm_id)
 * row deleted in `up()` from the backup recorded in
 * `permission_sunset_backup`.
 */
return new class extends Migration {
    /**
     * Snapshot of PermissionAliasMap::MAP at migration time. Inlined so
     * the migration's behaviour stays stable even if the map evolves.
     *
     * @var array<string, string>
     */
    private const ALIAS_MAP = [
        // ── Plans ─────────────────────────────────────────
        'plans_manage'                 => 'plans.list.view',
        'plans_create'                 => 'plans.create',
        'plans_edit'                   => 'plans.edit',
        'plans_destroy'                => 'plans.destroy',
        'plans_active'                 => 'plans.activate',
        'plans_inactive'               => 'plans.deactivate',
        'plans_log'                    => 'plans.log.view',
        'plans_log_excel'              => 'plans.log.export',
        'plans_sms_log'                => 'plans.sms_log.view',
        'plans_cash_edit'              => 'plans.cash.edit',
        'plans_cash_delete'            => 'plans.cash.delete',
        'plans_cash_edit_amount'       => 'plans.cash.edit_amount',
        'plans_cash_edit_date'         => 'plans.cash.edit_date',
        'plans_cash_edit_payment_mode' => 'plans.cash.edit_payment_mode',
        'plans_edit_sold_by'           => 'plans.sold_by.edit',
        'plans_service_delete'         => 'plans.service.delete',
        'view_inactive_plans'          => 'plans.list.view_inactive',

        // ── Packages ──────────────────────────────────────
        'packages_manage'  => 'packages.detail.view',
        'packages_create'  => 'packages.create',
        'packages_edit'    => 'packages.edit',
        'packages_destroy' => 'packages.delete',
        'packages_active'  => 'packages.activate',
        'packages_inactive'=> 'packages.deactivate',

        // ── Discounts ─────────────────────────────────────
        'discounts_manage'        => 'discounts.list.view',
        'discounts_create'        => 'discounts.create',
        'discounts_edit'          => 'discounts.edit',
        'discounts_destroy'       => 'discounts.destroy',
        'discounts_active'        => 'discounts.activate',
        'discounts_inactive'      => 'discounts.deactivate',
        'discounts_allocate'      => 'discounts.allocate',
        'view_inactive_discounts' => 'discounts.list.view_inactive',
    ];

    /**
     * Dotted slugs that may need to be re-created in the `permissions`
     * table. Parent_id 909 (plans), 901 (packages) — discovered via the
     * existing rows on this DB. `sort_order` is appended to whatever the
     * sibling rows already use so the row sorts at the end of its group.
     *
     * @var array<int, array{name: string, title: string, parent_id: int}>
     */
    private const DOTTED_TO_CREATE_IF_MISSING = [
        ['name' => 'plans.cash.edit',  'title' => 'Edit cash payment',       'parent_id' => 909],
        ['name' => 'packages.delete',  'title' => 'Delete',                  'parent_id' => 901],
    ];

    public function up(): void
    {
        // Backup table — populated inside the transaction so down() can
        // reconstruct the exact deletion set. Idempotent create.
        if (! DB::getSchemaBuilder()->hasTable('permission_sunset_backup')) {
            DB::statement('CREATE TABLE permission_sunset_backup (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_id INT NOT NULL,
                permission_id INT NOT NULL,
                permission_name VARCHAR(255) NOT NULL,
                migration_ran_at DATETIME NOT NULL,
                INDEX (role_id),
                INDEX (permission_id)
            )');
        }

        DB::transaction(function (): void {
            $runAt = Carbon::now();

            // ── Step 1: ensure dotted rows exist ──────────────
            foreach (self::DOTTED_TO_CREATE_IF_MISSING as $row) {
                $exists = DB::table('permissions')->where('name', $row['name'])->exists();
                if ($exists) {
                    continue;
                }
                // Mirror sibling row shape: guard_name='web', status=1
                // (visible in role editor), main_group=0, sort_order high
                // enough not to collide.
                DB::table('permissions')->insert([
                    'name'        => $row['name'],
                    'title'       => $row['title'],
                    'guard_name'  => 'web',
                    'parent_id'   => $row['parent_id'],
                    'main_group'  => 0,
                    'status'      => 1,
                    'sort_order'  => 99,
                    'category'    => null,
                    'created_at'  => $runAt,
                    'updated_at'  => $runAt,
                ]);
                Log::info('LegacyPermSunset: created dotted slug', ['name' => $row['name']]);
            }

            // Build name → id lookups for both catalogs after Step 1.
            $allNames = array_unique(array_merge(
                array_keys(self::ALIAS_MAP),
                array_values(self::ALIAS_MAP),
            ));
            $idByName = DB::table('permissions')
                ->whereIn('name', $allNames)
                ->pluck('id', 'name')
                ->all();

            // ── Step 2: backfill dotted for any role-with-only-legacy ──
            $backfilled = 0;
            foreach (self::ALIAS_MAP as $legacy => $dotted) {
                $legacyId = $idByName[$legacy] ?? null;
                $dottedId = $idByName[$dotted] ?? null;
                if ($legacyId === null || $dottedId === null) {
                    Log::warning('LegacyPermSunset: alias pair missing in DB', [
                        'legacy' => $legacy, 'dotted' => $dotted,
                    ]);
                    continue;
                }

                // Roles that hold the legacy but NOT the dotted.
                $rolesNeedingBackfill = DB::table('role_has_permissions as rl')
                    ->leftJoin('role_has_permissions as rd', function ($join) use ($dottedId) {
                        $join->on('rl.role_id', '=', 'rd.role_id')
                             ->where('rd.permission_id', '=', $dottedId);
                    })
                    ->where('rl.permission_id', $legacyId)
                    ->whereNull('rd.role_id')
                    ->pluck('rl.role_id')
                    ->unique()
                    ->all();

                foreach ($rolesNeedingBackfill as $roleId) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $dottedId,
                        'role_id'       => $roleId,
                    ]);
                    $backfilled++;
                }
            }
            Log::info('LegacyPermSunset: backfilled dotted grants', ['count' => $backfilled]);

            // ── Step 3: snapshot and delete legacy role grants ────
            $legacyIds = array_values(array_intersect_key(
                $idByName,
                array_flip(array_keys(self::ALIAS_MAP)),
            ));

            // Snapshot first so down() can restore.
            $toDelete = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->whereIn('role_has_permissions.permission_id', $legacyIds)
                ->select('role_has_permissions.role_id', 'role_has_permissions.permission_id', 'permissions.name')
                ->get();

            foreach ($toDelete as $row) {
                DB::table('permission_sunset_backup')->insert([
                    'role_id'          => $row->role_id,
                    'permission_id'    => $row->permission_id,
                    'permission_name'  => $row->name,
                    'migration_ran_at' => $runAt,
                ]);
            }

            $deleted = DB::table('role_has_permissions')
                ->whereIn('permission_id', $legacyIds)
                ->delete();

            Log::info('LegacyPermSunset: stripped legacy role grants', [
                'deleted_rows' => $deleted,
                'backup_table' => 'permission_sunset_backup',
                'ran_at'       => $runAt->toIso8601String(),
            ]);

            // Same one-shot cleanup for direct user grants — same alias
            // semantics, much smaller blast radius (rare in practice).
            $userBackfill = 0;
            foreach (self::ALIAS_MAP as $legacy => $dotted) {
                $legacyId = $idByName[$legacy] ?? null;
                $dottedId = $idByName[$dotted] ?? null;
                if ($legacyId === null || $dottedId === null) {
                    continue;
                }
                $usersNeedingBackfill = DB::table('model_has_permissions as rl')
                    ->leftJoin('model_has_permissions as rd', function ($join) use ($dottedId) {
                        $join->on('rl.model_id', '=', 'rd.model_id')
                             ->on('rl.model_type', '=', 'rd.model_type')
                             ->where('rd.permission_id', '=', $dottedId);
                    })
                    ->where('rl.permission_id', $legacyId)
                    ->whereNull('rd.model_id')
                    ->select('rl.model_id', 'rl.model_type')
                    ->distinct()
                    ->get();

                foreach ($usersNeedingBackfill as $row) {
                    DB::table('model_has_permissions')->insertOrIgnore([
                        'permission_id' => $dottedId,
                        'model_type'    => $row->model_type,
                        'model_id'      => $row->model_id,
                    ]);
                    $userBackfill++;
                }
            }
            DB::table('model_has_permissions')->whereIn('permission_id', $legacyIds)->delete();
            Log::info('LegacyPermSunset: cleaned direct user grants', ['backfilled' => $userBackfill]);
        });

        // Flush Spatie's permission cache so the next request sees the
        // new state — without this, in-flight workers serve stale grants
        // for up to 24h (Spatie's default TTL).
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permission_sunset_backup')) {
            return;
        }

        DB::transaction(function (): void {
            // Replay the most recent run only. If the migration was applied
            // multiple times, prior backups stay archived.
            $latest = DB::table('permission_sunset_backup')
                ->orderByDesc('migration_ran_at')
                ->limit(1)
                ->value('migration_ran_at');

            if ($latest === null) {
                return;
            }

            $rows = DB::table('permission_sunset_backup')
                ->where('migration_ran_at', $latest)
                ->get(['role_id', 'permission_id']);

            foreach ($rows as $row) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'role_id'       => $row->role_id,
                    'permission_id' => $row->permission_id,
                ]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
