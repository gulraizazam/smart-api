<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Appointments;
use App\Models\InvoiceDetails;
use App\Models\Invoices;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\PaymentModes;
use App\Models\Services;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Lead → Patient → Appointment → Invoice → Package → PackageAdvance.
 *
 * The ALLURA business model is a funnel: a marketing/walk-in lead becomes
 * a patient, the patient books a consultation appointment, the consultation
 * is upgraded to a treatment package, an invoice is issued, and cash is
 * collected against the package as an `in` advance. Each table has its own
 * controller and its own audit trail; what doesn't exist anywhere in the
 * codebase is a single end-to-end test that asserts the join keys all line
 * up correctly.
 *
 * This is the integration test for that funnel. It does NOT exercise any
 * one controller in depth — the unit/feature tests do that. It DOES assert
 * that the join keys propagate end-to-end:
 *
 *   leads.id          = appointments.lead_id
 *   leads.patient_id  = appointments.patient_id          (after conversion)
 *   appointments.id   = invoices.appointment_id
 *   invoices.id       = invoice_details.invoice_id
 *   packages.id       = invoice_details.package_id
 *   packages.id       = package_advances.package_id
 *   appointments.id   = package_advances.appointment_id
 *
 * If any one of these breaks (e.g. a model rename or a bad migration that
 * dropped a foreign key), every report that joins across the funnel goes
 * silently wrong. This test is the canary for that.
 */
class LeadToInvoiceFlowTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_lead_through_to_first_advance_propagates_all_join_keys(): void
    {
        // Stage 1: Lead arrives via walk-in or marketing campaign.
        $lead = Leads::factory()->create([
            'name' => 'Funnel Test Lead',
            'phone' => '+923001234567',
        ]);
        $this->assertDatabaseHas('leads', ['id' => $lead->id]);

        // Stage 2: Lead becomes a patient. In production this is the
        // result of LeadService::convert / patient creation flow. The
        // test pins the data shape: a Patient row exists, and the lead
        // points at it via patient_id.
        $patient = Patients::factory()->create([
            'name' => 'Funnel Test Patient',
            'phone' => $lead->phone,
        ]);
        $lead->patient_id = $patient->id;
        $lead->save();

        $this->assertSame(
            $patient->id,
            $lead->fresh()->patient_id,
            'After conversion the lead must reference the patient row.'
        );

        // Stage 3: Patient books an appointment. The appointment carries
        // BOTH lead_id (so the marketing report can attribute the sale to
        // the lead source) and patient_id (so the clinical record links).
        $location = Locations::factory()->create();
        $appointment = Appointments::factory()->completed()->create([
            'lead_id' => $lead->id,
            'patient_id' => $patient->id,
            'location_id' => $location->id,
        ]);

        $this->assertSame($lead->id, $appointment->lead_id);
        $this->assertSame($patient->id, $appointment->patient_id);
        $this->assertSame(4, (int) $appointment->appointment_status_id, 'completed = status 4');

        // Stage 4: A treatment package is created for the patient.
        $package = Packages::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $location->id,
            'appointment_id' => $appointment->id,
            'total_price' => 12000,
        ]);

        $this->assertSame($patient->id, $package->patient_id);
        $this->assertSame($appointment->id, $package->appointment_id);

        // Stage 5: The completed appointment generates an invoice. The
        // invoice references the appointment for traceability and the
        // patient for billing.
        $invoice = Invoices::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $location->id,
            'appointment_id' => $appointment->id,
            'invoice_status_id' => 1,
            'total_price' => 12000,
        ]);

        // The invoice has detail rows linking to the package — this is
        // how reports walk from invoice to consumed services.
        $detail = InvoiceDetails::factory()->create([
            'invoice_id' => $invoice->id,
            'package_id' => $package->id,
        ]);

        $this->assertSame($invoice->id, $detail->invoice_id);
        $this->assertSame($package->id, $detail->package_id);

        // Stage 6: Cash is collected against the package as an `in`
        // advance. This is the row CashFlow reads to update the till.
        $cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();
        $advance = PackageAdvances::factory()->create([
            'package_id' => $package->id,
            'patient_id' => $patient->id,
            'location_id' => $location->id,
            'payment_mode_id' => $cash->id,
            'cash_flow' => 'in',
            'cash_amount' => 5000,
            'invoice_id' => $invoice->id,
            'appointment_id' => $appointment->id,
            'is_cancel' => '0',
            'is_setteled' => '0',
            'is_refund' => '0',
            'is_tax' => '0',
        ]);

        $this->assertSame($package->id, $advance->package_id);
        $this->assertSame($invoice->id, $advance->invoice_id);
        $this->assertSame($appointment->id, $advance->appointment_id);

        // End-to-end JOIN check: starting from the lead, can we walk all
        // the way to the cash advance via the FK chain? If any link is
        // broken (FK renamed, schema migration dropped a column, observer
        // detached the wrong key), this query returns 0 rows.
        $row = DB::table('leads as l')
            ->join('appointments as a', 'a.lead_id', '=', 'l.id')
            ->join('invoices as i', 'i.appointment_id', '=', 'a.id')
            ->join('invoice_details as idt', 'idt.invoice_id', '=', 'i.id')
            ->join('packages as p', 'p.id', '=', 'idt.package_id')
            ->join('package_advances as pa', 'pa.package_id', '=', 'p.id')
            ->where('l.id', $lead->id)
            ->select('l.id as lead_id', 'pa.id as advance_id', 'pa.cash_amount')
            ->first();

        $this->assertNotNull(
            $row,
            'The lead → advance JOIN chain returned 0 rows. One of the foreign keys is broken.'
        );
        $this->assertSame((int) $lead->id, (int) $row->lead_id);
        $this->assertSame((int) $advance->id, (int) $row->advance_id);
        $this->assertSame(5000.0, (float) $row->cash_amount);
    }

    public function test_orphaned_lead_does_not_appear_in_the_funnel_join(): void
    {
        // A lead with no patient/appointment must NOT appear in the
        // invoice JOIN. Pin the negative case: a marketing report that
        // counts "leads converted" must not over-count by joining a
        // lead with no appointment row.
        $orphanLead = Leads::factory()->create();

        $count = DB::table('leads as l')
            ->join('appointments as a', 'a.lead_id', '=', 'l.id')
            ->where('l.id', $orphanLead->id)
            ->count();

        $this->assertSame(
            0,
            $count,
            'A lead with no appointment must not appear in the funnel join.'
        );
    }

    public function test_two_advances_against_the_same_package_both_appear_in_the_join(): void
    {
        // The funnel join must aggregate across multiple advances. Pin
        // it: build a single package with two advances and assert the
        // join returns both rows. Without this, a finance report could
        // silently sum just the first advance.
        $location = Locations::factory()->create();
        $patient = Patients::factory()->create();
        $lead = Leads::factory()->create(['patient_id' => $patient->id]);
        $appointment = Appointments::factory()->completed()->create([
            'lead_id' => $lead->id,
            'patient_id' => $patient->id,
            'location_id' => $location->id,
        ]);
        $package = Packages::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $location->id,
            'total_price' => 10000,
        ]);
        $invoice = Invoices::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $location->id,
            'appointment_id' => $appointment->id,
            'invoice_status_id' => 1,
            'total_price' => 10000,
        ]);
        InvoiceDetails::factory()->create([
            'invoice_id' => $invoice->id,
            'package_id' => $package->id,
        ]);

        $cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();
        foreach ([2000, 3000] as $amount) {
            PackageAdvances::factory()->create([
                'package_id' => $package->id,
                'patient_id' => $patient->id,
                'location_id' => $location->id,
                'payment_mode_id' => $cash->id,
                'cash_flow' => 'in',
                'cash_amount' => $amount,
                'invoice_id' => $invoice->id,
                'appointment_id' => $appointment->id,
                'is_cancel' => '0',
                'is_setteled' => '0',
                'is_refund' => '0',
                'is_tax' => '0',
            ]);
        }

        $sum = DB::table('leads as l')
            ->join('appointments as a', 'a.lead_id', '=', 'l.id')
            ->join('package_advances as pa', 'pa.appointment_id', '=', 'a.id')
            ->where('l.id', $lead->id)
            ->where('pa.cash_flow', 'in')
            ->sum('pa.cash_amount');

        $this->assertSame(
            5000.0,
            (float) $sum,
            'Both 2000 and 3000 in advances must aggregate in the join — got '
            . $sum . ' instead.'
        );
    }
}
