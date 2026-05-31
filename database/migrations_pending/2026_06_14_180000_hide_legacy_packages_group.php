<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hides the legacy `packages_*` group from the role editor.
 *
 * The dotted `packages.*` catalog (group head id=899, 9 children) has
 * been in place for some time and the legacy-to-new grant bridge is
 * already complete: every role holding `packages_manage` already holds
 * `packages.list.view`; the 2 admin roles holding the action perms
 * (create/edit/destroy/active/inactive) hold the dotted equivalents
 * via the admin loop. So unlike most other modules in this rewrite,
 * the `packages_*` legacy rows are now genuinely redundant — no
 * remaining bridging work, just role-editor noise.
 *
 * Strategy: flip status=0 on the 7 legacy `packages_*` rows so they
 * stop appearing in the role editor. Spatie grants survive (status is
 * a UI filter, not a gate-check input), so any controller still
 * calling `Gate::allows('packages_manage')` continues to work until
 * the consumer rewrite finishes.
 *
 * Reversible — `down()` flips status back to 1.
 *
 * NOTE: this is currently the only module in the new dotted-catalog
 * series where the bridge was already complete and the legacy rows
 * are safe to hide today. Every other module migrated in this session
 * still has consumers on the underscore names; their `hide_legacy_*`
 * companions get written one-by-one after the per-module controller
 * rewrites land.
 */
return new class extends Migration
{
    private const ROLE_SERVICE_CACHE_KEYS = [
        'roles.permissions_mapping.v2.super',
        'roles.permissions_mapping.v2.normal',
        'roles.permissions_mapping.super',
        'roles.permissions_mapping.normal',
    ];

    /**
     * @return list<string>
     */
    private function legacyNames(): array
    {
        return [
            'packages_manage',
            'packages_create',
            'packages_edit',
            'packages_destroy',
            'packages_active',
            'packages_inactive',
            'packages_sort',
        ];
    }

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
