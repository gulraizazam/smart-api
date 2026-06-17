<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The 2026_06_17 residual backfill re-runs the 2026_06_04 resolution so
 * package_bundles rows that landed NULL AFTER the first backfill (legacy crm2
 * / raw inserts that skip the crm3 creating hook) get their owning package's
 * account_id — otherwise the tenant-scoped delete validation rejects them and
 * the plan can't drop a service ("The selected ids.0 is invalid"). Resolves by
 * package_id, then by random_id, never overwrites an existing account_id, and
 * leaves a row with no resolvable package NULL.
 */
class BackfillResidualPackageBundlesAccountIdTest extends TestCase
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

    private function runBackfill(): void
    {
        (require database_path(
            'migrations/2026_06_17_120000_backfill_residual_package_bundles_account_id.php',
        ))->up();
    }

    private function accountId(int $pbId): ?int
    {
        $v = DB::table('package_bundles')->where('id', $pbId)->value('account_id');

        return $v === null ? null : (int) $v;
    }

    public function test_fills_null_account_id_from_the_linked_package(): void
    {
        $pkgId = $this->makePackage('RES-A', 1);
        $pbId = $this->rawBundle(['package_id' => $pkgId, 'random_id' => 'RES-A', 'account_id' => null]);

        $this->runBackfill();

        $this->assertSame(1, $this->accountId($pbId), 'A NULL account_id must be filled from the linked package.');
    }

    public function test_fills_null_account_id_via_random_id_when_package_id_is_null(): void
    {
        $this->makePackage('RES-B', 1);
        $pbId = $this->rawBundle(['package_id' => null, 'random_id' => 'RES-B', 'account_id' => null]);

        $this->runBackfill();

        $this->assertSame(1, $this->accountId($pbId), 'An in-flight row (package_id NULL) resolves via random_id.');
    }

    public function test_does_not_overwrite_an_existing_account_id(): void
    {
        // Pre-set to a different value than the package's account — the backfill
        // only touches NULL rows, so this must be left exactly as it was.
        $pkgId = $this->makePackage('RES-C', 1);
        $pbId = $this->rawBundle(['package_id' => $pkgId, 'random_id' => 'RES-C', 'account_id' => 2]);

        $this->runBackfill();

        $this->assertSame(2, $this->accountId($pbId), 'A row that already has an account_id must not be overwritten.');
    }

    public function test_leaves_a_row_with_no_resolvable_package_null(): void
    {
        // No package_id, random_id matches no package.
        $pbId = $this->rawBundle(['package_id' => null, 'random_id' => 'RES-NO-MATCH', 'account_id' => null]);

        $this->runBackfill();

        $this->assertNull($this->accountId($pbId), 'An un-allocated row with no matching package stays NULL.');
    }
}
