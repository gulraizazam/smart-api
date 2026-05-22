<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Leads module — fine-grained permission catalog (`leads.<area>.<action>`).
 *
 * Adds 15 new permissions covering every action surface on the leads list,
 * tab segmentation (Active / Junk), the lead drawer, and the inline actions
 * (import / export / comment / SMS / convert). Existing legacy `leads_*`
 * perms are kept untouched and still gate the backend — this migration is
 * additive plus a back-compat grant layer so any role holding the old perm
 * also holds the new equivalent, ready for the controller rewrite.
 *
 * Mirrors the structure of 2026_05_20_120000_add_patients_module_permissions
 * for the patients module — same idempotent `Permission::updateOrCreate` +
 * role-grant mirror + admin auto-grant + Spatie/RoleService cache eviction.
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
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'leads_manage' => [
                'leads.list.view',
                'leads.detail.view',
            ],
            'leads_create' => ['leads.create'],
            'leads_edit' => ['leads.edit'],
            'leads_destroy' => ['leads.delete'],
            'leads_import' => ['leads.import'],
            'leads_export' => ['leads.export'],
            'leads_lead_status' => ['leads.update_status'],
            'leads_city' => ['leads.update_city'],
            'leads_convert' => ['leads.convert'],
            'leads_junk' => ['leads.list.view_junk'],
            'view_inactive_leads' => ['leads.list.view_inactive'],
            'contact' => ['leads.list.view_contact'],
            // No clear legacy equivalent — admin-only on first run:
            //   leads.comment.create
            //   leads.send_sms
        ];
    }

    /**
     * @return list<array{name: string, title: string}>
     */
    private function newPerms(): array
    {
        return [
            // List
            ['name' => 'leads.list.view',           'title' => 'List · View'],
            ['name' => 'leads.list.view_contact',   'title' => 'List · View Contact'],
            ['name' => 'leads.list.view_inactive',  'title' => 'List · Show Inactive'],
            ['name' => 'leads.list.view_junk',      'title' => 'List · View Junk Tab'],
            // Detail
            ['name' => 'leads.detail.view',         'title' => 'Detail · View'],
            // CRUD
            ['name' => 'leads.create',              'title' => 'Create'],
            ['name' => 'leads.edit',                'title' => 'Edit'],
            ['name' => 'leads.delete',              'title' => 'Delete'],
            // Bulk + transport
            ['name' => 'leads.import',              'title' => 'Import'],
            ['name' => 'leads.export',              'title' => 'Export'],
            // Inline actions
            ['name' => 'leads.update_status',       'title' => 'Update Status (incl. Junk)'],
            ['name' => 'leads.update_city',         'title' => 'Update City'],
            ['name' => 'leads.convert',             'title' => 'Convert to Patient'],
            ['name' => 'leads.comment.create',      'title' => 'Add Comment'],
            ['name' => 'leads.send_sms',            'title' => 'Send SMS'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'leads'],
                [
                    'title' => 'Leads',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'Leads',
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

            // Admin roles: every new perm.
            $newNames = array_map(static fn ($r) => $r['name'], $this->newPerms());
            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($newNames);
            }

            // Back-compat mirror: for every legacy perm a non-admin role
            // currently holds, grant the matching new perm(s).
            $adminRoleIds = Role::whereIn('name', self::ADMIN_ROLES)
                ->pluck('id')
                ->all();
            foreach ($this->legacyToNewMap() as $legacy => $news) {
                $legacyPerm = Permission::where('name', $legacy)
                    ->where('guard_name', self::GUARD)
                    ->first();
                if (! $legacyPerm) {
                    continue;
                }
                $roleIds = DB::table('role_has_permissions')
                    ->where('permission_id', $legacyPerm->id)
                    ->whereNotIn('role_id', $adminRoleIds)
                    ->pluck('role_id')
                    ->all();
                foreach (Role::whereIn('id', $roleIds)->get() as $role) {
                    $role->givePermissionTo($news);
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

            Permission::where('name', 'leads')
                ->where('main_group', 1)
                ->where('category', 'Leads')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
