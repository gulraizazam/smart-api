<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tier P — P3 prerequisite: make `contact` phone-visibility self-sufficient
 * BEFORE the alias bridge is removed.
 *
 * crm3 reveals phone numbers under the broad legacy `contact` perm on most
 * screens AND under three granular dotted slugs on others
 * (leads/consultations/patients `.list.view_contact`). The alias bridge
 * (Gate::before) currently unifies them, so a role holding ONLY `contact`
 * still passes a dotted view_contact gate. When P3 removes the bridge, that
 * unification is gone — a `contact`-holder would lose phone visibility on any
 * dotted-gated screen it doesn't hold the dotted slug for.
 *
 * Live-prod audit (2026-06-18, all 23 roles): exactly ONE gap — "Lead Uploader"
 * holds `contact` + leads/consultations view_contact but NOT
 * `patients.list.view_contact`, so it sees patient-list phones only via the
 * bridge. This grants the dotted twin to every `contact`-holder that lacks it
 * (net effect on prod: Lead Uploader gains patients.list.view_contact), so no
 * role loses any phone screen when the bridge is removed.
 *
 * Behaviour-neutral today (the bridge already grants these effectively);
 * additive + reversible (snapshot-backed down()). Resolved BY NAME — ids drift
 * between environments.
 */
return new class extends Migration
{
    private const LEGACY_SOURCE = 'contact';

    /** Every dotted phone-visibility gate that the bridge aliases back to `contact`. */
    private const DOTTED_TWINS = [
        'leads.list.view_contact',
        'consultations.list.view_contact',
        'patients.list.view_contact',
    ];

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permission_contact_backfill_p3_backup')) {
            DB::statement('CREATE TABLE permission_contact_backfill_p3_backup (
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

            $legacyId = DB::table('permissions')->where('name', self::LEGACY_SOURCE)->value('id');
            if ($legacyId === null) {
                Log::warning('P3 contact backfill: legacy `contact` perm absent; skipping');

                return;
            }

            foreach (self::DOTTED_TWINS as $dotted) {
                $dottedId = DB::table('permissions')->where('name', $dotted)->value('id');
                if ($dottedId === null) {
                    Log::warning('P3 contact backfill: dotted twin absent; skipping', ['dotted' => $dotted]);
                    continue;
                }

                // Roles that hold `contact` but NOT this dotted twin.
                $roleIds = DB::table('role_has_permissions as rc')
                    ->leftJoin('role_has_permissions as rd', function ($j) use ($dottedId) {
                        $j->on('rc.role_id', '=', 'rd.role_id')
                            ->where('rd.permission_id', '=', $dottedId);
                    })
                    ->where('rc.permission_id', $legacyId)
                    ->whereNull('rd.role_id')
                    ->pluck('rc.role_id')
                    ->unique();

                foreach ($roleIds as $roleId) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $dottedId,
                        'role_id' => $roleId,
                    ]);
                    DB::table('permission_contact_backfill_p3_backup')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $dottedId,
                        'permission_name' => $dotted,
                        'ran_at' => $runAt,
                    ]);
                    $added++;
                }
            }

            Log::info('P3 contact backfill: granted dotted view_contact twins to contact-holders', ['added' => $added]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Remove exactly the grants this migration added (snapshot-driven). */
    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permission_contact_backfill_p3_backup')) {
            return;
        }

        DB::transaction(function (): void {
            foreach (DB::table('permission_contact_backfill_p3_backup')->get() as $row) {
                DB::table('role_has_permissions')
                    ->where('role_id', $row->role_id)
                    ->where('permission_id', $row->permission_id)
                    ->delete();
            }
            DB::table('permission_contact_backfill_p3_backup')->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
