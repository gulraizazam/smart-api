<?php

declare(strict_types=1);

namespace Tests\Feature\Coexistence;

use App\Models\Leads;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\Services;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * CI-runnable slice of the crm2/crm3 coexistence CONTRACT.
 *
 * The full check (scripts/coexistence-check.sh) needs the LOCAL mirror -- two
 * app checkouts (crm2 + crm3), live HTTP, and a shared seeded DB -- so it stays
 * the local Definition-of-Done gate. THIS test pins the destructive-break
 * subset that CAN run in CI against the schema-dump test DB, so a regression
 * can't reach prod unnoticed:
 *
 *   1. SHARED COLUMNS crm2 reads (its models' $fillable) must still exist
 *      -> catches a crm3 migration that drops/renames a shared column.
 *   2. The legacy umbrella PERMISSIONS crm2 gates on must still be DEFINED by
 *      the catalog seeder -> catches a crm3 change that removes one.
 *
 * $fillable-driven so it can't go stale as the models evolve. Complements
 * PermissionAliasBridgeTest (which guards the bridge MECHANISM, not the catalog).
 * See feedback_crm3_must_not_break_crm2 / project_local_coexistence_mirror.
 */
class Crm2ContractTest extends TestCase
{
    use RefreshDatabase;

    /** crm2 reads these models straight off the shared DB (legacy column shapes). */
    private const CRM2_MODELS = [
        Patients::class,
        Leads::class,
        PackageAdvances::class,
        Services::class,
        Packages::class,
    ];

    /** Umbrella perms crm2's Blade gates on (must survive any crm3 perm refactor). */
    private const LEGACY_UMBRELLAS = [
        'patients_manage', 'plans_manage', 'services_manage',
        'consultations_manage', 'treatments_manage', 'leads_manage', 'invoices_manage',
    ];

    public function test_shared_columns_crm2_reads_must_still_exist(): void
    {
        foreach (self::CRM2_MODELS as $cls) {
            $model = new $cls();
            $table = $model->getTable();

            $this->assertTrue(
                Schema::hasTable($table),
                "crm2 contract: table `{$table}` ({$cls}) is gone -- crm2 reads it.",
            );

            foreach ($model->getFillable() as $column) {
                $this->assertTrue(
                    Schema::hasColumn($table, $column),
                    "crm2 contract: column `{$table}.{$column}` is gone/renamed -- crm2's {$cls} \$fillable reads it. "
                        .'Shared-DB changes are additive only: never drop/rename a column crm2 reads.',
                );
            }
        }
    }

    public function test_legacy_umbrella_permissions_still_declared_in_catalog(): void
    {
        // CATEGORY_MAP is crm3's source of truth for parent/umbrella permissions
        // (kept in sync with the perm migration). Reading it via reflection catches
        // a crm3 change that removes a legacy umbrella crm2 gates on -- the
        // CI-checkable half of the perm contract. LIVE-DB catalog fidelity (rows
        // actually present on the shared prod DB) stays coexistence-check.sh's job,
        // since the schema-dump test DB has no seeded perm data.
        $map = (new ReflectionClass(PermissionSeeder::class))
            ->getReflectionConstant('CATEGORY_MAP')?->getValue();

        $this->assertIsArray(
            $map,
            'PermissionSeeder::CATEGORY_MAP not found -- the permission catalog source moved; update this contract guard.',
        );

        $declared = array_merge(...array_values($map));
        foreach (self::LEGACY_UMBRELLAS as $perm) {
            $this->assertContains(
                $perm,
                $declared,
                "crm2 contract: legacy umbrella `{$perm}` is no longer declared in PermissionSeeder::CATEGORY_MAP -- "
                    .'crm2 gates on it. Never remove a legacy permission while crm2 is live.',
            );
        }
    }
}
