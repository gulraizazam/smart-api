<?php

declare(strict_types=1);

namespace Tests\Feature\Discounts;

use App\Models\Discounts;
use App\Models\PackageVouchers;
use App\Models\Patients;
use App\Models\UserVouchers;
use App\Services\UserManagement\UserVoucherService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Voucher redemption is a three-table dance:
 *
 *   - `discounts` (discount_type='voucher') — the voucher TYPE row (the
 *     template: "Clinic Gift 500", "VIP Voucher", etc.)
 *   - `user_vouchers` — the patient-owned BALANCE row (one row per
 *     patient + voucher_type). Holds `amount` (remaining credit) and
 *     `total_amount` (original credit).
 *   - `package_vouchers` — the USAGE row written when a voucher is
 *     applied against a package. One row per (patient, voucher,
 *     package, service). This is the journal the reports hang off.
 *
 * The audit flagged the following invariants as load-bearing:
 *
 *   1. UserVoucherService::store() writes both amount AND total_amount
 *      to the same value. A refactor that sets only one would break the
 *      "used / total" display in the patient portal.
 *
 *   2. UserVoucherService::update() forbids editing the amount once
 *      the voucher has been applied to any package. Without this guard
 *      a cashier could top up a patient's voucher after it had already
 *      been used, silently giving free services.
 *
 *   3. UserVoucherService::delete() forbids deleting a voucher that
 *      has been applied to any package. Deletion would orphan the
 *      `package_vouchers` rows and drop the audit trail.
 *
 *   4. `isUsedInPackages()` — the single source of truth for "has this
 *      user+voucher combo been redeemed?". Both update and delete
 *      hang off this check; pin it so a refactor can't silently
 *      change its meaning.
 */
class VoucherRedemptionTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private UserVoucherService $service;

    private Patients $patient;

    private Discounts $voucherType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->service = app(UserVoucherService::class);
        $this->patient = Patients::factory()->create();

        // Create the voucher TYPE row (discount_type='voucher'). Going
        // through the Discounts model keeps the audit trail happy even
        // though VoucherRedemptionTest is not asserting the audit path.
        $this->voucherType = Discounts::create([
            'name' => 'Clinic Gift 1000',
            'slug' => 'clinic-gift-1000',
            'type' => 'Fixed',
            'amount' => 1000,
            'discount_type' => 'voucher',
            'start' => now()->subDay(),
            'end' => now()->addMonth(),
            'active' => 1,
        ]);
    }

    public function test_store_creates_a_user_vouchers_row_with_balance_equal_to_total(): void
    {
        $userVoucher = $this->service->store([
            'patient_id' => $this->patient->id,
            'voucher_id' => $this->voucherType->id,
            'amount' => 1000,
        ]);

        $this->assertInstanceOf(UserVouchers::class, $userVoucher);
        $this->assertSame('1000.00', (string) $userVoucher->amount);
        $this->assertSame(
            (string) $userVoucher->total_amount,
            (string) $userVoucher->amount,
            'On create, remaining amount must equal total_amount (no redemption yet).',
        );
    }

    public function test_is_used_in_packages_returns_false_until_a_package_voucher_row_is_written(): void
    {
        $userVoucher = $this->service->store([
            'patient_id' => $this->patient->id,
            'voucher_id' => $this->voucherType->id,
            'amount' => 1000,
        ]);

        $this->assertFalse($this->service->isUsedInPackages($userVoucher));

        // Simulate a cashier redeeming the voucher against a package:
        // production writes one package_vouchers row per voucher
        // application. The row is keyed by (voucher_id, user_id) so the
        // usage check can find it even without the package_id.
        PackageVouchers::create([
            'package_random_id' => 'pkg-'.uniqid(),
            'package_id' => null,
            'voucher_id' => $this->voucherType->id,
            'user_id' => $this->patient->id,
            'amount' => 500,
            'service_id' => null,
            'main_service_id' => null,
        ]);

        $this->assertTrue($this->service->isUsedInPackages($userVoucher));
    }

    public function test_update_adjusts_total_and_remaining_amount_before_use(): void
    {
        $userVoucher = $this->service->store([
            'patient_id' => $this->patient->id,
            'voucher_id' => $this->voucherType->id,
            'amount' => 1000,
        ]);

        $result = $this->service->update($userVoucher->id, ['total_amount' => 1500]);

        $this->assertTrue($result['success']);
        $userVoucher->refresh();
        $this->assertSame('1500.00', (string) $userVoucher->total_amount);
        $this->assertSame('1500.00', (string) $userVoucher->amount);
    }

    public function test_update_refuses_amount_change_once_voucher_has_been_used(): void
    {
        $userVoucher = $this->service->store([
            'patient_id' => $this->patient->id,
            'voucher_id' => $this->voucherType->id,
            'amount' => 1000,
        ]);

        // Simulate a prior redemption.
        PackageVouchers::create([
            'package_random_id' => 'pkg-'.uniqid(),
            'package_id' => null,
            'voucher_id' => $this->voucherType->id,
            'user_id' => $this->patient->id,
            'amount' => 300,
            'service_id' => null,
            'main_service_id' => null,
        ]);

        $result = $this->service->update($userVoucher->id, ['total_amount' => 5000]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already applied', $result['message']);

        // The row must still carry the original amount — the refusal
        // does NOT partially apply the update.
        $userVoucher->refresh();
        $this->assertSame('1000.00', (string) $userVoucher->total_amount);
    }

    public function test_delete_succeeds_before_any_redemption(): void
    {
        $userVoucher = $this->service->store([
            'patient_id' => $this->patient->id,
            'voucher_id' => $this->voucherType->id,
            'amount' => 1000,
        ]);

        $result = $this->service->delete($userVoucher->id);

        $this->assertTrue($result['success']);
        // The hardening migration added soft deletes to user_vouchers
        // so the assignment ledger keeps an audit trail. The row is no
        // longer hard-deleted; it is excluded from default queries.
        $this->assertSoftDeleted('user_vouchers', ['id' => $userVoucher->id]);
    }

    public function test_delete_refuses_after_redemption(): void
    {
        $userVoucher = $this->service->store([
            'patient_id' => $this->patient->id,
            'voucher_id' => $this->voucherType->id,
            'amount' => 1000,
        ]);

        PackageVouchers::create([
            'package_random_id' => 'pkg-'.uniqid(),
            'package_id' => null,
            'voucher_id' => $this->voucherType->id,
            'user_id' => $this->patient->id,
            'amount' => 300,
            'service_id' => null,
            'main_service_id' => null,
        ]);

        $result = $this->service->delete($userVoucher->id);

        $this->assertFalse($result['success']);
        $this->assertDatabaseHas('user_vouchers', ['id' => $userVoucher->id]);
    }

    public function test_get_edit_data_returns_restricted_marker_when_voucher_was_used(): void
    {
        $userVoucher = $this->service->store([
            'patient_id' => $this->patient->id,
            'voucher_id' => $this->voucherType->id,
            'amount' => 1000,
        ]);

        // Unused → full payload.
        $editData = $this->service->getEditData($userVoucher->id);
        $this->assertFalse($editData['restricted'] ?? false);
        $this->assertArrayHasKey('voucher', $editData);

        PackageVouchers::create([
            'package_random_id' => 'pkg-'.uniqid(),
            'package_id' => null,
            'voucher_id' => $this->voucherType->id,
            'user_id' => $this->patient->id,
            'amount' => 500,
            'service_id' => null,
            'main_service_id' => null,
        ]);

        // Used → restricted marker.
        $editData = $this->service->getEditData($userVoucher->id);
        $this->assertTrue(
            $editData['restricted'] ?? false,
            'A used voucher must signal restricted=true so the edit form degrades to read-only.',
        );
    }
}
