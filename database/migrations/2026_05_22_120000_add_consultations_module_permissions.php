<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Consultations module — fine-grained permission catalog
 * (`consultations.<area>.<action>`).
 *
 * Adds 18 new permissions covering every action surface on the consultations
 * list / calendar, drawer, invoice flow, and field-level edit-after-arrival
 * overrides. Mirrors the structure of 2026_05_20_120000 (patients) and
 * 2026_05_21_120000 (leads).
 *
 * IMPORTANT — partial migration: the legacy `appointments_*` perms shared
 * with the treatments module + admin-v1 appointments controller stay alive
 * and continue to gate those surfaces. The companion migration
 * 2026_05_22_130000 hides the legacy `consultations_manage` group from the
 * role editor so users only see the new dotted catalog. The legacy rows
 * are dropped when the treatments module audit ships.
 *
 * Back-compat mirror map (legacy → new) used to seed grants on existing
 * roles. Anyone holding the legacy perm picks up the new dotted equivalent
 * automatically so the rewrite is non-breaking.
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
            // Group umbrella — gates the list page in the legacy admin.
            'consultations_manage' => [
                'consultations.list.view',
                'consultations.detail.view',
                'consultations.copy_whatsapp',
                'consultations.view_activity',
                'consultations.view_sms_logs',
            ],
            // Consultation-specific CRUD
            'appointments_consultancy' => [
                'consultations.create',
                'consultations.edit',
                'consultations.delete',
                'consultations.reschedule',
            ],
            'appointments_edit' => ['consultations.edit'],
            'appointments_destroy' => ['consultations.delete'],
            'appointments_appointment_status' => ['consultations.update_status'],
            'appointments_status_update' => ['consultations.update_status'],
            'appointments_export' => ['consultations.export'],
            'appointments_export_all' => ['consultations.export'],
            'appointments_export_today' => ['consultations.export'],
            'appointments_export_this_month' => ['consultations.export'],
            'appointments_invoice' => ['consultations.invoice.create'],
            'appointments_invoice_display' => ['consultations.invoice.view'],
            // Granular edit-after-arrival overrides
            'edit_after_arrived' => ['consultations.edit.after_arrived'],
            'update_consultation_service' => ['consultations.edit.service'],
            'update_consultation_doctor' => ['consultations.edit.doctor'],
            'update_consultation_schedule' => ['consultations.edit.schedule'],
            // Phone visibility — generic `contact` perm
            'contact' => ['consultations.list.view_contact'],
        ];
    }

    /**
     * @return list<array{name: string, title: string}>
     */
    private function newPerms(): array
    {
        return [
            // List
            ['name' => 'consultations.list.view',          'title' => 'List · View'],
            ['name' => 'consultations.list.view_contact',  'title' => 'List · View Contact'],
            // Detail
            ['name' => 'consultations.detail.view',        'title' => 'Detail · View'],
            // CRUD
            ['name' => 'consultations.create',             'title' => 'Create'],
            ['name' => 'consultations.edit',               'title' => 'Edit'],
            ['name' => 'consultations.delete',             'title' => 'Delete'],
            // Status + schedule
            ['name' => 'consultations.update_status',      'title' => 'Update Status'],
            ['name' => 'consultations.reschedule',         'title' => 'Reschedule'],
            // Bulk + transport
            ['name' => 'consultations.export',             'title' => 'Export'],
            // Inline actions
            ['name' => 'consultations.copy_whatsapp',      'title' => 'Copy WhatsApp Message'],
            ['name' => 'consultations.view_sms_logs',      'title' => 'View SMS Logs'],
            ['name' => 'consultations.view_activity',      'title' => 'View Activity Logs'],
            // Invoice
            ['name' => 'consultations.invoice.view',       'title' => 'Invoice · View'],
            ['name' => 'consultations.invoice.create',     'title' => 'Invoice · Create / Save'],
            // Edit-after-arrival (granular)
            ['name' => 'consultations.edit.after_arrived', 'title' => 'Edit After Arrived (override)'],
            ['name' => 'consultations.edit.service',       'title' => 'Edit · Service'],
            ['name' => 'consultations.edit.doctor',        'title' => 'Edit · Doctor'],
            ['name' => 'consultations.edit.schedule',      'title' => 'Edit · Schedule'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'consultations'],
                [
                    'title' => 'Consultations',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'Consultations',
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

            // Back-compat mirror.
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

            Permission::where('name', 'consultations')
                ->where('main_group', 1)
                ->where('category', 'Consultations')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
