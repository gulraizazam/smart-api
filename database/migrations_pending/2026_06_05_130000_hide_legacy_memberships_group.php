<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hides the legacy `memberships_*` umbrella + `membership_codes_manage`
 * from the role editor now that the dotted `memberships.*` catalog is
 * in place and every gate (both controllers, MembershipPolicy,
 * MembershipCodeController, 6 Membership FormRequests, sidebar Blades,
 * memberships/index Blade, SPA route + sidebar) is pointed at the new
 * names by 2026_06_05_120000.
 *
 * Strategy: status=0 the legacy rows. Spatie grants survive — `status`
 * only affects the role-editor UI filter. Reversible — `down()` flips
 * status back to 1.
 *
 * Note: `memberships_manage` was also mapped from the Patients audit
 * (migration 2026_05_20_120000) to `patients.membership.assign` /
 * `patients.membership.cancel` for the patient-card scope — those
 * grants are independent of this cleanup and remain intact.
 */
return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function legacyNames(): array
    {
        return [
            'memberships_manage',
            'memberships_create',
            'memberships_edit',
            'memberships_destroy',
            'memberships_active',
            'memberships_inactive',
            'memberships_sort',
            'memberships_import',
            'memberships_export',
            'membership_codes_manage',
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
