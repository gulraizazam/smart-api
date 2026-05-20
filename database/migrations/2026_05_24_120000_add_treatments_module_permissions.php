<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Treatments module — fine-grained permission catalog
 * (`treatments.<area>.<action>`).
 *
 * Adds 14 new permissions covering the treatments list / calendar, drawer,
 * invoice flow, and edit-after-arrival override. Same shape as the patients,
 * leads, and consultations catalogs.
 *
 * Group `category` is set to `Clinic` so the role editor places this group
 * inside the sidebar's Clinic section, alongside Patients / Leads /
 * Consultations.
 *
 * IMPORTANT — partial migration: legacy `treatments_*` and shared
 * `appointments_*` perms stay alive because they're still gating the
 * admin-v1 appointments controller, AppointmentInvoiceController,
 * AppointmentExportController, AppointmentPolicy and various Blade views.
 * The companion migration 2026_05_24_130000 hides the legacy
 * `treatments_manage` group from the role editor so users only see the
 * new dotted group. Legacy rows are dropped when the appointments audit
 * lands.
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
            // Group umbrella + list scope
            'treatments_manage' => [
                'treatments.list.view',
                'treatments.detail.view',
                'treatments.copy_whatsapp',
                'treatments.view_sms_logs',
            ],
            // Write-side
            'treatments_services' => [
                'treatments.create',
                'treatments.edit',
                'treatments.delete',
                'treatments.reschedule',
            ],
            'treatments_edit' => ['treatments.edit'],
            'treatments_destroy' => ['treatments.delete'],
            // Status
            'treatments_appointment_status' => ['treatments.update_status'],
            'appointments_appointment_status' => ['treatments.update_status'],
            'appointments_status_update' => ['treatments.update_status'],
            // Export
            'treatments_export' => ['treatments.export'],
            'appointments_export' => ['treatments.export'],
            'appointments_export_all' => ['treatments.export'],
            'appointments_export_today' => ['treatments.export'],
            'appointments_export_this_month' => ['treatments.export'],
            // Invoice
            'treatments_invoice' => ['treatments.invoice.create'],
            'appointments_invoice' => ['treatments.invoice.create'],
            'treatments_invoice_display' => ['treatments.invoice.view'],
            'appointments_invoice_display' => ['treatments.invoice.view'],
            // Edit-after-arrived
            'treatments_edit_after_arrived' => ['treatments.edit.after_arrived'],
            // Contact (phone visibility)
            'contact' => ['treatments.list.view_contact'],
        ];
    }

    /**
     * @return list<array{name: string, title: string}>
     */
    private function newPerms(): array
    {
        return [
            // List
            ['name' => 'treatments.list.view',          'title' => 'List · View'],
            ['name' => 'treatments.list.view_contact',  'title' => 'List · View Contact'],
            // Detail
            ['name' => 'treatments.detail.view',        'title' => 'Detail · View'],
            // CRUD
            ['name' => 'treatments.create',             'title' => 'Create'],
            ['name' => 'treatments.edit',               'title' => 'Edit'],
            ['name' => 'treatments.delete',             'title' => 'Delete'],
            // Status + schedule
            ['name' => 'treatments.update_status',      'title' => 'Update Status'],
            ['name' => 'treatments.reschedule',         'title' => 'Reschedule'],
            // Export
            ['name' => 'treatments.export',             'title' => 'Export'],
            // Inline
            ['name' => 'treatments.copy_whatsapp',      'title' => 'Copy WhatsApp Message'],
            ['name' => 'treatments.view_sms_logs',      'title' => 'View SMS Logs'],
            // Invoice
            ['name' => 'treatments.invoice.view',       'title' => 'Invoice · View'],
            ['name' => 'treatments.invoice.create',     'title' => 'Invoice · Create / Save'],
            // Override
            ['name' => 'treatments.edit.after_arrived', 'title' => 'Edit After Arrived (override)'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'treatments'],
                [
                    'title' => 'Treatments',
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

            Permission::where('name', 'treatments')
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
