<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Atomic cascade delete for configurable Buy/Get groups
 * (`PlanService::cascadeDeleteGroup`). Replaces the SPA's earlier
 * sequential per-row loop that could leave half-state on partial
 * failure (voucher refunded but row still here, or rows 1–3 gone with
 * row 4 failed). Closes G6.
 *
 * Pinned because the whole point of the endpoint is the
 * transactional guarantee — if a future refactor swaps `DB::transaction`
 * for a non-atomic loop, the rollback path silently breaks and
 * operators are back to manual reconciliation.
 *
 * Implementation note: tests build the package_bundles + package_services
 * fixture directly via `DB::table` because the model factories drift
 * from the production schema in several places (see SoldByEligibilityTest
 * for the full story). Direct inserts give us full control and stay
 * resilient to factory churn.
 */
class CascadeDeleteGroupTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PlanService $service;

    private int $patientId;

    private int $packageId;

    private int $serviceCatalogId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->service = app(PlanService::class);

        $this->patientId = (int) DB::table('users')->insertGetId([
            'name' => 'Cascade Patient',
            'email' => 'cascade-test@test.local',
            'password' => bcrypt('test'),
            'phone' => '+923009998888',
            'user_type_id' => 3,
            'account_id' => 1,
            'active' => 1,
            'main_account' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'name' => 'Cascade Centre',
            'city_id' => 1,
            'region_id' => 1,
            'address' => '1 Test St',
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->serviceCatalogId = (int) DB::table('services')->insertGetId([
            'name' => 'Test Service',
            'price' => 1000,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->packageId = (int) DB::table('packages')->insertGetId([
            'patient_id' => $this->patientId,
            'location_id' => $locationId,
            'name' => 'Cascade Plan',
            'plan_type' => 'plan',
            'total_price' => 3000,
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Build a package_bundles + package_services pair, returns the bundle id. */
    private function makeBundle(string $randomId, ?bool $consumed = false): int
    {
        $bundleId = (int) DB::table('package_bundles')->insertGetId([
            'package_id' => $this->packageId,
            'random_id' => $randomId,
            'is_allocate' => 1,
            'qty' => 1,
            'service_price' => 1000,
            'net_amount' => 1000,
            'tax_exclusive_net_amount' => 854,
            'tax_percentage' => 17,
            'tax_price' => 146,
            'tax_including_price' => 1000,
            'location_id' => 1,
            'active' => 1,
            'is_exclusive' => 0,
            'discount_name' => '-',
            'discount_type' => '-',
            'discount_price' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('package_services')->insert([
            'package_id' => $this->packageId,
            'package_bundle_id' => $bundleId,
            'service_id' => $this->serviceCatalogId,
            'price' => 1000,
            'orignal_price' => 1000,
            'tax_including_price' => 1000,
            'tax_price' => 146,
            'tax_exclusive_price' => 854,
            'tax_percentage' => 17,
            'is_consumed' => $consumed ? 1 : 0,
            'random_id' => $randomId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $bundleId;
    }

    public function test_deletes_every_id_in_a_single_transaction(): void
    {
        // Happy path — three bundles, no consumed sessions, no
        // vouchers. Cascade deletes all three atomically.
        $randomId = 'TEST-CASCADE-1';
        $idA = $this->makeBundle($randomId);
        $idB = $this->makeBundle($randomId);
        $idC = $this->makeBundle($randomId);

        $result = $this->service->cascadeDeleteGroup([
            'ids' => [$idA, $idB, $idC],
            'random_id' => $randomId,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame([$idA, $idB, $idC], $result['data']['removed_ids']);
        $this->assertSame(0, DB::table('package_bundles')->whereIn('id', [$idA, $idB, $idC])->count());
    }

    public function test_rolls_back_EVERYTHING_when_one_id_is_consumed(): void
    {
        // The G6 invariant — if any row in the batch can't be
        // deleted (e.g. consumed session), the WHOLE transaction
        // rolls back. Earlier rows that already deleted come back.
        // The legacy sequential loop committed earlier rows and
        // left half-state.
        $randomId = 'TEST-CASCADE-2';
        $idA = $this->makeBundle($randomId, consumed: false);
        $idB = $this->makeBundle($randomId, consumed: true);  // can't delete
        $idC = $this->makeBundle($randomId, consumed: false);

        $result = $this->service->cascadeDeleteGroup([
            'ids' => [$idA, $idB, $idC],
            'random_id' => $randomId,
        ]);

        $this->assertFalse($result['success']);
        // ALL three must still exist — even idA, which would have
        // been silently deleted by the old loop.
        $this->assertSame(3, DB::table('package_bundles')->whereIn('id', [$idA, $idB, $idC])->count());
    }

    public function test_returns_failure_for_empty_id_list(): void
    {
        // Defensive — an empty list isn't a valid cascade. Without
        // this guard a caller could accidentally invoke an empty
        // transaction and get a misleading success.
        $result = $this->service->cascadeDeleteGroup(['ids' => []]);
        $this->assertFalse($result['success']);
        $this->assertSame(422, $result['status_code']);
    }

    public function test_dedups_duplicate_ids_in_the_input(): void
    {
        // Idempotency — duplicate ids in the input (e.g. SPA bug,
        // double-click) shouldn't double-delete or 500 the
        // transaction. The service uses array_unique on the input.
        $randomId = 'TEST-CASCADE-3';
        $idA = $this->makeBundle($randomId);

        $result = $this->service->cascadeDeleteGroup([
            'ids' => [$idA, $idA, $idA],
            'random_id' => $randomId,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame([$idA], $result['data']['removed_ids']);
        $this->assertSame(0, DB::table('package_bundles')->where('id', $idA)->count());
    }

    public function test_voucher_refund_is_rolled_back_when_a_later_row_delete_fails(): void
    {
        // The strongest G6 claim — Step 1 (voucher refund) and
        // Step 2 (per-row delete) must roll back TOGETHER. The
        // earlier "best-effort sequential" path refunded the
        // voucher and then noticed mid-loop that a row was
        // consumed, leaving the voucher silently restored on the
        // user's ledger while the rows were still attached. Now,
        // a Step 2 failure rolls back Step 1 too.
        $voucherId = (int) DB::table('discounts')->insertGetId([
            'name' => 'Test Voucher',
            'type' => 'Fixed',
            'discount_type' => 'Fixed',
            'amount' => 500,
            'active' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // user_vouchers ties the voucher to the patient with a
        // remaining balance — refundVoucherAmount adds back to
        // this row's `amount`. Starting balance 200; we'll attempt
        // to refund 50, then assert it stays at 200.
        DB::table('user_vouchers')->insert([
            'voucher_id' => $voucherId,
            'user_id' => $this->patientId,
            'amount' => 200,
            'total_amount' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $randomId = 'TEST-CASCADE-VR';
        $idA = $this->makeBundle($randomId, consumed: false);
        $idB = $this->makeBundle($randomId, consumed: true);  // forces row-delete failure

        $result = $this->service->cascadeDeleteGroup([
            'ids' => [$idA, $idB],
            'random_id' => $randomId,
            'patient_id' => $this->patientId,
            'vouchers' => [
                ['voucher_id' => $voucherId, 'amount' => 50],
            ],
        ]);

        $this->assertFalse($result['success']);

        // Voucher balance must be UNCHANGED — the refund was
        // rolled back along with the rest of the transaction.
        // If atomicity was broken, the balance would be 250
        // (200 starting + 50 refunded but not rolled back).
        $balance = (int) DB::table('user_vouchers')
            ->where('voucher_id', $voucherId)
            ->where('user_id', $this->patientId)
            ->value('amount');
        $this->assertSame(200, $balance);

        // Rows survive too — full atomicity.
        $this->assertSame(2, DB::table('package_bundles')->whereIn('id', [$idA, $idB])->count());
    }

    public function test_recomputes_package_total_price_after_cascade(): void
    {
        // The plan-total update must run after each per-row delete
        // so the final total reflects only the rows that survived.
        // Sum over remaining package_services.tax_including_price
        // gives the right answer (existing per-row logic).
        $randomId = 'TEST-CASCADE-4';
        $idA = $this->makeBundle($randomId);
        $idB = $this->makeBundle($randomId);
        $idC = $this->makeBundle($randomId);

        $this->service->cascadeDeleteGroup([
            'ids' => [$idA, $idB],
            'random_id' => $randomId,
        ]);

        $remaining = DB::table('packages')->where('id', $this->packageId)->value('total_price');
        // Only idC's bundle (tax_including_price = 1000) survives.
        $this->assertEquals(1000, $remaining);
    }
}
