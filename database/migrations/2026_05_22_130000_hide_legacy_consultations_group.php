<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hide the legacy `consultations_manage` permission group from the role
 * editor — same shape as 2026_05_20_130000 (patients group hide).
 *
 * Why: 2026_05_22_120000_add_consultations_module_permissions creates a
 * new dotted catalog (`consultations.*`) under a separate group. The
 * existing `consultations_manage` row (id=726) is still the parent of
 * many legacy `appointments_*` perms that are SHARED with the treatments
 * module and the admin-v1 appointments controller. We cannot drop them
 * yet — that happens when the treatments + appointments modules ship
 * their own dotted catalogs. Until then, flip status=0 on the umbrella
 * so the role editor shows only the new dotted group; gates keep
 * resolving because Spatie doesn't filter by our custom `status` column.
 *
 * Reversible: down() flips status back to 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::where('name', 'consultations_manage')->update(['status' => 0]);

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
    }

    public function down(): void
    {
        Permission::where('name', 'consultations_manage')->update(['status' => 1]);

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
    }
};
