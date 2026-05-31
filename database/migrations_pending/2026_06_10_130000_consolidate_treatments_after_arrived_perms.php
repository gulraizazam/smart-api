<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Apply the consultations after-arrival permission pattern to treatments.
 *
 * Before: a single generic `treatments.edit.after_arrived` override (seeded
 * by 2026_05_24_120000) plus a half-finished set of legacy underscore
 * perms (`update_treatment_service` etc.) that the update service was
 * ORed with — the wiring was confusing and only one of the three legacy
 * rows actually exists in the DB.
 *
 * After: three explicit per-field gates
 *   - treatments.edit.service.after_arrived
 *   - treatments.edit.doctor.after_arrived
 *   - treatments.edit.schedule.after_arrived
 * and the generic `treatments.edit.after_arrived` row is dropped.
 *
 * Grant mirror: any role/user that currently holds the generic perm gets
 * all three new ones automatically so revoking field access after the
 * rewrite is intentional rather than accidental.
 *
 * Idempotent + reversible. Spatie + RoleService caches are flushed so the
 * dotted catalog reads correctly without a redeploy.
 *
 * Companion migration: 2026_06_10_120000 applied the same shape to
 * consultations. Backend services use the new perm names in
 * TreatmentUpdateService + TreatmentDatatableService.
 */
return new class extends Migration
{
    private const GUARD = 'web';

    private const ADMIN_ROLES = ['Administrator', 'Super-Admin', 'Super Admin'];

    private const ROLE_SERVICE_CACHE_KEYS = [
        'roles.permissions_mapping.v2.super',
        'roles.permissions_mapping.v2.normal',
        'roles.permissions_mapping.super',
        'roles.permissions_mapping.normal',
    ];

    private const GENERIC_PERM = 'treatments.edit.after_arrived';

    /**
     * @return list<array{name: string, title: string}>
     */
    private function newPerms(): array
    {
        return [
            ['name' => 'treatments.edit.service.after_arrived',  'title' => 'Edit Service After Arrived'],
            ['name' => 'treatments.edit.doctor.after_arrived',   'title' => 'Edit Doctor After Arrived'],
            ['name' => 'treatments.edit.schedule.after_arrived', 'title' => 'Edit Schedule After Arrived'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            // Find the existing `treatments` group head so the new rows
            // share the same parent + sit in the same role-editor section.
            $groupId = (int) (Permission::where('name', 'treatments')
                ->where('main_group', 1)
                ->value('id') ?? 0);

            $newIds = [];
            foreach ($this->newPerms() as $i => $row) {
                $perm = Permission::updateOrCreate(
                    ['name' => $row['name']],
                    [
                        'title' => $row['title'],
                        'main_group' => 0,
                        'parent_id' => $groupId,
                        'status' => 1,
                        'category' => null,
                        'guard_name' => self::GUARD,
                        // Lands at the end of the existing catalog — the
                        // original migration's last sort_order was 14.
                        'sort_order' => 15 + $i,
                    ],
                );
                $newIds[] = (int) $perm->id;
            }

            $newNames = array_map(static fn ($r) => $r['name'], $this->newPerms());

            // Admin roles get every new perm — preserves "admins see
            // everything" parity with the original catalog migration.
            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($newNames);
            }

            // Grant mirror: roles/users that currently hold the generic
            // override pick up all three new per-field gates so the
            // rewrite doesn't silently revoke their effective access.
            $genericId = (int) (Permission::where('name', self::GENERIC_PERM)
                ->value('id') ?? 0);
            if ($genericId > 0) {
                $roleIds = DB::table('role_has_permissions')
                    ->where('permission_id', $genericId)
                    ->pluck('role_id')
                    ->all();
                foreach ($newIds as $newId) {
                    foreach ($roleIds as $roleId) {
                        DB::table('role_has_permissions')->updateOrInsert(
                            ['role_id' => $roleId, 'permission_id' => $newId],
                            [],
                        );
                    }
                }
                $directGrants = DB::table('model_has_permissions')
                    ->where('permission_id', $genericId)
                    ->get(['model_id', 'model_type']);
                foreach ($newIds as $newId) {
                    foreach ($directGrants as $grant) {
                        DB::table('model_has_permissions')->updateOrInsert(
                            [
                                'permission_id' => $newId,
                                'model_id' => $grant->model_id,
                                'model_type' => $grant->model_type,
                            ],
                            [],
                        );
                    }
                }

                // Drop the generic row — pivot cleanup runs via Spatie's
                // ON DELETE CASCADE FKs on role_has_permissions and
                // model_has_permissions.
                Permission::where('id', $genericId)->delete();
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $groupId = (int) (Permission::where('name', 'treatments')
                ->where('main_group', 1)
                ->value('id') ?? 0);

            // Re-create the generic row so down() truly reverses up().
            if ($groupId > 0) {
                Permission::updateOrCreate(
                    ['name' => self::GENERIC_PERM],
                    [
                        'title' => 'Edit After Arrived (override)',
                        'main_group' => 0,
                        'parent_id' => $groupId,
                        'status' => 1,
                        'category' => null,
                        'guard_name' => self::GUARD,
                        'sort_order' => 14,
                    ],
                );

                // Grant the restored generic to any role/user that
                // currently holds *all three* of the new per-field gates
                // (best-effort reconstruction — the generic was an OR of
                // the three so this preserves the same effective access).
                $newIds = Permission::whereIn('name', array_map(
                    static fn ($r) => $r['name'],
                    $this->newPerms(),
                ))->pluck('id')->all();
                $generic = Permission::where('name', self::GENERIC_PERM)->first();

                if ($generic && count($newIds) === 3) {
                    $roleIds = DB::table('role_has_permissions')
                        ->whereIn('permission_id', $newIds)
                        ->select('role_id')
                        ->groupBy('role_id')
                        ->havingRaw('COUNT(DISTINCT permission_id) = 3')
                        ->pluck('role_id')
                        ->all();
                    foreach ($roleIds as $roleId) {
                        DB::table('role_has_permissions')->updateOrInsert(
                            ['role_id' => $roleId, 'permission_id' => $generic->id],
                            [],
                        );
                    }
                }
            }

            Permission::whereIn('name', array_map(
                static fn ($r) => $r['name'],
                $this->newPerms(),
            ))->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
