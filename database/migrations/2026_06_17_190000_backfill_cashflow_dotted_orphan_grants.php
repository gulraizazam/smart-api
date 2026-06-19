<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tier P — P1: backfill the dotted twin for the ONLY genuine orphan grants.
 *
 * Verified on live prod 2026-06-17 (orphan audit, re-validated against the REAL
 * dotted catalog): exactly TWO roles hold a legacy cashflow slug without its
 * dotted twin —
 *   Stocks      holds cashflow_manage     but not cashflow.manage
 *   Operations  holds cashflow_dashboard  but not cashflow.dashboard.view
 *
 * The audit ALSO flagged packages_destroy on Administrator/Super-Admin, but that
 * was a FALSE POSITIVE: the bridge's dotted name `packages.delete` is a phantom
 * (no catalog row); the real slug is `packages.destroy`, which both roles already
 * hold. That bridge typo is corrected separately in P2 — this migration does NOT
 * touch packages.
 *
 * Additive + reversible: with the alias bridge still live these grants are
 * behaviour-neutral today (the roles already pass via the legacy slug); they are
 * the prerequisite so that when P3 removes the bridge no role loses access.
 *
 * Pairs are resolved BY NAME at runtime — permission ids drift between
 * environments (prod id 901 had already drifted from "Packages" to a cashflow
 * slug), so hardcoded ids are unsafe.
 */
return new class extends Migration
{
    /** legacy slug => REAL dotted twin (catalog-verified). */
    private const PAIRS = [
        'cashflow_manage'    => 'cashflow.manage',
        'cashflow_dashboard' => 'cashflow.dashboard.view',
    ];

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permission_backfill_p1_backup')) {
            DB::statement('CREATE TABLE permission_backfill_p1_backup (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_id INT NOT NULL,
                permission_id INT NOT NULL,
                permission_name VARCHAR(255) NOT NULL,
                ran_at DATETIME NOT NULL,
                INDEX (role_id),
                INDEX (permission_id)
            )');
        }

        DB::transaction(function (): void {
            $runAt = now();
            $added = 0;

            foreach (self::PAIRS as $legacy => $dotted) {
                $legacyId = DB::table('permissions')->where('name', $legacy)->value('id');
                $dottedId = DB::table('permissions')->where('name', $dotted)->value('id');

                // Skip safely if this DB's catalog differs (e.g. a local mirror
                // missing one of the slugs) — never fabricate a grant blindly.
                if ($legacyId === null || $dottedId === null) {
                    Log::warning('P1 backfill: pair absent on this DB; skipping', [
                        'legacy' => $legacy, 'dotted' => $dotted,
                        'legacyId' => $legacyId, 'dottedId' => $dottedId,
                    ]);
                    continue;
                }

                // Roles that hold the legacy slug but NOT the dotted twin.
                $roleIds = DB::table('role_has_permissions as rl')
                    ->leftJoin('role_has_permissions as rd', function ($j) use ($dottedId) {
                        $j->on('rl.role_id', '=', 'rd.role_id')
                            ->where('rd.permission_id', '=', $dottedId);
                    })
                    ->where('rl.permission_id', $legacyId)
                    ->whereNull('rd.role_id')
                    ->pluck('rl.role_id')
                    ->unique();

                foreach ($roleIds as $roleId) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $dottedId,
                        'role_id' => $roleId,
                    ]);
                    DB::table('permission_backfill_p1_backup')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $dottedId,
                        'permission_name' => $dotted,
                        'ran_at' => $runAt,
                    ]);
                    $added++;
                }
            }

            Log::info('P1 backfill: granted dotted twins to legacy-only roles', ['added' => $added]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Remove exactly the grants this migration added (snapshot-driven). */
    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permission_backfill_p1_backup')) {
            return;
        }

        DB::transaction(function (): void {
            foreach (DB::table('permission_backfill_p1_backup')->get() as $row) {
                DB::table('role_has_permissions')
                    ->where('role_id', $row->role_id)
                    ->where('permission_id', $row->permission_id)
                    ->delete();
            }
            DB::table('permission_backfill_p1_backup')->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
