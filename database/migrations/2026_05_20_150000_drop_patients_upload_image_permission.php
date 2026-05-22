<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Drop `patients.upload_image` — created speculatively in
 * 2026_05_20_120000_add_patients_module_permissions because the legacy
 * PatientController exposed a storeImage endpoint, but the SPA patient
 * card has no avatar-upload UI (the avatar renders initials via
 * AvatarFallback). The endpoint is still wired for staff/user image
 * uploads via PatientImageRequest; those callers gate on `users_manage`,
 * so this row had no real consumer.
 *
 * Backend updates accompanying this migration:
 *   - app/Http/Controllers/Api/PatientController.php::storeImage   →
 *     gate reduced to `users_manage`
 *   - app/Http/Controllers/Admin/PatientsController.php::imageindex →
 *     gate reduced to `users_manage`
 *
 * down() recreates the row under the `patients` group and restores grants
 * to any role currently holding `users_manage` (matching the up()
 * fallback chain that admin / FDM roles had via the original mirror map).
 */
return new class extends Migration
{
    private const GUARD = 'web';

    public function up(): void
    {
        DB::transaction(function (): void {
            Permission::where('name', 'patients.upload_image')->delete();

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
                ['name' => 'patients.upload_image'],
                [
                    'title' => 'Card · Upload Image',
                    'main_group' => 0,
                    'parent_id' => $parentId,
                    'status' => 1,
                    'guard_name' => self::GUARD,
                ],
            );

            // Restore grants — every role that holds users_manage gets it
            // back, mirroring the storeImage fallback chain.
            $roleIds = DB::table('role_has_permissions as rhp')
                ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
                ->where('p.name', 'users_manage')
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
