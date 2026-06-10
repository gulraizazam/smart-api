<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Http\Controllers\Admin\PackagesController;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PackageService;
use App\Models\Patients;
use App\Models\PaymentModes;
use App\Models\Services;
use App\Models\Settings;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The refund-form data the collector sees on screen — the "refundable
 * amount" — is computed in PlanRefundService::getRefundFormData. The audit
 * pinned the formula as:
 *
 *     refundable_amount =
 *         ceil( cash_received                  -- sum(in advances, !cancel, !setteled)
 *             - consumed_service_price         -- sum(package_service.price where is_consumed=1)
 *             - documentation_charges          -- settings.sys-documentationcharges
 *             - already_refunded               -- sum(out, refund=1, !tax)
 *             - already_settled )              -- sum(out, setteled=1, !tax)
 *
 * Three concrete failure modes the audit cared about:
 *
 *   1. Cancelled `in` advances must be EXCLUDED from cash_received. If a
 *      collector cancelled an invoice and the corresponding `in` row was
 *      flipped to is_cancel=1, the refundable amount must drop by that
 *      whole row — otherwise the till is double-credited.
 *   2. Already-refunded amounts must be SUBTRACTED. Without this, a
 *      collector could refund the same package twice and walk out with
 *      double the cash.
 *   3. Documentation charges must be SUBTRACTED. The clinic charges a fee
 *      to process a refund; the formula bakes that in so the collector
 *      cannot accidentally refund the gross amount.
 *
 * "Concurrency" angle: the refund form is non-locking on purpose (it's a
 * read), so the contract under test is **arithmetic consistency** — given
 * a snapshot of advances, the formula returns exactly that snapshot's
 * answer. Tests follow the pattern: build a known ledger, call the
 * controller, assert the refundable_amount field equals the formula by
 * hand.
 *
 * If you change the formula in PlanRefundService and these tests fail,
 * STOP and audit whether the new formula matches the audit's spec —
 * silently weakening these to pass loses the property under test.
 */
class ConcurrentRefundCalculationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private Packages $package;

    private Patients $patient;

    private Locations $location;

    private PaymentModes $cash;

    private Services $service;

    private const DOCUMENTATION_CHARGES = 500;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->location = Locations::factory()->create();
        $this->cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();
        $this->patient = Patients::factory()->create();
        $this->service = Services::factory()->create([
            'name' => 'Refund Calc Service',
            'price' => 1000,
        ]);
        $this->package = Packages::factory()->create([
            'patient_id' => $this->patient->id,
            'location_id' => $this->location->id,
            'total_price' => 10000,
        ]);

        // PackageBundles row is dereferenced for ->tax_percentage inside
        // getRefundFormData; without it the service crashes before the
        // formula is exercised. Default tax = 0 so the consumed-tax branch
        // stays neutral and the assertions pin the core formula only.
        PackageBundles::query()->create([
            'random_id' => (string) \Illuminate\Support\Str::uuid(),
            'package_id' => $this->package->id,
            'qty' => 1,
            'source_type' => 'service', // valid tag required by the model guard
            'service_price' => 10000,
            'net_amount' => 10000,
            'tax_exclusive_net_amount' => 10000,
            'tax_percentage' => 0,
            'tax_price' => 0,
            'tax_including_price' => 10000,
            'orignal_price' => 10000,
            'active' => 1,
        ]);

        // The refund formula reads sys-documentationcharges as the fee
        // deducted from refundable. Pin the value here so all assertions
        // can subtract a known constant rather than reading the setting
        // back from the DB.
        Settings::query()->create([
            'name' => 'Documentation Charges',
            'slug' => 'sys-documentationcharges',
            'data' => (string) self::DOCUMENTATION_CHARGES,
            'account_id' => 1,
            'active' => 1,
        ]);
    }

    public function test_refundable_amount_with_no_consumption_no_prior_refund(): void
    {
        // Baseline: 5000 in, no consumption, no prior refund, no settlement.
        // Formula: 5000 - 0 - 500(doc) - 0 - 0 = 4500.
        $this->makeInAdvance(5000);
        $this->makePriorRefundAdvance(0.01); // sentinel — getRefundFormData dereferences ->cash_amount

        $body = $this->callEditRefund();

        $this->assertSame(
            4500.0,
            (float) $body['data']['refundable_amount'],
            'cash_received(5000) - documentation(500) = 4500. '
            . 'If you see 5000 here, documentation charges are not being deducted.'
        );
        $this->assertSame(5000.0, (float) $body['data']['cash_amount']);
    }

    public function test_consumed_services_reduce_refundable_amount(): void
    {
        // 5000 in, 1500 of services consumed, 500 doc charges.
        // Formula: 5000 - 1500 - 500 = 3000.
        $this->makeInAdvance(5000);
        $this->makePriorRefundAdvance(0.01);

        // Two consumed services @ 750 each = 1500 consumed.
        PackageService::factory()->consumed()->create([
            'package_id' => $this->package->id,
            'service_id' => $this->service->id,
            'price' => 750,
            'orignal_price' => 750,
            'actual_price' => 750,
            'tax_exclusive_price' => 750,
            'tax_including_price' => 750,
        ]);
        PackageService::factory()->consumed()->create([
            'package_id' => $this->package->id,
            'service_id' => $this->service->id,
            'price' => 750,
            'orignal_price' => 750,
            'actual_price' => 750,
            'tax_exclusive_price' => 750,
            'tax_including_price' => 750,
        ]);

        $body = $this->callEditRefund();

        $this->assertSame(
            3000.0,
            (float) $body['data']['refundable_amount'],
            '5000 - 1500(consumed) - 500(doc) = 3000.'
        );
    }

    public function test_already_refunded_amount_is_subtracted(): void
    {
        // 5000 in, 1000 already refunded, no consumption.
        // Formula: 5000 - 0 - 500(doc) - 1000(refunded) = 3500.
        // Without the subtraction, a collector could refund the same
        // package a second time for another full amount — direct cash loss.
        $this->makeInAdvance(5000);
        $this->makePriorRefundAdvance(1000);

        $body = $this->callEditRefund();

        $this->assertSame(
            3500.0,
            (float) $body['data']['refundable_amount'],
            '5000 - 500(doc) - 1000(already refunded) = 3500. '
            . 'If you see 4500 here, already-refunded amounts are NOT being deducted '
            . 'and the same package can be refunded twice.'
        );
    }

    public function test_already_settled_amount_is_subtracted(): void
    {
        // 5000 in, 800 settled (not refunded, just adjusted off).
        // Formula: 5000 - 0 - 500(doc) - 800(settled) = 3700.
        $this->makeInAdvance(5000);
        $this->makePriorRefundAdvance(0.01);
        $this->makeSettledAdvance(800);

        $body = $this->callEditRefund();

        $this->assertSame(
            3700.0,
            (float) $body['data']['refundable_amount'],
            '5000 - 500(doc) - 800(settled) = 3700.'
        );
    }

    public function test_cancelled_in_advance_is_excluded_from_cash_received(): void
    {
        // 5000 collected, but the original invoice was cancelled and the
        // 5000 row was flipped to is_cancel=1. A second 3000 in advance
        // was then collected. Formula: 3000 - 500 = 2500.
        // If is_cancel=1 rows still feed the sum, the test sees 7500
        // refundable on a 3000 till — the till goes negative.
        $cancelled = $this->makeInAdvance(5000);
        $cancelled->is_cancel = '1';
        $cancelled->save();

        $this->makeInAdvance(3000);
        $this->makePriorRefundAdvance(0.01);

        $body = $this->callEditRefund();

        $this->assertSame(
            2500.0,
            (float) $body['data']['refundable_amount'],
            'Only the 3000 non-cancelled in advance counts. '
            . '3000 - 500(doc) = 2500. '
            . 'If you see 7500 here, cancelled in advances are leaking into cash_received.'
        );
        $this->assertSame(3000.0, (float) $body['data']['cash_amount']);
    }

    public function test_refund_calculation_is_consistent_after_a_new_in_advance_is_saved(): void
    {
        // The "concurrency" property: the refund form is read-only and
        // does not lock, so two collectors viewing the same form can see
        // a transient state. The contract is that whichever collector
        // submits last sees the up-to-date calculation. Pin it: snapshot
        // the form, persist a new in advance, snapshot again — the second
        // snapshot must reflect the new in advance exactly.
        $this->makeInAdvance(5000);
        $this->makePriorRefundAdvance(0.01);

        $first = $this->callEditRefund();
        $this->assertSame(4500.0, (float) $first['data']['refundable_amount']);

        // A second collector saves another 2000 in advance — this is what
        // the production lockForUpdate path persists in
        // PackageAdvancesController::savepackagesadvances.
        $this->makeInAdvance(2000);

        $second = $this->callEditRefund();
        $this->assertSame(
            6500.0,
            (float) $second['data']['refundable_amount'],
            'After a new 2000 in advance is committed, refundable should be '
            . '7000 - 500(doc) = 6500. '
            . 'A stale 4500 here means the refund form is reading from a cache '
            . 'or a snapshot that does not reflect committed advances.'
        );
        $this->assertSame(7000.0, (float) $second['data']['cash_amount']);
    }

    public function test_refundable_floor_is_zero_never_negative(): void
    {
        // Consumption > cash means refundable would go negative; the
        // formula floors at 0. Without the floor a collector could see a
        // negative refundable amount and (worst case) the UI could
        // interpret it as an arrears the patient owes — confusing at best,
        // exploitable at worst if it round-trips into a charge.
        $this->makeInAdvance(2000);
        $this->makePriorRefundAdvance(0.01);

        PackageService::factory()->consumed()->create([
            'package_id' => $this->package->id,
            'service_id' => $this->service->id,
            'price' => 5000,
            'orignal_price' => 5000,
            'actual_price' => 5000,
            'tax_exclusive_price' => 5000,
            'tax_including_price' => 5000,
        ]);

        $body = $this->callEditRefund();

        $this->assertSame(
            0.0,
            (float) $body['data']['refundable_amount'],
            'Negative refundable must be floored to 0.'
        );
    }

    /**
     * Insert an `in` flow advance against the test package. Returns the
     * inserted row so callers can mutate it (e.g. to set is_cancel=1 in
     * the cross-cancel test).
     */
    private function makeInAdvance(float $amount): PackageAdvances
    {
        return PackageAdvances::factory()->create([
            'package_id' => $this->package->id,
            'patient_id' => $this->patient->id,
            'location_id' => $this->location->id,
            'payment_mode_id' => $this->cash->id,
            'cash_flow' => 'in',
            'cash_amount' => $amount,
            'is_cancel' => '0',
            'is_setteled' => '0',
            'is_refund' => '0',
            'is_tax' => '0',
        ]);
    }

    /**
     * Insert an `out` flow refund advance. The refund-form code dereferences
     * `$packageRefundedAmount->cash_amount` and `->id` directly — if no
     * out/refund row exists for the package, it crashes before the
     * formula runs. Tests that don't actually want to assert refunded
     * subtraction still need a sentinel row (cash_amount > 0 because the
     * query has `cash_amount > 0`).
     */
    private function makePriorRefundAdvance(float $amount): PackageAdvances
    {
        return PackageAdvances::factory()->create([
            'package_id' => $this->package->id,
            'patient_id' => $this->patient->id,
            'location_id' => $this->location->id,
            'payment_mode_id' => $this->cash->id,
            'cash_flow' => 'out',
            'cash_amount' => $amount,
            'is_cancel' => '0',
            'is_setteled' => '0',
            'is_refund' => '1',
            'is_tax' => '0',
        ]);
    }

    /**
     * Insert an `out` flow settled adjustment advance.
     */
    private function makeSettledAdvance(float $amount): PackageAdvances
    {
        return PackageAdvances::factory()->create([
            'package_id' => $this->package->id,
            'patient_id' => $this->patient->id,
            'location_id' => $this->location->id,
            'payment_mode_id' => $this->cash->id,
            'cash_flow' => 'out',
            'cash_amount' => $amount,
            'is_cancel' => '0',
            'is_setteled' => '1',
            'is_refund' => '0',
            'is_tax' => '0',
        ]);
    }

    /**
     * @return array{status?: int, message?: string, data?: array<string, mixed>}
     */
    private function callEditRefund(): array
    {
        $response = app(PackagesController::class)->editRefund($this->package->id);

        return json_decode($response->getContent(), true) ?? [];
    }
}
