<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tier P-R / Delete (Tier 1) — remove the 5 remaining DEAD duplicate permission
 * groups the title-collision audit found still rendering in the role editor.
 *
 * The earlier delete pass matched legacy `X_manage` ↔ dotted `X` by NAME, so it
 * missed pairs whose two sides share a displayed TITLE but not a name. A live
 * audit grouping visible main groups (main_group=1, status=1) by TITLE surfaced
 * 7 duplicate pairs; this removes the 5 whose dead side has ZERO gates (literal
 * AND dynamic — verified by code grep). The 2 core-clinical pairs (treatments,
 * consultations) gate on the legacy side and need a code repoint first — handled
 * in a later, separate pass.
 *
 * Per pair, the gate scan proved which catalog the code uses; this deletes the
 * OTHER side's parent + ALL children + their stale (inert) grants — so no access
 * changes, only dead rows + dead grants are removed:
 *   - membership_types / resource_types / staff_targets → delete the dead LEGACY
 *     umbrella (dotted is canonical / the only catalog entry).
 *   - future_treatments_reports / csr_dashboard → delete the dead DOTTED shadow
 *     (the report controllers gate the LEGACY slug followuppatient_manage /
 *     csr_dashboard_report, so the dotted leaf is the inert side).
 *
 * Keeper-guarded (never delete a side unless its canonical twin is present +
 * visible) and FULLY reversible: every deleted row (all columns) and every
 * deleted grant is snapshotted; down() re-inserts them with their original ids.
 */
return new class extends Migration
{
    /** dead parent group to DELETE (subtree + grants) => canonical keeper (guard). */
    private const RETIRE_DELETE = [
        // ── dead LEGACY umbrella (dotted is canonical) ──
        'membershiptypes_manage' => 'membership_types',
        'resource_types_manage'  => 'resource_types',
        'staff_targets_manage'   => 'staff_targets',
        // ── dead DOTTED shadow (report controllers gate the legacy slug) ──
        'future_treatments_reports' => 'followuppatient_manage',
        'csr_dashboard'             => 'csr_dashboard_report',
    ];

    public function up(): void
    {
        DB::statement('CREATE TABLE IF NOT EXISTS permission_delete_p3rt1_backup (
            id INT AUTO_INCREMENT PRIMARY KEY, orig_id INT NOT NULL,
            permission_name VARCHAR(255) NOT NULL, payload JSON NOT NULL, ran_at DATETIME NOT NULL)');
        DB::statement('CREATE TABLE IF NOT EXISTS permission_grant_delete_p3rt1_backup (
            id INT AUTO_INCREMENT PRIMARY KEY, role_id INT NOT NULL, permission_id INT NOT NULL, ran_at DATETIME NOT NULL)');

        DB::transaction(function (): void {
            $runAt = now();

            $deadParentIds = [];
            foreach (self::RETIRE_DELETE as $hide => $keep) {
                $keeperVisible = DB::table('permissions')
                    ->where('name', $keep)->where('main_group', 1)->where('status', 1)->exists();
                if (! $keeperVisible) {
                    Log::warning('P3RT1 delete: keeper not visible — skipping', ['hide' => $hide, 'keep' => $keep]);
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
                DB::table('permission_delete_p3rt1_backup')->insert([
                    'orig_id' => $row->id,
                    'permission_name' => $row->name,
                    'payload' => json_encode($row),
                    'ran_at' => $runAt,
                ]);
            }
            foreach (DB::table('role_has_permissions')->whereIn('permission_id', $allDeadIds)->get() as $g) {
                DB::table('permission_grant_delete_p3rt1_backup')->insert([
                    'role_id' => $g->role_id,
                    'permission_id' => $g->permission_id,
                    'ran_at' => $runAt,
                ]);
            }
            DB::table('role_has_permissions')->whereIn('permission_id', $allDeadIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $allDeadIds)->delete();
            DB::table('permissions')->whereIn('id', $allDeadIds)->delete();
            Log::info('P3RT1 delete: removed remaining dead duplicate permissions', ['rows' => count($allDeadIds)]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permission_delete_p3rt1_backup')) {
            return;
        }

        DB::transaction(function (): void {
            foreach (DB::table('permission_delete_p3rt1_backup')->get() as $b) {
                if (! DB::table('permissions')->where('id', $b->orig_id)->exists()) {
                    DB::table('permissions')->insert((array) json_decode($b->payload, true));
                }
            }
            foreach (DB::table('permission_grant_delete_p3rt1_backup')->get() as $g) {
                $exists = DB::table('role_has_permissions')
                    ->where('role_id', $g->role_id)->where('permission_id', $g->permission_id)->exists();
                if (! $exists) {
                    DB::table('role_has_permissions')->insert(['role_id' => $g->role_id, 'permission_id' => $g->permission_id]);
                }
            }
            DB::table('permission_grant_delete_p3rt1_backup')->truncate();
            DB::table('permission_delete_p3rt1_backup')->truncate();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
