<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hide the legacy `business_closures_manage` permission group from the
 * role editor — same shape as the scheduling-shifts / treatments /
 * consultations hide migrations.
 *
 * Companion to 2026_05_26_120000 which creates the dotted
 * `business_closures.*` catalog under category='Clinic'. The legacy
 * `business_closures_*` rows stay alive at runtime so any lingering
 * Gate::allows() resolves them, but the role editor only shows the new
 * dotted group going forward.
 *
 * Reversible: down() flips status back to 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::where('name', 'business_closures_manage')->update(['status' => 0]);

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
        Permission::where('name', 'business_closures_manage')->update(['status' => 1]);

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
