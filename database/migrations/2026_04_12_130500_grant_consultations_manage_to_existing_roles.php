<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill: every role that currently holds `appointments_consultancy`
 * (the legacy child permission that actually gates the Consultations module)
 * also receives `consultations_manage` (the new parent). After this runs we
 * can safely flip all gate-check call sites to `consultations_manage` without
 * stripping access from any existing role.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $legacyId = DB::table('permissions')->where('name', 'appointments_consultancy')->value('id');
            $parentId = DB::table('permissions')->where('name', 'consultations_manage')->value('id');

            if ($legacyId === null || $parentId === null) {
                return;
            }

            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $legacyId)
                ->pluck('role_id')
                ->all();

            if (empty($roleIds)) {
                return;
            }

            $alreadyHave = DB::table('role_has_permissions')
                ->where('permission_id', $parentId)
                ->whereIn('role_id', $roleIds)
                ->pluck('role_id')
                ->all();

            $toInsert = array_diff($roleIds, $alreadyHave);

            if (empty($toInsert)) {
                return;
            }

            DB::table('role_has_permissions')->insert(
                array_map(
                    fn (int $roleId): array => ['permission_id' => $parentId, 'role_id' => $roleId],
                    $toInsert,
                )
            );
        });
    }

    public function down(): void
    {
        // No-op: removing the grants would strip access from roles that may
        // have been updated manually after the migration. Restore from the
        // pre-migration dump if needed.
    }
};
