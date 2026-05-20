<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hide the legacy `treatments_manage` permission group from the role
 * editor — same shape as 2026_05_22_130000 (consultations hide).
 *
 * Why: the companion migration 2026_05_24_120000 creates a new
 * `treatments.*` dotted catalog under its own group with category='Clinic'.
 * The existing `treatments_manage` row (id=484) is still the parent of
 * legacy `treatments_*` perms that some admin-v1 surfaces still gate on.
 * They can't be dropped yet — that happens when the appointments module
 * audit ships and the remaining cross-module rows are cleaned up.
 *
 * Setting status=0 on the umbrella hides the legacy "Treatments" group
 * from the role editor (the editor selects `main_group=1 AND status=1`).
 * The Gate::allows() calls in PHP keep resolving because Spatie does not
 * filter by our custom `status` column.
 *
 * Reversible: down() flips status back to 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::where('name', 'treatments_manage')->update(['status' => 0]);

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
        Permission::where('name', 'treatments_manage')->update(['status' => 1]);

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
