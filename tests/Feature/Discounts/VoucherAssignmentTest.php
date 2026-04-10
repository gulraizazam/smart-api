<?php

declare(strict_types=1);

namespace Tests\Feature\Discounts;

use App\Models\Discounts;
use App\Models\Patients;
use App\Models\UserVouchers;
use App\Services\Voucher\VoucherTypeService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Vouchers are stored in the same `discounts` table as regular
 * discounts — the only distinguishing column is
 * `discount_type = 'voucher'`. The VoucherTypeService is the only
 * sanctioned write path for voucher rows and the ONLY path that writes
 * to `user_vouchers` (the patient-level assignment ledger).
 *
 * The audit flagged several invariants that these tests pin:
 *
 *   1. `store()` ALWAYS stamps discount_type='voucher' and type='Fixed'.
 *      A refactor that lets a caller smuggle `discount_type='Treatment'`
 *      through the create path would silently create a regular discount
 *      via the voucher screen.
 *
 *   2. `assignToPatient()` refuses to write a user_vouchers row if the
 *      voucher type is inactive — this is the front-line check against
 *      handing expired vouchers to patients.
 *
 *   3. `update()` forbids renaming a voucher type that has already
 *      been assigned to patients. Renaming would silently change the
 *      label on past redemption records and distort reports.
 *
 *   4. `delete()` refuses to delete a voucher type that has active
 *      assignments. Cascade-deleting user_vouchers would destroy the
 *      patient's claim on a redeemable balance.
 */
class VoucherAssignmentTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private VoucherTypeService $service;

    private Patients $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->service = app(VoucherTypeService::class);
        $this->patient = Patients::factory()->create();
    }

    public function test_store_forces_discount_type_voucher_and_type_fixed(): void
    {
        // Attempt to smuggle a non-voucher discount_type through the
        // service — it must be overwritten to 'voucher'.
        $voucher = $this->service->store([
            'name' => 'Clinic Gift 1000',
            'amount' => 1000,
            'start' => now()->subDay()->format('Y-m-d'),
            'end' => now()->addMonth()->format('Y-m-d'),
            'slug' => 'gift-1000',
            // Callers passing these values should NOT win — the service
            // unconditionally stamps them back to the voucher defaults.
            'discount_type' => 'Treatment',
            'type' => 'Percentage',
        ]);

        $this->assertSame('voucher', $voucher->discount_type);
        $this->assertSame('Fixed', $voucher->type);
        $this->assertDatabaseHas('discounts', [
            'id' => $voucher->id,
            'discount_type' => 'voucher',
            'type' => 'Fixed',
        ]);
    }

    public function test_assign_to_patient_creates_a_user_vouchers_row_with_balance(): void
    {
        $voucher = $this->service->store([
            'name' => 'Gift 500',
            'amount' => 500,
            'start' => now()->subDay()->format('Y-m-d'),
            'end' => now()->addMonth()->format('Y-m-d'),
            'slug' => 'gift-500',
            'active' => 1,
        ]);

        $result = $this->service->assignToPatient($voucher->id, $this->patient->id, 500.00);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('user_vouchers', [
            'user_id' => $this->patient->id,
            'voucher_id' => $voucher->id,
            'amount' => 500.00,
            'total_amount' => 500.00,
        ]);
    }

    public function test_assign_to_patient_refuses_when_voucher_type_is_inactive(): void
    {
        $voucher = $this->service->store([
            'name' => 'Stale Gift',
            'amount' => 100,
            'start' => now()->subDay()->format('Y-m-d'),
            'end' => now()->addMonth()->format('Y-m-d'),
            'slug' => 'stale-gift',
            'active' => 0, // inactive
        ]);

        $result = $this->service->assignToPatient($voucher->id, $this->patient->id, 100.00);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('inactive', $result['message']);
        $this->assertSame(0, UserVouchers::query()->count());
    }

    public function test_assign_to_patient_returns_error_for_unknown_voucher_id(): void
    {
        $result = $this->service->assignToPatient(99999, $this->patient->id, 100.00);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    public function test_update_forbids_renaming_an_already_assigned_voucher_type(): void
    {
        $voucher = $this->service->store([
            'name' => 'Original Name',
            'amount' => 250,
            'start' => now()->subDay()->format('Y-m-d'),
            'end' => now()->addMonth()->format('Y-m-d'),
            'slug' => 'original-name',
            'active' => 1,
        ]);

        $this->service->assignToPatient($voucher->id, $this->patient->id, 250.00);

        $result = $this->service->update([
            'name' => 'Renamed Voucher',
            'amount' => 250,
            'start' => now()->subDay()->format('Y-m-d'),
            'end' => now()->addMonth()->format('Y-m-d'),
            'slug' => 'original-name',
        ], $voucher->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Cannot change voucher name', $result['message']);

        $this->assertDatabaseHas('discounts', [
            'id' => $voucher->id,
            'name' => 'Original Name',
        ]);
    }

    public function test_update_allows_non_name_field_changes_on_an_assigned_voucher(): void
    {
        $voucher = $this->service->store([
            'name' => 'Keep This Name',
            'amount' => 300,
            'start' => now()->subDay()->format('Y-m-d'),
            'end' => now()->addMonth()->format('Y-m-d'),
            'slug' => 'keep-this',
            'active' => 1,
        ]);

        $this->service->assignToPatient($voucher->id, $this->patient->id, 300.00);

        $result = $this->service->update([
            // Same name — the update path strips the name field when
            // the voucher is assigned, so this should succeed.
            'name' => 'Keep This Name',
            'amount' => 350,
            'start' => now()->subDay()->format('Y-m-d'),
            'end' => now()->addMonths(2)->format('Y-m-d'),
            'slug' => 'keep-this',
        ], $voucher->id);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('discounts', [
            'id' => $voucher->id,
            'amount' => 350,
        ]);
    }

    public function test_delete_refuses_to_destroy_an_assigned_voucher_type(): void
    {
        $voucher = $this->service->store([
            'name' => 'Protected Voucher',
            'amount' => 500,
            'start' => now()->subDay()->format('Y-m-d'),
            'end' => now()->addMonth()->format('Y-m-d'),
            'slug' => 'protected',
            'active' => 1,
        ]);

        $this->service->assignToPatient($voucher->id, $this->patient->id, 500.00);

        $result = $this->service->delete($voucher->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already assigned', $result['message']);
        // The row must survive the refused delete.
        $this->assertDatabaseHas('discounts', [
            'id' => $voucher->id,
            'deleted_at' => null,
        ]);
    }

    public function test_delete_succeeds_for_an_unassigned_voucher_type(): void
    {
        $voucher = $this->service->store([
            'name' => 'Unused Voucher',
            'amount' => 200,
            'start' => now()->subDay()->format('Y-m-d'),
            'end' => now()->addMonth()->format('Y-m-d'),
            'slug' => 'unused',
            'active' => 1,
        ]);

        $result = $this->service->delete($voucher->id);

        $this->assertTrue($result['success']);
        // Soft-deleted row: discount_type is still 'voucher' but
        // deleted_at is stamped.
        $this->assertSoftDeleted('discounts', ['id' => $voucher->id]);
    }

    public function test_toggle_status_flips_active_flag_idempotently(): void
    {
        $voucher = $this->service->store([
            'name' => 'Toggle Voucher',
            'amount' => 100,
            'start' => now()->subDay()->format('Y-m-d'),
            'end' => now()->addMonth()->format('Y-m-d'),
            'slug' => 'toggle',
            'active' => 1,
        ]);

        $this->service->toggleStatus($voucher->id, 0);
        $this->assertDatabaseHas('discounts', ['id' => $voucher->id, 'active' => 0]);

        $this->service->toggleStatus($voucher->id, 1);
        $this->assertDatabaseHas('discounts', ['id' => $voucher->id, 'active' => 1]);
    }
}
