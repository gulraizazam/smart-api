<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Patients module — fine-grained permission catalog (`patients.<area>.<action>`).
 *
 * Adds 32 new permissions covering every action surface on the patient
 * datatable and inside the patient card (tabs + inline actions). The existing
 * legacy `patients_*` perms are kept untouched and still gate the backend
 * — this migration is additive plus a back-compat grant layer so any role
 * holding the old perm also holds the new equivalent, ready for controllers
 * to pivot in a later pass.
 *
 * Catalog parent: `patients` (existing umbrella id=169, name=patients_manage,
 * retitled as the group). New rows hang under a fresh hierarchical parent
 * `patients` so the role editor can group them visually without disturbing
 * the legacy tree.
 *
 * Side effects:
 *  - Title fix on `patients_customform_create` / `_edit` (legacy rows whose
 *    titles erroneously read "Document Create / Edit").
 *  - Soft-deprecate `patients_create` (status=0) — direct patient creation
 *    was removed from the SPA; zero remaining code references.
 *  - Admin roles (Administrator / Super-Admin / Super Admin) receive every
 *    new perm. FDM + other roles get only the new perms whose legacy
 *    equivalent they already hold (back-compat mirror).
 *  - Spatie permission cache + RoleService cache evicted.
 *
 * down() — reverses each change: removes the new perm rows + their grants,
 * restores legacy titles + status, leaves the umbrella group intact (it was
 * here before this migration).
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
     * Legacy → new permission map.
     *
     * Back-compat: any role that currently holds the key gets the value too,
     * so once controllers start gating on the new names no one suddenly loses
     * an action. Where a legacy perm doesn't exist (e.g. list / card view),
     * admin roles are the only grantees on first run.
     *
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'patients_manage' => [
                'patients.list.view',
                'patients.card.view',
                'patients.profile.view',
                'patients.activity.view',
                'patients.notes.view',
                'patients.notes.create',
                'patients.notes.pin',
            ],
            'patients_edit' => ['patients.edit'],
            'patients_destroy' => ['patients.delete'],
            'patients_active' => ['patients.activate'],
            'patients_inactive' => ['patients.deactivate'],

            'patients_assign_membership' => ['patients.membership.assign'],
            'patients_cancel_membership' => ['patients.membership.cancel'],
            'patients_add_referrals' => ['patients.referral.add'],

            'patients_document_manage' => ['patients.documents.view'],
            'patients_document_create' => ['patients.documents.upload'],
            'patients_document_edit' => ['patients.documents.edit'],
            'patients_document_destroy' => ['patients.documents.delete'],

            'view_inactive_patients' => ['patients.list.view_inactive'],
            'contact' => ['patients.list.view_contact'],

            'vouchers_manage' => [
                'patients.vouchers.view',
                'patients.vouchers.view_history',
                'patients.voucher.assign',
            ],

            'consultations_manage' => ['patients.consultations.view'],
            'appointments_consultancy' => ['patients.consultations.create'],
            'treatments_manage' => ['patients.treatments.view'],
            'treatments_services' => ['patients.treatments.create'],
            'plans_manage' => ['patients.plans.view'],
            'invoices_manage' => ['patients.invoices.view'],
            'refunds_manage' => ['patients.refunds.view'],

            // No clear legacy equivalent — admin-only on first run:
            //   patients.upload_image
            //   patients.notes.manage
        ];
    }

    /**
     * All new perms with title + parent hint. Parent is the `patients` group
     * created here. main_group flag follows the existing seeder pattern
     * (1 = group header, 0 = leaf).
     *
     * @return list<array{name: string, title: string}>
     */
    private function newPerms(): array
    {
        return [
            // List
            ['name' => 'patients.list.view',           'title' => 'List · View'],
            ['name' => 'patients.list.view_contact',   'title' => 'List · View Contact'],
            ['name' => 'patients.list.view_inactive',  'title' => 'List · Show Inactive'],

            // Card / header
            ['name' => 'patients.card.view',           'title' => 'Card · View'],
            ['name' => 'patients.edit',                'title' => 'Card · Edit'],
            ['name' => 'patients.delete',              'title' => 'Card · Delete'],
            ['name' => 'patients.activate',            'title' => 'Card · Activate'],
            ['name' => 'patients.deactivate',          'title' => 'Card · Deactivate'],
            ['name' => 'patients.upload_image',        'title' => 'Card · Upload Image'],

            // Profile tab
            ['name' => 'patients.profile.view',        'title' => 'Profile · View'],
            ['name' => 'patients.membership.assign',   'title' => 'Profile · Assign Membership'],
            ['name' => 'patients.membership.cancel',   'title' => 'Profile · Cancel Membership'],
            ['name' => 'patients.voucher.assign',      'title' => 'Profile · Assign Voucher'],
            ['name' => 'patients.referral.add',        'title' => 'Profile · Add Referral'],
            ['name' => 'patients.notes.view',          'title' => 'Profile · View Notes'],
            ['name' => 'patients.notes.create',        'title' => 'Profile · Add Note'],
            ['name' => 'patients.notes.manage',        'title' => "Profile · Manage Others' Notes"],
            ['name' => 'patients.notes.pin',           'title' => 'Profile · Pin Note'],

            // Consultations / Treatments tabs
            ['name' => 'patients.consultations.view',   'title' => 'Consultations · View'],
            ['name' => 'patients.consultations.create', 'title' => 'Consultations · New'],
            ['name' => 'patients.treatments.view',      'title' => 'Treatments · View'],
            ['name' => 'patients.treatments.create',    'title' => 'Treatments · New'],

            // Plans / Vouchers / Invoices / Refunds tabs (visibility only)
            ['name' => 'patients.plans.view',           'title' => 'Plans · View'],
            ['name' => 'patients.vouchers.view',        'title' => 'Vouchers · View'],
            ['name' => 'patients.vouchers.view_history', 'title' => 'Vouchers · View History'],
            ['name' => 'patients.invoices.view',        'title' => 'Invoices · View'],
            ['name' => 'patients.refunds.view',         'title' => 'Refunds · View'],

            // Documents tab
            ['name' => 'patients.documents.view',       'title' => 'Documents · View'],
            ['name' => 'patients.documents.upload',     'title' => 'Documents · Upload'],
            ['name' => 'patients.documents.edit',       'title' => 'Documents · Edit'],
            ['name' => 'patients.documents.delete',     'title' => 'Documents · Delete'],

            // Activity tab
            ['name' => 'patients.activity.view',        'title' => 'Activity · View'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            // Group header — new hierarchical parent (does not disturb the
            // legacy patients_manage row which has historically served as the
            // umbrella for the snake_case catalog).
            $group = Permission::updateOrCreate(
                ['name' => 'patients'],
                [
                    'title' => 'Patients',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'Patients',
                    'guard_name' => self::GUARD,
                ],
            );

            // Children
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

            // Title corrections — legacy customform rows ship with copy/paste
            // titles from the documents block ("Document Create / Edit").
            Permission::where('name', 'patients_customform_create')
                ->update(['title' => 'Custom Form Create']);
            Permission::where('name', 'patients_customform_edit')
                ->update(['title' => 'Custom Form Edit']);

            // Soft-deprecate: `patients_create` — direct patient creation was
            // removed from the SPA and the backend has zero remaining code
            // references. Status flip hides it from the role editor without
            // breaking any grant table FKs.
            Permission::where('name', 'patients_create')->update(['status' => 0]);

            // Admin roles: every new perm.
            $newNames = array_map(static fn ($r) => $r['name'], $this->newPerms());
            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($newNames);
            }

            // Back-compat mirror: for every legacy perm a role currently
            // holds, grant the matching new perm(s). Skips admin roles
            // (already covered above) to keep the loop cheap.
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

            // Cache eviction inside the txn — rollback rolls back eviction.
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            // Drop the new perms and their grants. Spatie cleans up
            // role_has_permissions automatically via FK cascade.
            $names = array_map(static fn ($r) => $r['name'], $this->newPerms());
            Permission::whereIn('name', $names)->delete();

            // Drop the group header (only if no other migration repurposed it).
            Permission::where('name', 'patients')
                ->where('main_group', 1)
                ->delete();

            // Restore legacy state.
            Permission::where('name', 'patients_customform_create')
                ->update(['title' => 'Document Create']);
            Permission::where('name', 'patients_customform_edit')
                ->update(['title' => 'Document Edit']);
            Permission::where('name', 'patients_create')->update(['status' => 1]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
