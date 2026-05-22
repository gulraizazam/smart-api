<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hides the legacy `membershiptypes_*` umbrella from the role editor
 * now that the dotted `membership_types.*` catalog is in place and
 * every gate (both controllers, 3 FormRequests, 3 Blade files, SPA
 * route + sidebar) is pointed at the new names by 2026_06_04_120000.
 *
 * Strategy: status=0 the legacy rows. Spatie grants survive — `status`
 * only affects the role-editor UI filter. Reversible — `down()` flips
 * status back to 1.
 *
 * Historically only `membershiptypes_manage` was actually seeded; the
 * other 5 rows are no-ops on most environments but included for
 * idempotency in case any DB created them by hand.
 */
return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function legacyNames(): array
    {
        return [
            'membershiptypes_manage',
            'membershiptypes_create',
            'membershiptypes_edit',
            'membershiptypes_destroy',
            'membershiptypes_active',
            'membershiptypes_inactive',
        ];
    }

    private const ROLE_SERVICE_CACHE_KEYS = [
        'roles.permissions_mapping.v2.super',
        'roles.permissions_mapping.v2.normal',
        'roles.permissions_mapping.super',
        'roles.permissions_mapping.normal',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('permissions')
                ->whereIn('name', $this->legacyNames())
                ->update(['status' => 0]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::table('permissions')
                ->whereIn('name', $this->legacyNames())
                ->update(['status' => 1]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
