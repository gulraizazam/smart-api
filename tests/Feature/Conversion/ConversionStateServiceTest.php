<?php

declare(strict_types=1);

namespace Tests\Feature\Conversion;

use App\Models\AppointmentStatuses;
use App\Models\Appointments;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\Patients;
use App\Services\Conversion\ConversionStateService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Unit-level coverage for `ConversionStateService` — the single source
 * of truth for "is this appointment still validly converted?".
 *
 * Pins the contract that downstream triggers (RefundService,
 * deletePayment, deletePlan) and read-side reports rely on:
 *
 *   1. netCashForPlan: cash_in − cash_out_refund, soft-delete aware.
 *   2. netCashForAppointment: sums across multiple plans (multi-plan
 *      patient — refund on one plan must NOT trigger revert if another
 *      keeps net cash positive).
 *   3. shouldRevert: returns false when appointment isn't currently
 *      Converted, regardless of net cash position.
 *   4. shouldRevert: returns true when appointment is Converted AND
 *      net cash ≤ 0 (Q2=strict).
 *   5. revertIfNeeded: idempotent — calling twice doesn't re-revert
 *      a row that's already Arrived.
 */
class ConversionStateServiceTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private int $arrivedStatusId;
    private int $convertedStatusId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        // Auto-increment doesn't reset between tests on InnoDB, so the
        // seeded "Arrived"/"Converted" rows can land at any id. The SOT
        // resolves by flag (is_arrived=1 / is_converted=1), not by id —
        // so set the flags by name and capture the resulting ids.
        AppointmentStatuses::where('name', 'Arrived')->update(['is_arrived' => 1]);
        $this->arrivedStatusId = (int) AppointmentStatuses::where('name', 'Arrived')->value('id');

        $converted = AppointmentStatuses::create([
            'name' => 'Converted',
            'active' => 1,
            'is_converted' => 1,
        ]);
        $this->convertedStatusId = (int) $converted->id;
    }

    public function test_net_cash_for_plan_subtracts_refunds_and_excludes_soft_deletes(): void
    {
        $patient = Patients::factory()->create();
        $package = Packages::factory()->create(['patient_id' => $patient->id]);

        // 5000 in
        PackageAdvances::factory()->create([
            'package_id' => $package->id,
            'patient_id' => $patient->id,
            'cash_flow' => 'in',
            'cash_amount' => 5000,
        ]);

        // 2000 refund out
        PackageAdvances::factory()->out()->create([
            'package_id' => $package->id,
            'patient_id' => $patient->id,
            'is_refund' => 1,
            'cash_amount' => 2000,
        ]);

        // 9999 row that's soft-deleted — must NOT count.
        $ghost = PackageAdvances::factory()->create([
            'package_id' => $package->id,
            'patient_id' => $patient->id,
            'cash_flow' => 'in',
            'cash_amount' => 9999,
        ]);
        $ghost->delete();

        $this->assertSame(3000.0, ConversionStateService::netCashForPlan($package->id));
    }

    public function test_net_cash_for_appointment_sums_across_multiple_plans(): void
    {
        $patient = Patients::factory()->create();
        $appointment = Appointments::factory()->create(['patient_id' => $patient->id]);

        $planA = Packages::factory()->create([
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
        ]);
        $planB = Packages::factory()->create([
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
        ]);

        // Plan A: 5000 in, 5000 refund (net 0)
        PackageAdvances::factory()->create(['package_id' => $planA->id, 'patient_id' => $patient->id, 'cash_flow' => 'in', 'cash_amount' => 5000]);
        PackageAdvances::factory()->out()->create(['package_id' => $planA->id, 'patient_id' => $patient->id, 'is_refund' => 1, 'cash_amount' => 5000]);

        // Plan B: 3000 in, no refund (net +3000)
        PackageAdvances::factory()->create(['package_id' => $planB->id, 'patient_id' => $patient->id, 'cash_flow' => 'in', 'cash_amount' => 3000]);

        // Aggregate: 0 + 3000 = 3000
        $this->assertSame(3000.0, ConversionStateService::netCashForAppointment($appointment->id));
    }

    public function test_should_revert_returns_false_when_appointment_not_converted(): void
    {
        $patient = Patients::factory()->create();
        $appointment = Appointments::factory()->create([
            'patient_id' => $patient->id,
            'appointment_status_id' => $this->arrivedStatusId,
        ]);

        // Net cash is irrelevant — the appointment isn't Converted.
        $this->assertFalse(ConversionStateService::shouldRevert($appointment->id));
    }

    public function test_should_revert_returns_true_when_converted_and_net_cash_zero(): void
    {
        $patient = Patients::factory()->create();
        $appointment = Appointments::factory()->create([
            'patient_id' => $patient->id,
            'appointment_status_id' => $this->convertedStatusId,
        ]);
        $package = Packages::factory()->create([
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
        ]);

        // Fully refunded: 5000 in, 5000 out → net 0.
        PackageAdvances::factory()->create(['package_id' => $package->id, 'patient_id' => $patient->id, 'cash_flow' => 'in', 'cash_amount' => 5000]);
        PackageAdvances::factory()->out()->create(['package_id' => $package->id, 'patient_id' => $patient->id, 'is_refund' => 1, 'cash_amount' => 5000]);

        $this->assertTrue(ConversionStateService::shouldRevert($appointment->id));
    }

    public function test_revert_if_needed_is_idempotent(): void
    {
        $patient = Patients::factory()->create();
        $appointment = Appointments::factory()->create([
            'patient_id' => $patient->id,
            'appointment_status_id' => $this->convertedStatusId,
        ]);
        $package = Packages::factory()->create([
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
        ]);
        // Net 0 → first call should revert.
        PackageAdvances::factory()->create(['package_id' => $package->id, 'patient_id' => $patient->id, 'cash_flow' => 'in', 'cash_amount' => 5000]);
        PackageAdvances::factory()->out()->create(['package_id' => $package->id, 'patient_id' => $patient->id, 'is_refund' => 1, 'cash_amount' => 5000]);

        $first = ConversionStateService::revertIfNeeded($appointment->id);
        $this->assertTrue($first, 'First call should revert.');

        // Status is now Arrived — second call must short-circuit, not
        // re-revert (or worse, re-clear flags / fire side-effects).
        $second = ConversionStateService::revertIfNeeded($appointment->id);
        $this->assertFalse($second, 'Second call must be a no-op.');
    }
}
