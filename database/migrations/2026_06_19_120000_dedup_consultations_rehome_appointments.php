<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tier P-R / Tier 2b — dissolve the mislabeled legacy "Consultations" group.
 *
 * The legacy "Consultations" group (consultations_manage) is really the
 * Appointments module's permission group wearing a "Consultations" label: its
 * children are 11 live-gated appointments_* perms + edit_after_arrived
 * (appointment-level) + 3 update_consultation_* (consultation-specific). The
 * dotted "consultations" group is the real consultation catalog, so the editor
 * showed the title twice.
 *
 * Paired with a code repoint (same release): consultations_manage ->
 * consultations.list.view, and update_consultation_{doctor,schedule,service} ->
 * consultations.edit.{...}.after_arrived. Access-flip across all roles verified
 * zero-loss; edit_after_arrived is appointment-level and is left as-is.
 *
 * To remove the duplicate WITHOUT disturbing the Appointments module:
 *   1. Re-parent the appointments_* + edit_after_arrived perms into the existing
 *      appointments_manage group, and rename that group's title to "Appointments".
 *      Perms keep their name/id/grants/gates — only parent_id + the group label
 *      change, so ZERO functional effect (Spatie authorizes by name).
 *   2. Delete the now-consultation-only legacy group + its repointed
 *      update_consultation_* leftovers + their stale grants.
 *
 * Fully reversible: the re-parent (old parent_id), the rename (old title), and the
 * deletion (rows + grants) are all snapshotted; down() restores every part.
 */
