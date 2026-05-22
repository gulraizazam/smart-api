<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Consolidate the consultation edit-after-arrival permission model.
 *
 * Before: one generic `consultations.edit.after_arrived` (cosmetic override
 * surfaced as the "Edit After Arrived" tickbox in the role editor) plus
 * three per-field perms `consultations.edit.{service,doctor,schedule}`
 * that — confusingly — were already enforced ONLY when the consultation
 * was arrived/converted (see ConsultancyUpdateService::validateArrivedConsultationUpdate).
 * Two different names for the same intent and a fourth generic name that
 * the team didn't actually want.
 *
 * After: the three per-field perms are renamed to the explicit
 *   consultations.edit.{service,doctor,schedule}.after_arrived
 * shape that matches the semantic ("only kicks in when the consultation
 * has already arrived/converted") and the generic `.after_arrived` row
 * is dropped — there's nothing left for it to gate now that the invoice-
 * lock bypass is removed in the companion service-layer change.
 *
 * Idempotent + safe on re-run: each row is checked for existence before
 * the UPDATE/DELETE so a partial prior application (or a fresh DB that
 * never had the originals) doesn't error.
 *
 * Permission ids stay stable across the rename — Spatie's role/user
 * pivot tables reference perms by id, so existing grants automatically
 * carry over to the new names. Spatie + RoleService caches are flushed
 * at the end so the dotted catalog reads correctly without a redeploy.
 */
return new class extends Migration
{
    private const ROLE_SERVICE_CACHE_KEYS = [
        'roles.permissions_mapping.v2.super',
        'roles.permissions_mapping.v2.normal',
        'roles.permissions_mapping.super',
        'roles.permissions_mapping.normal',
    ];

    /**
     * old name → [new name, new title]. Title shown in the role editor.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function renames(): array
    {
        return [
            'consultations.edit.service'   => ['consultations.edit.service.after_arrived',   'Edit Service After Arrived'],
            'consultations.edit.doctor'    => ['consultations.edit.doctor.after_arrived',    'Edit Doctor After Arrived'],
            'consultations.edit.schedule'  => ['consultations.edit.schedule.after_arrived',  'Edit Schedule After Arrived'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach ($this->renames() as $oldName => [$newName, $newTitle]) {
                // If the new name already exists (a previous half-applied
                // run), merge: re-point any grants that still reference
                // the old id to the new id, then delete the old row.
                $newId = DB::table('permissions')->where('name', $newName)->value('id');
                $oldRow = DB::table('permissions')->where('name', $oldName)->first();
                if ($oldRow === null) {
                    continue;
                }

                if ($newId !== null && (int) $newId !== (int) $oldRow->id) {
                    DB::table('role_has_permissions')
                        ->where('permission_id', $oldRow->id)
                        ->update(['permission_id' => $newId]);
                    DB::table('model_has_permissions')
                        ->where('permission_id', $oldRow->id)
                        ->update(['permission_id' => $newId]);
                    DB::table('permissions')->where('id', $oldRow->id)->delete();
                    DB::table('permissions')
                        ->where('id', $newId)
                        ->update(['title' => $newTitle]);
                } else {
                    DB::table('permissions')
                        ->where('id', $oldRow->id)
                        ->update(['name' => $newName, 'title' => $newTitle]);
                }
            }

            // Drop the generic override — replaced by the per-field
            // .after_arrived perms above. Pivot rows clean up via FK
            // cascade (Spatie's schema defines ON DELETE CASCADE).
            DB::table('permissions')
                ->where('name', 'consultations.edit.after_arrived')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            foreach ($this->renames() as $oldName => [$newName, $_title]) {
                $exists = DB::table('permissions')->where('name', $oldName)->exists();
                if ($exists) {
                    continue;
                }
                DB::table('permissions')
                    ->where('name', $newName)
                    ->update([
                        'name' => $oldName,
                        'title' => match ($oldName) {
                            'consultations.edit.service'  => 'Edit · Service',
                            'consultations.edit.doctor'   => 'Edit · Doctor',
                            'consultations.edit.schedule' => 'Edit · Schedule',
                            default => $oldName,
                        },
                    ]);
            }

            // Recreate the generic override row so a `down()` truly
            // reverses `up()`. parent_id is resolved from the existing
            // `consultations` group head; if that's missing (rare) we
            // skip — the up() catalog migration would re-seed it.
            $groupId = DB::table('permissions')->where('name', 'consultations')->value('id');
            if ($groupId !== null) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => 'consultations.edit.after_arrived'],
                    [
                        'title' => 'Edit After Arrived (override)',
                        'main_group' => 0,
                        'parent_id' => (int) $groupId,
                        'status' => 1,
                        'category' => null,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
