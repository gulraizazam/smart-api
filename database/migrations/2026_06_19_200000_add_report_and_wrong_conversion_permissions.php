<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Create catalog permissions for three features that the 2026-06-19 QA found were
 * reachable only by Super-Admin because NO permission row existed to grant them
 * (the SPA/server gated on slugs that weren't in the catalog → always-deny):
 *
 *   - doctor_revenue_manage    — Doctor Revenue report (SPA already checks it;
 *                                server gated on it in this same change)
 *   - doctor_incentive_report  — Doctor Incentive report (SPA + server already
 *                                check it; this just creates the missing slug)
 *   - wrong_conversions_manage — Wrong Conversions tool (server gated on it here)
 *
 * Each is a top-level grantable group row (parent_id=0, main_group=1, status=1),
 * mirroring every other report perm, so it renders in the role editor. Additive +
 * reversible: down() removes any role grants then the rows, with ->delete() (NOT
 * truncate — truncate forces an implicit commit and breaks migrate:rollback).
 */
return new class extends Migration
{
    /** @var array<int, array{name:string, title:string, category:string}> */
    private const PERMS = [
        ['name' => 'doctor_revenue_manage', 'title' => 'Doctor Revenue Report', 'category' => 'Reports'],
        ['name' => 'doctor_incentive_report', 'title' => 'Doctor Incentive Report', 'category' => 'Reports'],
        ['name' => 'wrong_conversions_manage', 'title' => 'Wrong Conversions', 'category' => 'Finance'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMS as $p) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $p['name'], 'guard_name' => 'web'],
                [
                    'title' => $p['title'],
                    'main_group' => 1,
                    'status' => 1,
                    'category' => $p['category'],
                    'parent_id' => 0,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $names = array_column(self::PERMS, 'name');

        $ids = DB::table('permissions')
            ->whereIn('name', $names)->where('guard_name', 'web')->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('name', $names)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
