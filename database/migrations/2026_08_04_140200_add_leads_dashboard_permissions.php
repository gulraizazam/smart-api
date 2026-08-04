<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission slugs for the new leads reporting dashboard + the admin CRUD
 * that manages lead-department reference data.
 *
 *   dashboard.marketing.leads_overview.view    — KPI row (total/converted/junk/revenue)
 *   dashboard.marketing.leads_leaderboard.view — agent leaderboard panel
 *   dashboard.marketing.leads_revenue.view     — lead-attributed revenue panel
 *   dashboard.marketing.leads_funnel.view      — funnel + time-metrics panels
 *   leads.departments.manage                    — CRUD on the lead_departments table
 *
 * Grants: Administrator / Super-Admin / Super Admin get everything on first run.
 * Back-compat mirror: any role currently holding
 * `dashboard.marketing.lead_conversion.view` (which was already the "trusted to
 * see lead reports" gate) also gets the four new marketing gates — no new
 * permission surface for existing users.
 *
 * Mirrors the structure of 2026_05_21_120000_add_leads_module_permissions.
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

    /** @return list<array{name: string, title: string, group: string}> */
    private function newPerms(): array
    {
        return [
            ['name' => 'dashboard.marketing.leads_overview.view',    'title' => 'Marketing · Leads Overview',    'group' => 'dashboard'],
            ['name' => 'dashboard.marketing.leads_leaderboard.view', 'title' => 'Marketing · Agent Leaderboard', 'group' => 'dashboard'],
            ['name' => 'dashboard.marketing.leads_revenue.view',     'title' => 'Marketing · Lead Revenue',      'group' => 'dashboard'],
            ['name' => 'dashboard.marketing.leads_funnel.view',      'title' => 'Marketing · Lead Funnel',       'group' => 'dashboard'],
            ['name' => 'leads.departments.manage',                    'title' => 'Departments · Manage',          'group' => 'leads'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $dashboardGroup = $this->ensureGroup('dashboard', 'Dashboard');
            $leadsGroup = $this->ensureGroup('leads', 'Leads');

            $groups = ['dashboard' => $dashboardGroup, 'leads' => $leadsGroup];

            foreach ($this->newPerms() as $i => $row) {
                Permission::updateOrCreate(
                    ['name' => $row['name']],
                    [
                        'title' => $row['title'],
                        'main_group' => 0,
                        'parent_id' => $groups[$row['group']]->id,
                        'status' => 1,
                        'category' => null,
                        'guard_name' => self::GUARD,
                        'sort_order' => 200 + $i,
                    ],
                );
            }

            // Admin roles get everything.
            $newNames = array_map(static fn ($r) => $r['name'], $this->newPerms());
            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($newNames);
            }

            // Back-compat: mirror onto any role holding the existing lead-conversion gate.
            $adminRoleIds = Role::whereIn('name', self::ADMIN_ROLES)->pluck('id')->all();
            $legacy = Permission::where('name', 'dashboard.marketing.lead_conversion.view')
                ->where('guard_name', self::GUARD)
                ->first();
            if ($legacy) {
                $roleIds = DB::table('role_has_permissions')
                    ->where('permission_id', $legacy->id)
                    ->whereNotIn('role_id', $adminRoleIds)
                    ->pluck('role_id')
                    ->all();
                $marketingNew = array_values(array_filter(
                    $newNames,
                    static fn ($n) => str_starts_with($n, 'dashboard.marketing.'),
                ));
                foreach (Role::whereIn('id', $roleIds)->get() as $role) {
                    $role->givePermissionTo($marketingNew);
                }
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

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }

    private function ensureGroup(string $name, string $title): Permission
    {
        $existing = Permission::where('name', $name)->where('main_group', 1)->first();
        if ($existing) {
            return $existing;
        }
        return Permission::updateOrCreate(
            ['name' => $name],
            [
                'title' => $title,
                'main_group' => 1,
                'parent_id' => 0,
                'status' => 1,
                'category' => $title,
                'guard_name' => self::GUARD,
            ],
        );
    }
};
