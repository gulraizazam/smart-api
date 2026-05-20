<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Business Working Days module — fine-grained permission catalog
 * (`business_working_days.<action>`).
 *
 * Adds 2 new permissions for the /business-working-days SPA page.
 * Group `category='Clinic'` so it lands in the sidebar's Clinic section
 * (the sidebar nests Business Working Days under Clinic alongside
 * Scheduling Shifts and Business Closed Periods).
 *
 * Why this module is special: it was previously gated on `schedule_manage`,
 * which never existed in the permissions table. Every Gate::allows() call
 * resolved to false for non-super-admins, effectively locking the whole
 * page to admins. This migration replaces the dangling reference with a
 * real two-perm catalog; the companion code rewrite (controllers,
 * FormRequests, AppointmentPolicy, SPA) re-points every gate at the new
 * names.
 *
 * Roles seeded: admins only. Other roles get nothing — ops grants from
 * the role editor case by case. (Per the audit conversation: nobody
 * currently holds `schedule_manage`, so there's no mirror migration to
 * run.)
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

    /**
     * @return list<array{name: string, title: string}>
     */
    private function newPerms(): array
    {
        return [
            ['name' => 'business_working_days.list.view', 'title' => 'View'],
            ['name' => 'business_working_days.edit',      'title' => 'Edit (weekly config + exceptions)'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'business_working_days'],
                [
                    'title' => 'Business Working Days',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'Clinic',
                    'guard_name' => self::GUARD,
                ],
            );

            foreach ($this->newPerms() as $i => $row) {
                Permission::updateOrCreate(
                    ['name' => $row['name']],
                    [
                        'title' => $row['title'],
                        'main_group' => 0,
                        'parent_id' => $group->id,
                        'status' => 1,
                        'category' => null,
                        'guard_name' => self::GUARD,
                        'sort_order' => $i + 1,
                    ],
                );
            }

            $newNames = array_map(static fn ($r) => $r['name'], $this->newPerms());
            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($newNames);
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
            $names = array_map(static fn ($r) => $r['name'], $this->newPerms());
            Permission::whereIn('name', $names)->delete();

            Permission::where('name', 'business_working_days')
                ->where('main_group', 1)
                ->where('category', 'Clinic')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
