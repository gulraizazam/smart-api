<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Models\PackageBundles;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the package_bundles.account_id work:
 *   - 2026_06_04_130200 add column (idempotent)
 *   - 2026_06_04_130300 backfill (by package_id, then random_id)
 *   - PackageBundles::creating hook stamping account_id on new rows
 *     (acting-user source, and package-derive fallback).
 */
class PackageBundleAccountIdTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    private function makePackage(string $randomId, int $accountId = 1): int
    {
        return (int) DB::table('packages')->insertGetId([
            'random_id' => $randomId,
            'plan_type' => 'plan',
            'total_price' => 0,
            'account_id' => $accountId,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Insert a package_bundles row directly (bypassing the model hook). */
    private function rawBundle(array $attrs): int
    {
        return (int) DB::table('package_bundles')->insertGetId(array_merge([
            'qty' => 1,
            'service_price' => 100,
            'net_amount' => 100,
            'account_id' => null,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }

    private function runMigration(string $file): void
    {
        (require database_path("migrations/{$file}"))->up();
    }

    public function test_add_column_migration_is_idempotent(): void
    {
        // The column already exists in the test DB (ensure helper), so
        // up() must be a clean no-op, not a duplicate-column error.
        $this->runMigration('2026_06_04_130200_add_account_id_to_package_bundles.php');
        $this->assertTrue(Schema::hasColumn('package_bundles', 'account_id'));
    }

    public function test_backfill_populates_account_id_from_package_id(): void
    {
        $pkgId = $this->makePackage('RID-A', 1);
        $pbId = $this->rawBundle(['package_id' => $pkgId, 'random_id' => 'RID-A']);

        $this->runMigration('2026_06_04_130300_backfill_package_bundles_account_id.php');

        $this->assertSame(1, (int) DB::table('package_bundles')->where('id', $pbId)->value('account_id'));
    }

    public function test_backfill_resolves_via_random_id_when_package_id_is_null(): void
    {
        $this->makePackage('RID-B', 1);
        $pbId = $this->rawBundle(['package_id' => null, 'random_id' => 'RID-B']);

        $this->runMigration('2026_06_04_130300_backfill_package_bundles_account_id.php');

        $this->assertSame(1, (int) DB::table('package_bundles')->where('id', $pbId)->value('account_id'));
    }

    public function test_creating_hook_derives_account_id_from_package_without_auth(): void
    {
        $pkgId = $this->makePackage('RID-C', 1);

        $pb = PackageBundles::create([
            'package_id' => $pkgId,
            'random_id' => 'RID-C',
            'qty' => 1,
            'source_type' => 'service', // valid tag required by the model guard
            'service_price' => 100,
            'net_amount' => 100,
            'active' => 1,
        ]);

        $this->assertSame(1, (int) $pb->fresh()->account_id);
    }

    public function test_creating_hook_stamps_account_id_from_acting_user(): void
    {
        $userId = (int) DB::table('users')->insertGetId([
            'name' => 'Acct Actor',
            'email' => 'acct.actor@test.local',
            'password' => bcrypt('test'),
            'phone' => '+923007770001',
            'user_type_id' => 3,
            'account_id' => 1,
            'active' => 1,
            'main_account' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs(User::find($userId));

        // No package_id / unmatched random_id — the hook must fall back
        // to the acting user's account_id.
        $pb = PackageBundles::create([
            'random_id' => 'RID-UNMATCHED',
            'qty' => 1,
            'source_type' => 'service', // valid tag required by the model guard
            'service_price' => 100,
            'net_amount' => 100,
            'active' => 1,
        ]);

        $this->assertSame(1, (int) $pb->fresh()->account_id);
    }
}
