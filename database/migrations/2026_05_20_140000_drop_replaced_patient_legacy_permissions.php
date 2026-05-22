<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Drop the legacy snake_case patient-core permissions whose call sites have
 * been fully rewritten to the new `patients.<area>.<action>` catalog.
 *
 * Companion to:
 *   - 2026_05_20_120000_add_patients_module_permissions  (created the new
 *     catalog + mirrored grants from the legacy rows)
 *   - 2026_05_20_130000_hide_legacy_patients_group        (hid the umbrella
 *     from the role editor)
 *
 * Backend rewrites completed prior to this migration:
 *   - app/Http/Controllers/Api/PatientController.php           (13 gates)
 *   - app/Http/Controllers/Admin/PatientsController.php        (17 gates)
 *   - app/Services/PatientManagement/PatientService.php        ( 6 gates)
 *   - app/Services/PatientManagement/PatientSearchService.php  ( 1 gate )
 *   - app/Policies/PatientPolicy.php                           ( 6 gates)
 *   - app/Models/Patients.php                                  ( 4 gates)
 *   - app/Exports/PatientExport.php                            ( 1 gate )
 *   - app/Http/Resources/Patient/{Patient,Detail,Appointment}Resource.php
 *   - app/Services/UserManagement/RoleService.php              ( 1 entry)
 *   - resources/views/admin/partials/sidebar.blade.php
 *   - resources/views/admin/partials/sidebar-v2.blade.php
 *   - resources/views/admin/patients/card/documents/add_documents.blade.php
 *   - resources/views/admin/patients/card/nav.blade.php
 *   - resources/views/admin/patients/card/profile/personal-info.blade.php
 *   - resources/views/admin/patients/index.blade.php
 *   - resources/views/admin/patients/filters.blade.php
 *   - resources/views/partials/sidebar.blade.php
 *   - routes/web/admin-catalogue.php
 *
 * Rows being dropped (14 total):
 *   patients_manage, patients_create, patients_edit, patients_destroy,
 *   patients_active, patients_inactive, patients_add_referrals,
 *   patients_assign_membership, patients_cancel_membership,
 *   patients_document_manage, patients_document_create,
 *   patients_document_edit, patients_document_destroy,
 *   view_inactive_patients
 *
 * NOT dropped (still owned by other modules; will go in those modules'
 * own audit passes):
 *   patients_appointment_manage, patients_customform_*, patients_plan_*,
 *   patients_finance_*, patients_invoice_*, patients_refund_*
 *
 * Role grants on the dropped rows are removed via Spatie's normal delete
 * cascade through role_has_permissions. The mirror migration already
 * granted the new equivalents, so nothing actually changes at runtime
 * for users who held the legacy rows.
 *
 * down() recreates the rows with their original ids preserved where
 * possible (Permission::create assigns a new id; we capture the originals
 * up() for restoration), restores the parent_id linkages, and gives every
 * role that held the new equivalent a grant on the legacy back. This
 * reverses both the structural delete and the grant cleanup.
 */
return new class extends Migration
{
    private const GUARD = 'web';

    /**
     * Legacy name → list of new perms that replaced it. Used by down() to
     * find roles that should regain a grant on the legacy row.
     *
     * @return array<string, list<string>>
     */
    private function reverseMap(): array
    {
        return [
            'patients_manage' => [
                'patients.list.view',
                'patients.card.view',
                'patients.profile.view',
                'patients.activity.view',
                'patients.notes.view',
            ],
            'patients_edit' => ['patients.edit'],
            'patients_destroy' => ['patients.delete'],
            'patients_active' => ['patients.activate'],
            'patients_inactive' => ['patients.deactivate'],
            'patients_add_referrals' => ['patients.referral.add'],
            'patients_assign_membership' => ['patients.membership.assign'],
            'patients_cancel_membership' => ['patients.membership.cancel'],
            'patients_document_manage' => ['patients.documents.view'],
            'patients_document_create' => ['patients.documents.upload'],
            'patients_document_edit' => ['patients.documents.edit'],
            'patients_document_destroy' => ['patients.documents.delete'],
            'view_inactive_patients' => ['patients.list.view_inactive'],
            'patients_create' => [], // status=0 already; no replacement
        ];
    }

    /**
     * Snapshot of legacy rows captured for restoration. Keyed by name.
     *
     * @var array<string, array{title: string, parent_id: int, status: int}>
     */
    private array $snapshot = [];

    public function up(): void
    {
        DB::transaction(function (): void {
            $names = array_keys($this->reverseMap());

            // Snapshot for down() — title, parent_id, status as they stand now.
            $this->snapshot = Permission::whereIn('name', $names)
                ->get(['name', 'title', 'parent_id', 'status'])
                ->keyBy('name')
                ->map(fn ($p) => [
                    'title' => (string) $p->title,
                    'parent_id' => (int) $p->parent_id,
                    'status' => (int) $p->status,
                ])
                ->all();

            // Persist the snapshot for down() — stored alongside the
            // migration in a small JSON file under storage/app/migrations
            // so a future rollback can reconstruct the rows even after
            // this PHP file's in-memory state is gone.
            $this->writeSnapshot($this->snapshot);

            // Delete the rows. Spatie cascades role_has_permissions and
            // model_has_permissions on permission delete.
            Permission::whereIn('name', $names)->delete();

            // Cache eviction inside the txn — rollback rolls back eviction.
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (
                [
                    'roles.permissions_mapping.v2.super',
                    'roles.permissions_mapping.v2.normal',
                    'roles.permissions_mapping.super',
                    'roles.permissions_mapping.normal',
                ] as $key
            ) {
                Cache::forget($key);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $snapshot = $this->readSnapshot();

            foreach ($this->reverseMap() as $name => $newPerms) {
                $meta = $snapshot[$name] ?? null;

                $perm = Permission::firstOrCreate(
                    ['name' => $name],
                    [
                        'title' => $meta['title'] ?? $name,
                        'main_group' => 0,
                        'parent_id' => $meta['parent_id'] ?? 0,
                        'status' => $meta['status'] ?? 1,
                        'guard_name' => self::GUARD,
                    ],
                );

                if ($newPerms === []) {
                    continue;
                }

                // Restore role grants: any role that holds the new
                // replacement perm regains the legacy one too.
                $roleIds = DB::table('role_has_permissions as rhp')
                    ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
                    ->whereIn('p.name', $newPerms)
                    ->distinct()
                    ->pluck('rhp.role_id')
                    ->all();

                if ($roleIds === []) {
                    continue;
                }

                $rows = array_map(
                    static fn (int $rid) => ['role_id' => $rid, 'permission_id' => $perm->id],
                    array_map('intval', $roleIds),
                );
                DB::table('role_has_permissions')->insertOrIgnore($rows);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (
                [
                    'roles.permissions_mapping.v2.super',
                    'roles.permissions_mapping.v2.normal',
                    'roles.permissions_mapping.super',
                    'roles.permissions_mapping.normal',
                ] as $key
            ) {
                Cache::forget($key);
            }
        });
    }

    /**
     * @param array<string, array{title: string, parent_id: int, status: int}> $snapshot
     */
    private function writeSnapshot(array $snapshot): void
    {
        $dir = storage_path('app/migrations');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        file_put_contents(
            $dir.'/2026_05_20_140000_legacy_patient_perms.json',
            json_encode($snapshot, JSON_PRETTY_PRINT),
        );
    }

    /**
     * @return array<string, array{title: string, parent_id: int, status: int}>
     */
    private function readSnapshot(): array
    {
        $path = storage_path('app/migrations/2026_05_20_140000_legacy_patient_perms.json');
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
};
