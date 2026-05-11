<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Services\Plan\PlanDiscountService;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Voucher pricing rules on the plan-create dialog, agreed with the
 * user 2026-05-11 after the voucher on plan #47275 landed with a
 * `discount_price` of 2× the service price.
 *
 * Two contracts are pinned here:
 *
 *   1. `getDiscountInfoForPlan` and `getDiscountInfo` cap the voucher
 *      discount at `min(voucher_balance, service_price)`. The bug had
 *      these endpoints stamping the un-capped balance into
 *      `discount_price`, only clamping `net_amount` to 0 — so a
 *      Rs.50,000 voucher on a Rs.24,995 service silently wrote
 *      discount_price=50,000 to package_bundles even though only
 *      Rs.24,995 of the balance was actually applicable. The same
 *      cap logic already lives in `consumeVoucherForBundle`'s ledger
 *      decrement — this pins the SAME contract at the discount-info
 *      layer so the bundle row matches the journal.
 *
 *   2. `PlanService::buildEligibleDiscounts` excludes vouchers with
 *      `user_vouchers.amount <= 0` (depleted). Without this filter the
 *      dropdown surfaces a voucher whose balance is gone; the operator
 *      picks it, the save endpoint silently writes a row tagged with a
 *      voucher discount_id but discount_price=0, and the voucher usage
 *      report shows a "use" against a balance that was zero — a
 *      reporting / audit problem even though the math nets out.
 */
class VoucherDiscountInfoTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PlanDiscountService $discountService;

    private PlanService $planService;

    private int $patientId;

    private int $locationId;

    private int $serviceId;

    private float $servicePrice = 5000.0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $admin = $this->actingAsAdmin();

        // Role 1 (Administrator) bypasses the per-role discount gate
        // in buildEligibleDiscounts so the test's assertions about
        // voucher visibility don't accidentally fail on the role gate.
        DB::table('roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Administrator', 'guard_name' => 'web', 'commission' => 0, 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('model_has_roles')->updateOrInsert(
            ['role_id' => 1, 'model_id' => $admin->id, 'model_type' => 'App\\Models\\User'],
            [],
        );

        $this->discountService = app(PlanDiscountService::class);
        $this->planService = app(PlanService::class);

        $this->locationId = (int) DB::table('locations')->insertGetId([
            'name' => 'Test Centre',
            'city_id' => 1,
            'region_id' => 1,
            'address' => '1 Test St',
            'tax_percentage' => 0,
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patientId = (int) DB::table('users')->insertGetId([
            'name' => 'Voucher Patient',
            'email' => 'voucher-'.uniqid().'@test.local',
            'password' => bcrypt('test'),
            'phone' => '+923000000050',
            'user_type_id' => 3,
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->serviceId = (int) DB::table('services')->insertGetId([
            'name' => 'Test Service',
            'price' => $this->servicePrice,
            'end_node' => 1,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Create a voucher type + allocate it to the test service + assign
     * the given balance to the test patient. Returns the voucher's
     * discounts.id.
     */
    private function seedVoucher(float $balance): int
    {
        $voucherId = (int) DB::table('discounts')->insertGetId([
            'name' => 'Test Voucher '.uniqid(),
            'slug' => 'voucher-'.uniqid(),
            'type' => 'Fixed',
            'amount' => 0,
            'discount_type' => 'voucher',
            'pre_days' => 0,
            'post_days' => 0,
            'start' => now()->subDay(),
            'end' => now()->addMonth(),
            'active' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discount_has_locations')->insert([
            'discount_id' => $voucherId,
            'location_id' => $this->locationId,
            'service_id' => $this->serviceId,
            'type' => 'Fixed',
            'amount' => 0,
            'slug' => 'default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_vouchers')->insert([
            'user_id' => $this->patientId,
            'voucher_id' => $voucherId,
            'amount' => $balance,
            'total_amount' => $balance,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $voucherId;
    }

    public function test_voucher_balance_higher_than_service_price_caps_discount_at_service_price(): void
    {
        // Voucher balance is 8000, service is 5000. The voucher should
        // wipe the service to zero AND only the service-price worth
        // (5000) of the balance should be considered consumable on
        // this row — NOT the full 8000. Stamping 8000 as discount_price
        // would (a) make the saved row's numbers nonsensical
        // (discount > regular), (b) drift from what
        // `consumeVoucherForBundle` actually decrements from the
        // ledger.
        $voucherId = $this->seedVoucher(balance: 8000);

        $info = $this->discountService->getDiscountInfoForPlan([
            'service_id' => $this->serviceId,
            'discount_id' => $voucherId,
            'patient_id' => $this->patientId,
            'location_id' => $this->locationId,
        ]);

        $this->assertSame(
            $this->servicePrice,
            (float) $info['data']['discount_price'],
            'When voucher balance > service price, discount_price must cap at the service price.',
        );
        $this->assertSame(
            0.0,
            (float) $info['data']['net_amount'],
            'With discount capped at service price, net_amount goes to 0 (patient owes nothing on this row).',
        );
    }

    public function test_voucher_balance_lower_than_service_price_caps_discount_at_balance(): void
    {
        // Voucher balance 3000 < service price 5000. The discount
        // covers the balance, patient owes the residual 2000.
        $voucherId = $this->seedVoucher(balance: 3000);

        $info = $this->discountService->getDiscountInfoForPlan([
            'service_id' => $this->serviceId,
            'discount_id' => $voucherId,
            'patient_id' => $this->patientId,
            'location_id' => $this->locationId,
        ]);

        $this->assertSame(3000.0, (float) $info['data']['discount_price']);
        $this->assertSame(
            2000.0,
            (float) $info['data']['net_amount'],
            'Patient owes service_price - voucher_balance when the balance is smaller than the service.',
        );
    }

    public function test_voucher_with_zero_balance_returns_zero_discount(): void
    {
        // Edge case: someone pulls the catalog while the voucher is
        // depleted (the dropdown filter should block this upstream,
        // but the endpoint must also be safe on its own).
        $voucherId = $this->seedVoucher(balance: 0);

        $info = $this->discountService->getDiscountInfoForPlan([
            'service_id' => $this->serviceId,
            'discount_id' => $voucherId,
            'patient_id' => $this->patientId,
            'location_id' => $this->locationId,
        ]);

        $this->assertSame(0.0, (float) $info['data']['discount_price']);
        $this->assertSame($this->servicePrice, (float) $info['data']['net_amount']);
        $this->assertFalse(
            (bool) $info['data']['discount_is_voucher'],
            'A zero-balance voucher must not flag the row as a voucher discount — otherwise the SPA would call reserveVoucher and fail with VOUCHER_EXHAUSTED.',
        );
    }

    public function test_depleted_voucher_is_excluded_from_eligible_discounts(): void
    {
        // Same voucher type, but the patient's ledger balance is zero.
        // The voucher must NOT appear in the dropdown — otherwise an
        // operator picks it and gets a 0-discount row tagged with the
        // voucher's discount_id, distorting the voucher usage report.
        $voucherId = $this->seedVoucher(balance: 0);

        $data = $this->planService->getCreateFormDataForPatient([$this->locationId], $this->patientId);

        $ids = collect($data['discounts'])->pluck('id')->all();
        $this->assertNotContains(
            $voucherId,
            $ids,
            'Depleted vouchers (user_vouchers.amount = 0) must not appear in the discount dropdown.',
        );
    }

    public function test_voucher_with_positive_balance_does_appear_in_eligible_discounts(): void
    {
        // Inverse pin — the fix excluded depleted vouchers; make sure
        // it didn't accidentally exclude live ones too.
        $voucherId = $this->seedVoucher(balance: 2000);

        $data = $this->planService->getCreateFormDataForPatient([$this->locationId], $this->patientId);

        $ids = collect($data['discounts'])->pluck('id')->all();
        $this->assertContains(
            $voucherId,
            $ids,
            'A voucher with any positive balance must remain visible in the dropdown.',
        );
    }

    public function test_consume_voucher_for_bundle_skips_decrement_when_pre_reserved(): void
    {
        // Pre-fix double-decrement bug, observed on production plan
        // #47275. Add-row flow:
        //   1. SPA calls `reserveVoucherAmount` → decrements user_vouchers.
        //   2. SPA calls `addServiceRow` → backend `saveServiceForPlan`
        //      → `consumeVoucherForBundle` → decrements AGAIN.
        //
        // Result: voucher charged 2× per add-row click. On a 30k
        // balance + 25k service, balance went straight to 0 even
        // though only 25k was actually applied. Refunds on
        // cancel/delete only undid one of the two decrements,
        // leaving the patient permanently short.
        //
        // Fix: when the caller signals `voucher_pre_reserved`,
        // `consumeVoucherForBundle` skips the user_vouchers update
        // and only writes the PackageVouchers journal row (so the
        // delete-path auto-refund still finds it). This test pins the
        // skip — without it the regression slips back in silently.
        $voucherId = $this->seedVoucher(balance: 8000);

        // Simulate the SPA's pre-reservation (decrement by service
        // price, which is what discount_price ends up being for
        // balance > service).
        $this->discountService->reserveVoucherAmount(
            $voucherId,
            $this->patientId,
            $this->servicePrice,
        );

        $balanceAfterReserve = (float) DB::table('user_vouchers')
            ->where('voucher_id', $voucherId)
            ->where('user_id', $this->patientId)
            ->value('amount');
        $this->assertSame(
            8000.0 - $this->servicePrice,
            $balanceAfterReserve,
            'After reserveVoucherAmount, the balance must be decremented exactly once.',
        );

        // Now invoke saveServiceForPlan with the pre-reserved flag —
        // the new contract that prevents the second decrement.
        $randomId = 'TEST-PRE-RESV-'.uniqid();
        DB::table('packages')->insert([
            'patient_id' => $this->patientId,
            'location_id' => $this->locationId,
            'name' => 'Pre-reserved test',
            'plan_type' => 'plan',
            'total_price' => 0,
            'account_id' => 1,
            'active' => 1,
            'random_id' => $randomId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->discountService->saveServiceForPlan([
            'random_id' => $randomId,
            'service_id' => $this->serviceId,
            'bundle_id' => $this->serviceId,
            'discount_id' => $voucherId,
            'discount_price' => $this->servicePrice,
            'net_amount' => 0,
            'location_id' => $this->locationId,
            'user_id' => $this->patientId,
            'sold_by' => $this->patientId,
            'package_total' => '0',
            'is_exclusive' => '0',
            'voucher_pre_reserved' => '1',
        ]);

        $balanceAfterSave = (float) DB::table('user_vouchers')
            ->where('voucher_id', $voucherId)
            ->where('user_id', $this->patientId)
            ->value('amount');
        $this->assertSame(
            $balanceAfterReserve,
            $balanceAfterSave,
            'With voucher_pre_reserved=1, saveServiceForPlan must NOT decrement user_vouchers a second time.',
        );

        // The PackageVouchers journal MUST still be written — without
        // it, deletePackageService has nothing to refund against and
        // the delete-path auto-refund silently fails.
        $journal = DB::table('package_vouchers')
            ->where('package_random_id', $randomId)
            ->where('voucher_id', $voucherId)
            ->first();
        $this->assertNotNull(
            $journal,
            'PackageVouchers journal row must still be written even when the decrement is skipped — '
            . 'delete-time auto-refund reads from this journal.',
        );
        $this->assertSame(
            $this->servicePrice,
            (float) $journal->amount,
            'Journal amount should equal the SPA-reserved discount_price (= service_price cap).',
        );
    }

    public function test_delete_package_service_refund_caps_at_total_amount(): void
    {
        // Bug observed on user voucher 78 (TestIng voucher): trash of a
        // staged voucher row was bumping `user_vouchers.amount` ABOVE
        // `total_amount`. The journal carried a stale or inflated
        // `amount` (a row written before the SPA-pre-reserved fix
        // landed, or where the SPA reserved against a manually-bumped
        // balance during testing). The auto-refund inside
        // `deletePackageService` did a raw additive update with no
        // ceiling — so a Rs.6,000-issued voucher ended up showing
        // BALANCE=12,000 in the history dialog. Same invariant
        // `refundVoucherAmount` already enforces; mirroring it here
        // keeps the dialog stat sane and stops the derived
        // "consumed = total - balance" from going negative.
        $voucherId = $this->seedVoucher(balance: 6000); // total_amount=6000
        // Force the ledger to 5000 (simulate 1000 already consumed).
        DB::table('user_vouchers')
            ->where('voucher_id', $voucherId)
            ->where('user_id', $this->patientId)
            ->update(['amount' => 5000]);

        // Build a saved plan with a journal row carrying an oversized
        // amount (e.g. 9000) — the kind of stale row the bug surfaced.
        $randomId = 'CAP-'.uniqid();
        $packageId = (int) DB::table('packages')->insertGetId([
            'patient_id' => $this->patientId,
            'location_id' => $this->locationId,
            'name' => 'Cap test',
            'plan_type' => 'plan',
            'total_price' => $this->servicePrice,
            'account_id' => 1,
            'active' => 1,
            'random_id' => $randomId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bundleId = (int) DB::table('package_bundles')->insertGetId([
            'package_id' => $packageId,
            'random_id' => $randomId,
            'config_group_id' => null,
            'is_allocate' => 1,
            'qty' => 1,
            'service_price' => $this->servicePrice,
            'net_amount' => 0,
            'tax_exclusive_net_amount' => 0,
            'tax_percentage' => 0,
            'tax_price' => 0,
            'tax_including_price' => 0,
            'location_id' => $this->locationId,
            'discount_id' => $voucherId,
            'discount_name' => 'Test',
            'discount_type' => 'Fixed',
            'discount_price' => $this->servicePrice,
            'bundle_id' => $this->serviceId,
            'active' => 1,
            'is_exclusive' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('package_services')->insert([
            'package_id' => $packageId,
            'package_bundle_id' => $bundleId,
            'service_id' => $this->serviceId,
            'sold_by' => $this->patientId,
            'consumption_order' => 0,
            'is_consumed' => 0,
            'price' => $this->servicePrice,
            'orignal_price' => $this->servicePrice,
            'tax_including_price' => 0,
            'tax_price' => 0,
            'tax_exclusive_price' => 0,
            'tax_percentage' => 0,
            'random_id' => $randomId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Plant a journal row with an over-stated amount (9000 vs total=6000).
        DB::table('package_vouchers')->insert([
            'package_random_id' => $randomId,
            'voucher_id' => $voucherId,
            'user_id' => $this->patientId,
            'amount' => 9000,
            'service_id' => $bundleId,
            'main_service_id' => $this->serviceId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->planService->deletePackageService([
            'id' => $bundleId,
            'random_id' => $randomId,
            'package_total' => 0,
            'update_status' => 1,
        ]);

        $balanceAfter = (float) DB::table('user_vouchers')
            ->where('voucher_id', $voucherId)
            ->where('user_id', $this->patientId)
            ->value('amount');
        $this->assertSame(
            6000.0,
            $balanceAfter,
            'After delete, user_vouchers.amount must cap at total_amount (6000) — '
            . 'a raw 5000+9000=14000 update would inflate the balance above the originally assigned grant.',
        );
    }

    public function test_delete_package_service_refunds_the_specific_journal_row_for_this_bundle(): void
    {
        // Bug observed on plan #47283: the voucher row trash flow
        // looked the journal up by `main_service_id = bundle.bundle_id`,
        // which is the services.id rather than the per-bundle id. When
        // the same voucher had been applied to the same service across
        // multiple bundles (add-row → cancel → re-add), the wrong
        // journal entry got refunded and stale rows were left
        // floating in `package_vouchers`. Fix: look up by
        // `service_id = package_bundle.id` so each delete refunds the
        // journal it actually wrote.
        $voucherId = $this->seedVoucher(balance: 3000);
        // Drain the balance to 0 (simulating the state after both
        // bundles below have been consumed); the trash refunds the
        // matching journal back into the ledger.
        DB::table('user_vouchers')
            ->where('voucher_id', $voucherId)
            ->where('user_id', $this->patientId)
            ->update(['amount' => 0]);

        $randomId = 'JRN-'.uniqid();
        $packageId = (int) DB::table('packages')->insertGetId([
            'patient_id' => $this->patientId,
            'location_id' => $this->locationId,
            'name' => 'Journal test',
            'plan_type' => 'plan',
            'total_price' => $this->servicePrice * 2,
            'account_id' => 1,
            'active' => 1,
            'random_id' => $randomId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Two bundles applied to the SAME service id. Each writes a
        // distinct journal row keyed on its own package_bundle id.
        $bundleA = $this->seedVoucherBundle($packageId, $randomId, $voucherId, journalAmount: 1500);
        $bundleB = $this->seedVoucherBundle($packageId, $randomId, $voucherId, journalAmount: 1200);

        // Trash bundle A. The journal row referencing bundle A
        // (service_id = $bundleA) must be the one that gets refunded
        // — not the row for bundle B. Pre-fix this was non-deterministic
        // (PackageVouchers::first() picked whichever ordered first).
        $this->planService->deletePackageService([
            'id' => $bundleA,
            'random_id' => $randomId,
            'package_total' => 0,
            'update_status' => 1,
        ]);

        $remainingJournal = DB::table('package_vouchers')
            ->where('package_random_id', $randomId)
            ->get();
        $this->assertCount(
            1,
            $remainingJournal,
            'Exactly one journal row must remain after the per-bundle delete; the other was the trashed bundle\'s row and should be cleaned up.',
        );
        $this->assertSame(
            $bundleB,
            (int) $remainingJournal->first()->service_id,
            'The journal row that remains must be the one referencing bundle B — bundle A\'s journal is the one we just refunded.',
        );
        $this->assertSame(
            1500.0,
            (float) DB::table('user_vouchers')->where('voucher_id', $voucherId)->where('user_id', $this->patientId)->value('amount'),
            'Balance must reflect the refund of bundle A\'s journal amount (1500), not bundle B\'s (1200) — the lookup must pin the right row.',
        );
    }

    private function seedVoucherBundle(int $packageId, string $randomId, int $voucherId, float $journalAmount): int
    {
        $bundleId = (int) DB::table('package_bundles')->insertGetId([
            'package_id' => $packageId,
            'random_id' => $randomId,
            'is_allocate' => 1,
            'qty' => 1,
            'service_price' => $this->servicePrice,
            'net_amount' => $this->servicePrice - $journalAmount,
            'tax_exclusive_net_amount' => 0,
            'tax_percentage' => 0,
            'tax_price' => 0,
            'tax_including_price' => 0,
            'location_id' => $this->locationId,
            'discount_id' => $voucherId,
            'discount_name' => 'Voucher',
            'discount_type' => 'Fixed',
            'discount_price' => $journalAmount,
            'bundle_id' => $this->serviceId,
            'active' => 1,
            'is_exclusive' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('package_services')->insert([
            'package_id' => $packageId,
            'package_bundle_id' => $bundleId,
            'service_id' => $this->serviceId,
            'sold_by' => $this->patientId,
            'consumption_order' => 0,
            'is_consumed' => 0,
            'price' => $this->servicePrice,
            'orignal_price' => $this->servicePrice,
            'tax_including_price' => 0,
            'tax_price' => 0,
            'tax_exclusive_price' => 0,
            'tax_percentage' => 0,
            'random_id' => $randomId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('package_vouchers')->insert([
            'package_random_id' => $randomId,
            'voucher_id' => $voucherId,
            'user_id' => $this->patientId,
            'amount' => $journalAmount,
            'service_id' => $bundleId, // per-bundle id — the discriminator the fixed lookup keys on.
            'main_service_id' => $this->serviceId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $bundleId;
    }

    public function test_consume_voucher_for_bundle_still_decrements_when_not_pre_reserved(): void
    {
        // Inverse pin for the legacy admin (Blade plan-form.js) path.
        // The Blade form does NOT call reserveVoucherAmount before
        // hitting savepackages_service_for_plan — so the backend has
        // to do the decrement itself when `voucher_pre_reserved` is
        // absent. Removing this behaviour would silently stop
        // decrementing vouchers on every legacy admin save.
        $voucherId = $this->seedVoucher(balance: 8000);

        $randomId = 'TEST-NO-PRE-'.uniqid();
        DB::table('packages')->insert([
            'patient_id' => $this->patientId,
            'location_id' => $this->locationId,
            'name' => 'No-pre-reserve test',
            'plan_type' => 'plan',
            'total_price' => 0,
            'account_id' => 1,
            'active' => 1,
            'random_id' => $randomId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Call saveServiceForPlan WITHOUT the pre-reserved flag — the
        // legacy admin's behaviour.
        $this->discountService->saveServiceForPlan([
            'random_id' => $randomId,
            'service_id' => $this->serviceId,
            'bundle_id' => $this->serviceId,
            'discount_id' => $voucherId,
            'discount_price' => $this->servicePrice,
            'net_amount' => 0,
            'location_id' => $this->locationId,
            'user_id' => $this->patientId,
            'sold_by' => $this->patientId,
            'package_total' => '0',
            'is_exclusive' => '0',
            // no voucher_pre_reserved
        ]);

        $balanceAfter = (float) DB::table('user_vouchers')
            ->where('voucher_id', $voucherId)
            ->where('user_id', $this->patientId)
            ->value('amount');
        $this->assertSame(
            8000.0 - $this->servicePrice,
            $balanceAfter,
            'Without voucher_pre_reserved, saveServiceForPlan must decrement user_vouchers by service_price.',
        );
    }
}
