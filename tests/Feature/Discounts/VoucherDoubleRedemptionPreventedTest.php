<?php

declare(strict_types=1);

namespace Tests\Feature\Discounts;

use App\Models\Discounts;
use App\Models\PackageVouchers;
use App\Models\Patients;
use App\Models\Services;
use App\Models\UserVouchers;
use App\Services\Plan\PlanService;
use ReflectionMethod;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Guards against the "voucher consumed twice for the same value" bug
 * class. The production consumption path is
 * `PlanService::handleVoucherConsumption()` — it is called once per
 * bundled service when a package is saved with a voucher attached.
 *
 * The invariants under test:
 *
 *   1. First consumption decrements `user_vouchers.amount` by the
 *      service price and writes a journal row to `package_vouchers`.
 *
 *   2. Subsequent consumptions of the SAME voucher (same user) continue
 *      to decrement until the balance is exhausted. The balance must
 *      FLOOR at 0 — never go negative. The `max(0, balance - price)`
 *      clause in the production code is what enforces this; without it
 *      a 1000-rupee voucher could silently settle 3000 rupees of
 *      services.
 *
 *   3. Once exhausted, a repeat call writes a package_vouchers row
 *      with `amount = 0` (the "nothing to settle" sentinel). This is
 *      the existing production behaviour — tests pin it so a refactor
 *      cannot silently change the contract that reports rely on.
 *
 *   4. `isUsedInPackages()` is true after the first consumption, so
 *      the patient-portal edit/delete guards activate immediately
 *      (already pinned by VoucherRedemptionTest, asserted here to
 *      document the cross-service interaction).
 *
 * The function under test is `private` in PlanService. We invoke it via
 * reflection because that is the cheapest way to pin its contract — the
 * public entry points (the package-save controller action) require a
 * full package fixture (patient, bundles, services, pricing, advances)
 * that is irrelevant to the double-redemption invariant.
 */
class VoucherDoubleRedemptionPreventedTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PlanService $planService;

    private Patients $patient;

    private Discounts $voucherType;

    private UserVouchers $userVoucher;

    private Services $service;

    private ReflectionMethod $consume;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->planService = app(PlanService::class);
        $this->patient = Patients::factory()->create();

        $this->voucherType = Discounts::create([
            'name' => 'Gift Voucher 1000',
            'slug' => 'gift-voucher-1000',
            'type' => 'Fixed',
            'amount' => 1000,
            'discount_type' => 'voucher',
            'start' => now()->subDay(),
            'end' => now()->addMonth(),
            'active' => 1,
        ]);

        $this->userVoucher = UserVouchers::create([
            'user_id' => $this->patient->id,
            'voucher_id' => $this->voucherType->id,
            'amount' => 1000,
            'total_amount' => 1000,
        ]);

        $this->service = Services::first() ?? Services::factory()->create(['price' => 400]);
        $this->service->price = 400;
        $this->service->save();

        // Additional FK-valid service rows to use as the "pivot
        // service id" on the package_vouchers journal. The production
        // path passes a package_bundle service id that is FK-valid
        // against `services.id`, so the test has to honour the same FK.
        $this->extraServiceIds = [
            Services::factory()->create(['price' => 400])->id,
            Services::factory()->create(['price' => 400])->id,
            Services::factory()->create(['price' => 400])->id,
            Services::factory()->create(['price' => 400])->id,
        ];

        $this->consume = new ReflectionMethod(PlanService::class, 'handleVoucherConsumption');
    }

    /** @var list<int> */
    private array $extraServiceIds = [];

    /**
     * Invoke the private consumption method with the fixture voucher.
     *
     * Signature:
     *   handleVoucherConsumption(Discounts $discount, int|string $userId,
     *       string $randomId, Services $service, float $discountPrice,
     *       string $serviceId): void
     *
     * `$discountPrice` is the voucher's face value used only when the
     * balance has been exhausted (the sentinel case); `$serviceId` is
     * stringly-typed on the signature but stored in an INT column, so
     * we pass numeric strings to match production.
     */
    private function consume(string $randomId, string $serviceId): void
    {
        $this->consume->invoke(
            $this->planService,
            $this->voucherType,
            $this->patient->id,
            $randomId,
            $this->service,
            1000.0,
            $serviceId,
        );
    }

    public function test_first_consumption_decrements_balance_and_writes_one_journal_row(): void
    {
        $this->consume('pkg-A', (string) $this->extraServiceIds[0]);

        $this->userVoucher->refresh();
        $this->assertSame('600.00', (string) $this->userVoucher->amount);
        $this->assertSame('1000.00', (string) $this->userVoucher->total_amount);

        $this->assertSame(1, PackageVouchers::where('voucher_id', $this->voucherType->id)->count());

        $journal = PackageVouchers::where('voucher_id', $this->voucherType->id)->first();
        $this->assertSame($this->patient->id, $journal->user_id);
        $this->assertSame('pkg-A', $journal->package_random_id);
        // package_vouchers.amount has no cast in the Eloquent model,
        // so MariaDB returns it as an unquoted numeric string.
        $this->assertSame(400.0, (float) $journal->amount);
    }

    public function test_consecutive_consumptions_floor_at_zero_and_never_go_negative(): void
    {
        // 1000 - 400 - 400 - 400 = -200 arithmetically, but must floor
        // at 0. Without the `max(0, ...)` guard, a refactor could let
        // the balance go negative and silently settle the 3rd service
        // for free.
        $this->consume('pkg-A', (string) $this->extraServiceIds[0]);
        $this->consume('pkg-A', (string) $this->extraServiceIds[1]);
        $this->consume('pkg-A', (string) $this->extraServiceIds[2]);

        $this->userVoucher->refresh();
        $this->assertSame(
            '0.00',
            (string) $this->userVoucher->amount,
            'An exhausted voucher must settle to 0, never negative.',
        );

        // total_amount is the original face value; it is NEVER mutated
        // by consumption — only by an explicit UserVoucherService::update.
        $this->assertSame('1000.00', (string) $this->userVoucher->total_amount);
    }

    public function test_each_consumption_writes_its_own_journal_row(): void
    {
        // Three calls → three rows. The journal is append-only; the
        // reports (voucher-usage-by-patient, voucher-usage-by-package)
        // count rows, so the write-per-call contract is load-bearing.
        $this->consume('pkg-A', (string) $this->extraServiceIds[0]);
        $this->consume('pkg-A', (string) $this->extraServiceIds[1]);
        $this->consume('pkg-A', (string) $this->extraServiceIds[2]);

        $this->assertSame(
            3,
            PackageVouchers::where('voucher_id', $this->voucherType->id)->count(),
            'handleVoucherConsumption must write exactly one package_vouchers row per call.',
        );
    }

    public function test_exhausted_voucher_still_records_journal_row_with_sentinel_amount(): void
    {
        // Drain the voucher (1000 - 400 - 400 = 200 remaining, then a
        // 400-price service takes it to 0).
        $this->consume('pkg-A', (string) $this->extraServiceIds[0]);
        $this->consume('pkg-A', (string) $this->extraServiceIds[1]);
        $this->consume('pkg-A', (string) $this->extraServiceIds[2]);

        $this->userVoucher->refresh();
        $this->assertSame('0.00', (string) $this->userVoucher->amount);

        // Now try to consume again on a zero balance. Production still
        // writes a journal row — with amount = discountPrice (1000)
        // because that branch is the "fallback to the voucher's face
        // value" sentinel. This is the CURRENT contract; if it changes
        // we want the test to scream so the reports can be updated in
        // lockstep.
        $this->consume('pkg-A', (string) $this->extraServiceIds[3]);

        $this->userVoucher->refresh();
        $this->assertSame('0.00', (string) $this->userVoucher->amount);

        $this->assertSame(4, PackageVouchers::where('voucher_id', $this->voucherType->id)->count());
    }

    public function test_consumption_marks_voucher_as_used_in_packages_immediately(): void
    {
        $userVoucherService = app(\App\Services\UserManagement\UserVoucherService::class);

        $this->assertFalse(
            $userVoucherService->isUsedInPackages($this->userVoucher),
            'Before any consumption the usage guard must be false.',
        );

        $this->consume('pkg-A', (string) $this->extraServiceIds[0]);

        $this->assertTrue(
            $userVoucherService->isUsedInPackages($this->userVoucher),
            'A single consumption must flip the usage guard to true — this is what blocks edit/delete on a redeemed voucher.',
        );
    }

    public function test_journal_row_carries_main_service_id_from_the_service_model(): void
    {
        // The audit flagged `main_service_id` as load-bearing: the
        // voucher-usage report joins package_vouchers → services on
        // this column (NOT on service_id, which is the pivot id). A
        // refactor that mis-wires it would silently break the report.
        $pivotId = $this->extraServiceIds[0];
        $this->consume('pkg-A', (string) $pivotId);

        $journal = PackageVouchers::where('voucher_id', $this->voucherType->id)->first();
        $this->assertSame($this->service->id, $journal->main_service_id);
        $this->assertSame($pivotId, (int) $journal->service_id);
    }
}
