<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hygiene — scrub PRE-EXISTING dangling permission references.
 *
 * QA (2026-06-19) found inert junk that PRE-DATES the Tier P / P-R cleanup
 * (verified: none of these ids appear in the cleanup's delete snapshots — they
 * come from older UI-deletions / migrations that didn't cascade):
 *   1. Orphaned children — permissions whose parent_id points to a parent row
 *      that no longer exists (main_group=0 leaves that render nowhere; confirmed
 *      ungated: zero backend AND zero SPA references; their dotted replacements
 *      already exist).
 *   2. Dangling grants — role_has_permissions / model_has_permissions rows whose
 *      permission_id has no matching permission (grant nothing — Spatie resolves
 *      grants by FK).
 *
 * Removed dynamically (the conditions precisely define the junk) and fully
 * snapshotted; down() restores the rows + grants. ZERO access change — every
 * removed row was already inert. Clears with delete() (never truncate) so the
 * down() is rollback-safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE TABLE IF NOT EXISTS permission_scrub_p3rt3_backup (
            id INT AUTO_INCREMENT PRIMARY KEY, orig_id INT NOT NULL, payload JSON NOT NULL, ran_at DATETIME NOT NULL)');
        DB::statement('CREATE TABLE IF NOT EXISTS permission_scrub_grant_p3rt3_backup (
            id INT AUTO_INCREMENT PRIMARY KEY, role_id INT NOT NULL, permission_id INT NOT NULL, ran_at DATETIME NOT NULL)');

        DB::transaction(function (): void {
            $runAt = now();

            // 1. Orphaned children (parent_id -> missing parent). Snapshot + delete.
            $existingIds = DB::table('permissions')->pluck('id')->all();
            $orphans = DB::table('permissions')
                ->whereNotNull('parent_id')->where('parent_id', '!=', 0)
                ->whereNotIn('parent_id', $existingIds)
                ->get();
            foreach ($orphans as $row) {
                DB::table('permission_scrub_p3rt3_backup')->insert([
                    'orig_id' => (int) $row->id, 'payload' => json_encode($row), 'ran_at' => $runAt,
                ]);
            }
            if ($orphans->isNotEmpty()) {
                DB::table('permissions')->whereIn('id', $orphans->pluck('id')->all())->delete();
            }

            // 2. Dangling grants (permission_id -> missing perm) — now also includes any
            //    grant that pointed at a just-removed orphan. Snapshot + delete.
            $existingIds = DB::table('permissions')->pluck('id')->all();
            $dangling = DB::table('role_has_permissions')->whereNotIn('permission_id', $existingIds)->get();
            foreach ($dangling as $g) {
                DB::table('permission_scrub_grant_p3rt3_backup')->insert([
                    'role_id' => (int) $g->role_id, 'permission_id' => (int) $g->permission_id, 'ran_at' => $runAt,
                ]);
            }
            if ($dangling->isNotEmpty()) {
                DB::table('role_has_permissions')->whereNotIn('permission_id', $existingIds)->delete();
            }

            // Defensive: dangling model_has_permissions (expected none).
            $danglingModel = (int) DB::table('model_has_permissions')->whereNotIn('permission_id', $existingIds)->count();
            if ($danglingModel > 0) {
                DB::table('model_has_permissions')->whereNotIn('permission_id', $existingIds)->delete();
            }

            Log::info('P3RT3 scrub: removed dangling permission refs', [
                'orphans' => $orphans->count(),
                'dangling_grants' => $dangling->count(),
                'dangling_model' => $danglingModel,
            ]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permission_scrub_p3rt3_backup')) {
            return;
        }

        DB::transaction(function (): void {
            // restore orphan rows first (with original ids), then their/other grants
            foreach (DB::table('permission_scrub_p3rt3_backup')->get() as $b) {
                if (! DB::table('permissions')->where('id', $b->orig_id)->exists()) {
                    DB::table('permissions')->insert((array) json_decode($b->payload, true));
                }
            }
            foreach (DB::table('permission_scrub_grant_p3rt3_backup')->get() as $g) {
                $exists = DB::table('role_has_permissions')
                    ->where('role_id', $g->role_id)->where('permission_id', $g->permission_id)->exists();
                if (! $exists) {
                    DB::table('role_has_permissions')->insert(['role_id' => $g->role_id, 'permission_id' => $g->permission_id]);
                }
            }
            DB::table('permission_scrub_grant_p3rt3_backup')->delete();
            DB::table('permission_scrub_p3rt3_backup')->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