return new class extends Migration
{
    private const LEGACY_GROUP = 'consultations_manage';  // mislabeled group to dissolve
    private const REHOME_GROUP = 'appointments_manage';   // existing group that receives the appointments perms
    private const KEEPER       = 'consultations';         // dotted catalog (guard)
    private const NEW_TITLE    = 'Appointments';

    /** consultation-specific children, repointed to dotted -> deleted with the group. */
    private const DELETE_CHILDREN = [
        'update_consultation_doctor', 'update_consultation_schedule', 'update_consultation_service',
    ];

    public function up(): void
    {
        DB::statement('CREATE TABLE IF NOT EXISTS permission_delete_p3rt2b_backup (
            id INT AUTO_INCREMENT PRIMARY KEY, orig_id INT NOT NULL,
            permission_name VARCHAR(255) NOT NULL, payload JSON NOT NULL, ran_at DATETIME NOT NULL)');
        DB::statement('CREATE TABLE IF NOT EXISTS permission_grant_delete_p3rt2b_backup (
            id INT AUTO_INCREMENT PRIMARY KEY, role_id INT NOT NULL, permission_id INT NOT NULL, ran_at DATETIME NOT NULL)');
        DB::statement('CREATE TABLE IF NOT EXISTS permission_reparent_p3rt2b_backup (
            id INT AUTO_INCREMENT PRIMARY KEY, permission_id INT NOT NULL, old_parent_id INT NOT NULL, ran_at DATETIME NOT NULL)');
        DB::statement('CREATE TABLE IF NOT EXISTS permission_group_rename_p3rt2b_backup (
            id INT AUTO_INCREMENT PRIMARY KEY, permission_id INT NOT NULL, old_title VARCHAR(255) NULL, ran_at DATETIME NOT NULL)');

        DB::transaction(function (): void {
            $runAt = now();

            $legacy = DB::table('permissions')->where('name', self::LEGACY_GROUP)->where('main_group', 1)->first();
            $rehome = DB::table('permissions')->where('name', self::REHOME_GROUP)->where('main_group', 1)->first();
            $keeperVisible = DB::table('permissions')->where('name', self::KEEPER)
                ->where('main_group', 1)->where('status', 1)->exists();

            if ($legacy === null || $rehome === null || ! $keeperVisible) {
                Log::warning('P3RT2B: legacy/rehome/keeper missing — skipping', [
                    'legacy' => $legacy->id ?? null, 'rehome' => $rehome->id ?? null, 'keeperVisible' => $keeperVisible,
                ]);
                return;
            }

            $children = DB::table('permissions')->where('parent_id', $legacy->id)->get();
            $deleteChildIds = $children->whereIn('name', self::DELETE_CHILDREN)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $reparent = $children->whereNotIn('name', self::DELETE_CHILDREN);

            // snapshot re-parent (old parent) + rename (old title)
            foreach ($reparent as $p) {
                DB::table('permission_reparent_p3rt2b_backup')->insert([
                    'permission_id' => (int) $p->id, 'old_parent_id' => (int) $legacy->id, 'ran_at' => $runAt,
                ]);
            }
            DB::table('permission_group_rename_p3rt2b_backup')->insert([
                'permission_id' => (int) $rehome->id, 'old_title' => $rehome->title, 'ran_at' => $runAt,
            ]);

            // re-parent appointments_* + edit_after_arrived into the rehome group, then rename it
            if ($reparent->isNotEmpty()) {
                DB::table('permissions')->whereIn('id', $reparent->pluck('id')->all())->update(['parent_id' => $rehome->id]);
            }
            DB::table('permissions')->where('id', $rehome->id)->update(['title' => self::NEW_TITLE]);

            // delete the legacy group + its repointed update_consultation_* leftovers + grants
            $deadIds = array_merge([(int) $legacy->id], $deleteChildIds);
            foreach (DB::table('permissions')->whereIn('id', $deadIds)->get() as $row) {
                DB::table('permission_delete_p3rt2b_backup')->insert([
                    'orig_id' => (int) $row->id, 'permission_name' => $row->name, 'payload' => json_encode($row), 'ran_at' => $runAt,
                ]);
            }
            foreach (DB::table('role_has_permissions')->whereIn('permission_id', $deadIds)->get() as $g) {
                DB::table('permission_grant_delete_p3rt2b_backup')->insert([
                    'role_id' => $g->role_id, 'permission_id' => $g->permission_id, 'ran_at' => $runAt,
                ]);
            }
            DB::table('role_has_permissions')->whereIn('permission_id', $deadIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $deadIds)->delete();
            DB::table('permissions')->whereIn('id', $deadIds)->delete();

            Log::info('P3RT2B: dissolved legacy consultations group', [
                'reparented' => $reparent->count(), 'deleted' => count($deadIds),
            ]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permission_delete_p3rt2b_backup')) {
            return;
        }

        DB::transaction(function (): void {
            // restore deleted rows first (incl. the legacy parent — needed before re-parenting back)
            foreach (DB::table('permission_delete_p3rt2b_backup')->get() as $b) {
                if (! DB::table('permissions')->where('id', $b->orig_id)->exists()) {
                    DB::table('permissions')->insert((array) json_decode($b->payload, true));
                }
            }
            foreach (DB::table('permission_grant_delete_p3rt2b_backup')->get() as $g) {
                $exists = DB::table('role_has_permissions')
                    ->where('role_id', $g->role_id)->where('permission_id', $g->permission_id)->exists();
                if (! $exists) {
                    DB::table('role_has_permissions')->insert(['role_id' => $g->role_id, 'permission_id' => $g->permission_id]);
                }
            }
            // re-parent the moved perms back to the legacy group
            foreach (DB::table('permission_reparent_p3rt2b_backup')->get() as $r) {
                DB::table('permissions')->where('id', $r->permission_id)->update(['parent_id' => $r->old_parent_id]);
            }
            // restore the rehome group's original title
            foreach (DB::table('permission_group_rename_p3rt2b_backup')->get() as $t) {
                DB::table('permissions')->where('id', $t->permission_id)->update(['title' => $t->old_title]);
            }

            DB::table('permission_delete_p3rt2b_backup')->delete();
            DB::table('permission_grant_delete_p3rt2b_backup')->delete();
            DB::table('permission_reparent_p3rt2b_backup')->delete();
            DB::table('permission_group_rename_p3rt2b_backup')->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
