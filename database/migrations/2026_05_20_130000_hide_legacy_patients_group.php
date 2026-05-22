<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hide the legacy snake_case Patients permission group from the role editor.
 *
 * Companion to 2026_05_20_120000_add_patients_module_permissions, which
 * introduced the new dotted `patients.*` catalog under its own group. The
 * role editor now shows two "Patients" sections — the legacy `patients_manage`
 * umbrella (children: Edit / Delete / Activate / Document Manage / Plan
 * Manage / Invoice Manage / Refund Manage / …) and the new `patients`
 * dotted catalog. Visually duplicate, so we hide the legacy one.
 *
 * Mechanism: RoleService::buildPermissionGroup() selects group headers with
 * `main_group=1 AND status=1`, then pulls children via parent_id matching
 * those headers. Flipping the legacy umbrella's status to 0 removes both
 * the header and its entire subtree from the editor in one move.
 *
 * Why not hard-delete: `Gate::allows('patients_manage')` /
 * `Gate::allows('patients_edit')` etc. are still wired into PatientController,
 * PatientService, PatientPolicy, and several admin-v1 Blade views. Hard
 * delete would 403 those code paths for everyone except super-admins. The
 * actual call-site rewrite happens when each parent module (Plans, Invoices,
 * Refunds, Documents) gets its dedicated audit pass — at that point the
 * remaining patients_* rows owned by those modules are dropped/relocated
 * alongside the rewrite.
 *
 * Reversible: down() flips status back to 1, restoring the legacy group.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::where('name', 'patients_manage')->update(['status' => 0]);

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
    }

    public function down(): void
    {
        Permission::where('name', 'patients_manage')->update(['status' => 1]);

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
    }
};
