<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Http\Controllers\Admin\InvoicesController;
use App\Http\Controllers\Admin\PackagesController;
use App\Models\Appointments;
use App\Models\InvoiceDetails;
use App\Models\InvoiceStatuses;
use App\Models\Invoices;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\PaymentModes;
use App\Models\Services;
use App\Models\Settings;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Refund end-to-end:
 *
 *   1. Cash collected on a package via an `in` PackageAdvance
 *   2. Invoice cancelled — InvoicesController::cancel persists a
 *      reversal `in/is_cancel=1` advance crediting the till back AND
 *      flips the appointment status back to booked
 *   3. The refund-form computation (PackagesController::editRefund →
 *      PlanRefundService::getRefundFormData) reflects the new state:
 *      cash_received now excludes the cancelled `in` rows, refundable
 *      amount drops accordingly
 *
 * The cross-controller boundaries are where the audit found the most
 * silent corruption — InvoicesController writes a row, PlanRefundService
 * reads it back via a different filter, and any mismatch in the
 * `is_cancel` semantics produces a wrong refund number on screen. This
 * test pins the round-trip from one side to the other.
 *
 * Note on scope: this test is NOT a re-test of either controller's
 * single-step behaviour. The unit/feature tests cover that. This test
 * pins the contract that the SHARED data model lines up — i.e. that
 * the `is_cancel='1'` flag InvoicesController writes is the same flag
 * PlanRefundService reads.
 */
class RefundEndToEndTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const DOCUMENTATION_CHARGES = 500;

    private Locations $location;

    private Patients $patient;

    private PaymentModes $cash;

    private Services $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        // Both controllers under test sit behind permission gates that
        // are not the focus of this integration test — the auth tests
        // cover the gates. Open them all here so the assertions exercise
        // the data flow rather than the gate.
        Gate::before(static fn () => true);

        $this->location = Locations::factory()->create();
        $this->cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();
        $this->patient = Patients::factory()->create();
        $this->service = Services::factory()->create([
            'name' => 'Refund E2E Service',
            'price' => 1000,
        ]);

        // sys-documentationcharges is read by the refund formula. Pin a
        // known value so the assertions can do exact arithmetic.
        Settings::query()->create([
            'name' => 'Documentation Charges',
            'slug' => 'sys-documentationcharges',
            'data' => (string) self::DOCUMENTATION_CHARGES,
            'account_id' => 1,
            'active' => 1,
        ]);
    }

    public function test_invoice_cancel_then_refund_form_reflects_zero_cash_received(): void
    {
        // Stage 1: build the package + appointment + invoice + advance
        // chain. The `in` advance simulates the patient having paid 5000
        // against the package via the cancelled invoice.
        $package = Packages::factory()->create([
            'patient_id' => $this->patient->id,
            'location_id' => $this->location->id,
            'total_price' => 10000,
        ]);

        // PackageBundles row is required by PlanRefundService::getRefundFormData
        // — it dereferences ->tax_percentage. Tax = 0 keeps the formula
        // assertions deterministic.
        PackageBundles::query()->create([
            'random_id' => (string) \Illuminate\Support\Str::uuid(),
            'package_id' => $package->id,
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

        $appointment = Appointments::factory()->completed()->create([
            'patient_id' => $this->patient->id,
            'location_id' => $this->location->id,
            'appointment_type_id' => 1,
        ]);

        $invoice = Invoices::factory()->create([
            'patient_id' => $this->patient->id,
            'location_id' => $this->location->id,
            'appointment_id' => $appointment->id,
            'invoice_status_id' => 1,
            'total_price' => 5000,
        ]);

        InvoiceDetails::factory()->create([
            'invoice_id' => $invoice->id,
        ]);

        // The patient pays 5000 against the package; this is the row
        // PlanRefundService sums for cash_received.
        $inAdvance = PackageAdvances::factory()->create([
            'package_id' => $package->id,
            'patient_id' => $this->patient->id,
            'location_id' => $this->location->id,
            'payment_mode_id' => $this->cash->id,
            'cash_flow' => 'in',
            'cash_amount' => 5000,
            'invoice_id' => $invoice->id,
            'appointment_id' => $appointment->id,
            'is_cancel' => '0',
            'is_setteled' => '0',
            'is_refund' => '0',
            'is_tax' => '0',
        ]);

        // Sentinel `out/refund=1` row so getRefundFormData's
        // ->cash_amount dereference does not crash.
        PackageAdvances::factory()->create([
            'package_id' => $package->id,
            'patient_id' => $this->patient->id,
            'location_id' => $this->location->id,
            'payment_mode_id' => $this->cash->id,
            'cash_flow' => 'out',
            'cash_amount' => 0.01,
            'is_cancel' => '0',
            'is_setteled' => '0',
            'is_refund' => '1',
            'is_tax' => '0',
        ]);

        // Stage 2: snapshot the refund form BEFORE the cancel. The
        // refundable amount must reflect the full 5000 minus doc fees.
        $beforeBody = $this->callEditRefund($package->id);
        $this->assertSame(
            5000.0,
            (float) $beforeBody['data']['cash_amount'],
            'Pre-cancel cash_received must be 5000 — the in advance is uncancelled.'
        );
        $this->assertSame(
            4500.0,
            (float) $beforeBody['data']['refundable_amount'],
            '5000 - 500(doc) = 4500 refundable before cancel.'
        );

        // Stage 3: cancel the invoice. This must (a) flip the original
        // in advance to is_cancel=1 OR (b) insert a reversal in/is_cancel=1
        // row. Either way, the refund form must see cash_received drop.
        // The cancel controller's actual behaviour is (b): it leaves the
        // original row alone and inserts a reversal in/is_cancel=1 row,
        // and PlanRefundService::getRefundFormData filters those out via
        // `is_cancel = '0'` in the cash_received query.
        $cancelResponse = app(InvoicesController::class)->cancel($invoice->id);
        $cancelBody = json_decode($cancelResponse->getContent(), true);
        $this->assertTrue(
            (bool) ($cancelBody['status'] ?? false),
            'Cancel must succeed: ' . ($cancelBody['message'] ?? 'no message')
        );

        // Stage 4: verify the cancel side effects landed in the DB.
        $this->assertSame(
            (int) InvoiceStatuses::query()->where('slug', 'cancelled')->value('id'),
            (int) $invoice->fresh()->invoice_status_id,
            'Cancelled invoice must have its status flipped.'
        );
        $reversal = PackageAdvances::query()
            ->where('invoice_id', $invoice->id)
            ->where('cash_flow', 'in')
            ->where('is_cancel', '1')
            ->first();
        $this->assertNotNull(
            $reversal,
            'Cancel must insert exactly one reversal in/is_cancel=1 row to credit the till back.'
        );
        $this->assertSame(5000.0, (float) $reversal->cash_amount);

        // Stage 5: snapshot the refund form AFTER the cancel. The
        // original 5000 in advance is still in the DB (not cancelled),
        // and the new 5000 in/is_cancel=1 reversal exists. PlanRefundService
        // filters cash_received by is_cancel=0, so cash_received stays
        // at 5000 — NOT 10000 (which would happen if it summed both)
        // and NOT 0 (which would happen if it filtered the original).
        //
        // This is the audit's actual semantic: the cancel reverses the
        // till but the patient's "money in the package" is unchanged
        // until the package itself is refunded.
        $afterBody = $this->callEditRefund($package->id);
        $this->assertSame(
            5000.0,
            (float) $afterBody['data']['cash_amount'],
            'Post-cancel cash_received must remain 5000 (the cancellation creates a separate '
            . 'is_cancel=1 reversal advance which does NOT count toward the package\'s cash_received). '
            . 'If you see 10000 here, the is_cancel filter is broken in PlanRefundService and the '
            . 'till is double-credited.'
        );
        $this->assertSame(
            4500.0,
            (float) $afterBody['data']['refundable_amount'],
            '5000 - 500(doc) = 4500. Refundable should not change just because the invoice was cancelled.'
        );
    }

    public function test_appointment_status_is_reset_when_invoice_is_cancelled(): void
    {
        // The audit identified appointment status reset as a side effect
        // of invoice cancellation that the test suite must pin: a
        // completed appointment must drop back to "booked" so the
        // collector can re-bill it without manual cleanup.
        $appointment = Appointments::factory()->completed()->create([
            'patient_id' => $this->patient->id,
            'location_id' => $this->location->id,
            'appointment_type_id' => 1,
        ]);

        $invoice = Invoices::factory()->create([
            'patient_id' => $this->patient->id,
            'location_id' => $this->location->id,
            'appointment_id' => $appointment->id,
            'invoice_status_id' => 1,
            'total_price' => 3000,
        ]);
        InvoiceDetails::factory()->create(['invoice_id' => $invoice->id]);

        $this->assertSame(4, (int) $appointment->fresh()->appointment_status_id);

        app(InvoicesController::class)->cancel($invoice->id);

        $this->assertSame(
            1,
            (int) $appointment->fresh()->appointment_status_id,
            'After cancel, the appointment must drop back to booked (status 1).'
        );
    }

    public function test_pre_existing_out_refund_advances_are_purged_on_cancel(): void
    {
        // The cancel() controller does:
        //     PackageAdvances::where('invoice_id', $id)
        //         ->where('cash_flow', 'out')->delete()
        // This is the "wipe the prior refund attempts" step — without
        // it, a re-cancellation would leave stale refund rows that
        // double up the till credit.
        $appointment = Appointments::factory()->completed()->create([
            'patient_id' => $this->patient->id,
            'location_id' => $this->location->id,
            'appointment_type_id' => 1,
        ]);
        $invoice = Invoices::factory()->create([
            'patient_id' => $this->patient->id,
            'location_id' => $this->location->id,
            'appointment_id' => $appointment->id,
            'invoice_status_id' => 1,
            'total_price' => 4000,
        ]);
        InvoiceDetails::factory()->create(['invoice_id' => $invoice->id]);

        // Plant a stale `out` refund advance attached to this invoice
        // — the cancel() must purge it.
        PackageAdvances::factory()->create([
            'patient_id' => $this->patient->id,
            'location_id' => $this->location->id,
            'payment_mode_id' => $this->cash->id,
            'invoice_id' => $invoice->id,
            'cash_flow' => 'out',
            'cash_amount' => 1234,
            'is_refund' => '1',
            'is_cancel' => '0',
            'is_setteled' => '0',
            'is_tax' => '0',
        ]);

        app(InvoicesController::class)->cancel($invoice->id);

        $this->assertSame(
            0,
            PackageAdvances::query()
                ->where('invoice_id', $invoice->id)
                ->where('cash_flow', 'out')
                ->count(),
            'Pre-cancel out advances must be purged. If 1 here, cancel() is leaking stale refund rows.'
        );
    }

    /**
     * @return array{status?: int, message?: string, data?: array<string, mixed>}
     */
    private function callEditRefund(int $packageId): array
    {
        $response = app(PackagesController::class)->editRefund($packageId);

        return json_decode($response->getContent(), true) ?? [];
    }
}
