<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Drop `patients.membership.cancel` — speculative perm with no UI surface
 * in the patient module. The actual cancel-membership action lives in the
 * Memberships module (Api\MembershipsController::cancelMembership, route
 * POST /api/memberships/cancel) and will be re-introduced there with its
 * own naming when that module is audited.
 *
 * Same shape as 2026_05_20_150000 (drop_patients_upload_image_permission):
 * row delete + cache flush; down() recreates the row under the `patients`
 * group and grants it back to every role that holds the legacy
 * vouchers/memberships management perm chain.
 */
return new class extends Migration
{
    private const GUARD = 'web';

    public function up(): void
    {
        DB::transaction(function (): void {
            Permission::where('name', 'patients.membership.cancel')->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (
                [
                    'roles.permissions_mapping.v2.super',
                    'roles.permissions_mapping.v2.normal',
                    'roles.permissions_mapping.super',
                    'roles.permissions_mapping.normal',
                ] as $key
            ) {
                Cache::forget($key);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $parentId = (int) (Permission::where('name', 'patients')
                ->where('main_group', 1)
                ->value('id') ?? 0);

            $perm = Permission::firstOrCreate(
                ['name' => 'patients.membership.cancel'],
                [
                    'title' => 'Profile · Cancel Membership',
                    'main_group' => 0,
                    'parent_id' => $parentId,
                    'status' => 1,
                    'guard_name' => self::GUARD,
                ],
            );

            // Restore grants — every role with patients.membership.assign
            // gets the cancel back (paired permissions in the original
            // catalog).
            $roleIds = DB::table('role_has_permissions as rhp')
                ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
                ->where('p.name', 'patients.membership.assign')
                ->distinct()
                ->pluck('rhp.role_id')
                ->all();

            if ($roleIds !== []) {
                $rows = array_map(
                    static fn (int $rid) => ['role_id' => $rid, 'permission_id' => $perm->id],
                    array_map('intval', $roleIds),
                );
                DB::table('role_has_permissions')->insertOrIgnore($rows);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (
                [
                    'roles.permissions_mapping.v2.super',
                    'roles.permissions_mapping.v2.normal',
                    'roles.permissions_mapping.super',
                    'roles.permissions_mapping.normal',
                ] as $key
            ) {
                Cache::forget($key);
            }
        });
    }
};
