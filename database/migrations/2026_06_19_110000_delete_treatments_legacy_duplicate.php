<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tier P-R / Tier 2a — delete the dead legacy "Treatments" duplicate group.
 *
 * Paired with a code repoint in the same release: the 13 backend gate sites that
 * referenced the legacy treatments_manage / treatments_services slugs were moved
 * to the dotted treatments.list.view (AppointmentPolicy + Patients/Appointments
 * controllers + AppointmentExportController + ConsultancyDatatableService), so no
 * code references the legacy catalog any more. Access-flip across all 23 roles:
 * every legacy-slug holder already holds treatments.list.view, so the repoint +
 * this delete are ZERO access change. The dotted "treatments" group (728, 29
 * children) is the canonical catalog.
 *
 * Deletes the legacy treatments_manage parent + ALL its children + their now-inert
 * grants. Keeper-guarded (only if the dotted "treatments" group is visible) and
 * FULLY reversible: every deleted row (all columns) + grant is snapshotted; down()
 * re-inserts them with original ids.
 *
 * Consultations is intentionally NOT touched here — its legacy group parents live
 * appointments_* perms shared with the Appointments module, so it needs a separate
 * structural pass (re-parent those first).
 */
return new class extends Migration
{
    /** dead parent group to DELETE (subtree + grants) => canonical keeper (guard). */
    private const RETIRE_DELETE = [
        'treatments_manage' => 'treatments',
    ];

    public function up(): void
    {
        DB::statement('CREATE TABLE IF NOT EXISTS permission_delete_p3rt2a_backup (
            id INT AUTO_INCREMENT PRIMARY KEY, orig_id INT NOT NULL,
            permission_name VARCHAR(255) NOT NULL, payload JSON NOT NULL, ran_at DATETIME NOT NULL)');
        DB::statement('CREATE TABLE IF NOT EXISTS permission_grant_delete_p3rt2a_backup (
            id INT AUTO_INCREMENT PRIMARY KEY, role_id INT NOT NULL, permission_id INT NOT NULL, ran_at DATETIME NOT NULL)');

        DB::transaction(function (): void {
            $runAt = now();

            $deadParentIds = [];
            foreach (self::RETIRE_DELETE as $hide => $keep) {
                $keeperVisible = DB::table('permissions')
                    ->where('name', $keep)->where('main_group', 1)->where('status', 1)->exists();
                if (! $keeperVisible) {
                    Log::warning('P3RT2A delete: keeper not visible — skipping', ['hide' => $hide, 'keep' => $keep]);
                    continue;
                }
                $pid = DB::table('permissions')->where('name', $hide)->where('main_group', 1)->value('id');
                if ($pid !== null) {
                    $deadParentIds[] = (int) $pid;
                }
            }

            $childIds = DB::table('permissions')->whereIn('parent_id', $deadParentIds)->pluck('id')->all();
            $allDeadIds = array_values(array_unique(array_merge($deadParentIds, $childIds)));

            if ($allDeadIds === []) {
                return;
            }

            foreach (DB::table('permissions')->whereIn('id', $allDeadIds)->get() as $row) {
                DB::table('permission_delete_p3rt2a_backup')->insert([
                    'orig_id' => $row->id,
                    'permission_name' => $row->name,
                    'payload' => json_encode($row),
                    'ran_at' => $runAt,
                ]);
            }
            foreach (DB::table('role_has_permissions')->whereIn('permission_id', $allDeadIds)->get() as $g) {
                DB::table('permission_grant_delete_p3rt2a_backup')->insert([
                    'role_id' => $g->role_id,
                    'permission_id' => $g->permission_id,
                    'ran_at' => $runAt,
                ]);
            }
            DB::table('role_has_permissions')->whereIn('permission_id', $allDeadIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $allDeadIds)->delete();
            DB::table('permissions')->whereIn('id', $allDeadIds)->delete();
            Log::info('P3RT2A delete: removed legacy treatments duplicate', ['rows' => count($allDeadIds)]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permission_delete_p3rt2a_backup')) {
            return;
        }

        DB::transaction(function (): void {
            foreach (DB::table('permission_delete_p3rt2a_backup')->get() as $b) {
                if (! DB::table('permissions')->where('id', $b->orig_id)->exists()) {
                    DB::table('permissions')->insert((array) json_decode($b->payload, true));
                }
            }
            foreach (DB::table('permission_grant_delete_p3rt2a_backup')->get() as $g) {
                $exists = DB::table('role_has_permissions')
                    ->where('role_id', $g->role_id)->where('permission_id', $g->permission_id)->exists();
                if (! $exists) {
                    DB::table('role_has_permissions')->insert(['role_id' => $g->role_id, 'permission_id' => $g->permission_id]);
                }
            }
            DB::table('permission_grant_delete_p3rt2a_backup')->delete();
            DB::table('permission_delete_p3rt2a_backup')->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
