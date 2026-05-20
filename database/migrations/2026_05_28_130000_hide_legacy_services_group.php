<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hides the legacy `services_manage` umbrella + its children from the role
 * editor now that the dotted `services.*` catalog is in place and every
 * gate (controllers, FormRequests, policy, sidebars, SPA route) is pointed
 * at the new names by 2026_05_28_120000.
 *
 * Strategy: status=0 the legacy rows rather than deleting them. Spatie
 * grants on those rows are still honoured at runtime (the recordings
 * survive), but the role editor's `buildPermissionGroup()` only surfaces
 * `status=1` rows, so the duplicate "Services" section disappears.
 *
 * `view_inactive_services` is also flipped off — its successor
 * `services.list.view_inactive` was mirrored in Migration 1, and its
 * lone caller (ServiceHelper::canViewInactive + GeneralFunctions
 * inactive-toggle helpers) was repointed in the same commit.
 *
 * Reversible: `down()` flips status back to 1 with no data loss.
 */
return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function legacyNames(): array
    {
        return [
            'services_manage',
            'services_create',
            'services_edit',
            'services_active',
            'services_inactive',
            'services_destroy',
            'services_sort',
            'services_duplicate',
            'services_detail',
            'services_export',
            'view_inactive_services',
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
