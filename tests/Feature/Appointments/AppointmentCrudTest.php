<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\Appointments;
use App\Models\AuditTrails;
use App\Models\Invoices;
use App\Models\InvoiceStatuses;
use App\Models\Locations;
use App\Models\Measurement;
use App\Models\Patients;
use App\Models\Services;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Appointments are the clinic's scheduling spine — every patient
 * visit, every invoice, every measurement, every medical history row
 * hangs off this table. The audit's concerns for this surface:
 *
 *   - The model enforces six NOT NULL FKs at the schema level
 *     (lead_id, patient_id, doctor_id, region_id, city_id,
 *     location_id). Drift from that contract is a guaranteed insert
 *     failure in production; pin it explicitly so the tests surface a
 *     future migration or factory regression first.
 *
 *   - Appointments::DeleteRecord() enforces a child-exists guard:
 *     invoices, package advances, measurements, and appointment
 *     images all block the delete. Without the guard, a staff account
 *     could soft-delete a consultation mid-invoice and orphan the
 *     financial spine. Pin the guard from the model side (unit-level)
 *     because the HTTP controller path is extremely chunky and the
 *     audit's concern is the model contract, not the view layer.
 *
 *   - AppointmentEvent writes to audit_trails on create/update/delete
 *     via the model boot() hooks. Clinical compliance requires that
 *     every appointment mutation leave a paper trail — pin that the
 *     trail appears at each lifecycle step.
 */
class AppointmentCrudTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_appointment_factory_creates_a_row_with_all_required_fks_populated(): void
    {
        $appointment = Appointments::factory()->create();

        $this->assertNotNull($appointment->id);
        // The six NOT NULL FKs the production schema enforces. If any
        // of these ever slip back to nullable the factory should still
        // be filling them, so assert the values are present.
        $this->assertNotNull($appointment->lead_id, 'lead_id is NOT NULL');
        $this->assertNotNull($appointment->patient_id, 'patient_id is NOT NULL');
        $this->assertNotNull($appointment->doctor_id, 'doctor_id is NOT NULL');
        $this->assertNotNull($appointment->region_id, 'region_id is NOT NULL');
        $this->assertNotNull($appointment->city_id, 'city_id is NOT NULL');
        $this->assertNotNull($appointment->location_id, 'location_id is NOT NULL');
    }

    public function test_appointment_create_without_location_id_fails_at_the_schema_level(): void
    {
        // Schema-level NOT NULL pin: location_id is mandatory in
        // production. If a future migration relaxes it, a regulator
        // could end up reading a record with no branch — this test
        // flags that drift immediately.
        $patient = Patients::factory()->create();

        try {
            Appointments::create([
                'name' => 'Missing Location',
                'account_id' => 1,
                'appointment_type_id' => 1,
                'patient_id' => $patient->id,
                'lead_id' => \App\Models\Leads::factory()->create()->id,
                'region_id' => 1,
                'city_id' => 1,
                'doctor_id' => \App\Models\User::factory()->doctor()->create()->id,
                'service_id' => Services::factory()->create()->id,
                // 'location_id' intentionally missing
                'appointment_status_id' => 1,
            ]);
            $this->fail('Appointment insert must reject a missing location_id.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_appointment_soft_delete_writes_deleted_at_and_preserves_the_row(): void
    {
        $appointment = Appointments::factory()->create();
        $id = $appointment->id;

        $appointment->delete();

        $this->assertNull(Appointments::query()->find($id));
        $this->assertNotNull(
            Appointments::withTrashed()->find($id),
            'Soft-deleted appointments must still be recoverable via withTrashed().'
        );
    }

    public function test_appointment_restore_rehydrates_a_soft_deleted_row(): void
    {
        $appointment = Appointments::factory()->create();
        $id = $appointment->id;

        $appointment->delete();
        Appointments::withTrashed()->find($id)->restore();

        $this->assertNotNull(
            Appointments::query()->find($id),
            'Restoring a soft-deleted appointment must return it to the default query scope.'
        );
    }

    public function test_delete_record_refuses_when_an_invoice_is_attached(): void
    {
        // Pin the child-exists guard: an appointment with a live
        // (non-cancelled) invoice must not be deletable. Deleting one
        // would orphan the financial spine row and leave the clinic
        // unable to reconcile.
        $appointment = Appointments::factory()->create();

        // Seed a minimal live invoice keyed to this appointment. The
        // isChildExists() guard only counts invoices where
        // invoice_status_id != 4 (the "Cancelled" invoice state
        // seeded by UsesFinancialFixtures).
        Invoices::factory()->create([
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'location_id' => $appointment->location_id,
            'invoice_status_id' => 1,
        ]);

        $result = Appointments::DeleteRecord($appointment->id, 1);

        $this->assertFalse(
            $result['status'],
            'DeleteRecord must refuse when a live invoice is attached to the appointment.'
        );
        $this->assertNotNull(
            Appointments::query()->find($appointment->id),
            'Refused delete must NOT have removed the appointment row.'
        );
    }

    public function test_delete_record_refuses_when_a_measurement_is_attached(): void
    {
        // Measurements are a softer child than invoices — losing them
        // would only cost the clinic its progress tracking — but the
        // guard is pinned for them too and has been the subject of
        // past regressions. Assert both the refusal and the row survival.
        $appointment = Appointments::factory()->create();

        $formId = DB::table('custom_forms')->insertGetId([
            'name' => 'Before/After Form',
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $feedbackId = (int) DB::table('custom_form_feedbacks')->insertGetId([
            'form_name' => 'Before/After Submission',
            'custom_form_id' => $formId,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Measurement::create([
            'user_id' => auth()->id(),
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'custom_form_feedback_id' => $feedbackId,
            'service_id' => $appointment->service_id,
            'priority' => 'Low priority',
            'type' => 'Before Appointment',
            'date' => now()->toDateString(),
        ]);

        $result = Appointments::DeleteRecord($appointment->id, 1);

        $this->assertFalse(
            $result['status'],
            'DeleteRecord must refuse when a measurement is attached to the appointment.'
        );
        $this->assertNotNull(Appointments::query()->find($appointment->id));
    }

    public function test_delete_record_removes_a_childless_appointment(): void
    {
        $appointment = Appointments::factory()->create();

        $result = Appointments::DeleteRecord($appointment->id, 1);

        $this->assertTrue($result['status']);
        $this->assertNull(
            Appointments::query()->find($appointment->id),
            'DeleteRecord must soft-delete a childless appointment.'
        );
    }

    public function test_delete_record_refuses_when_the_account_id_does_not_match(): void
    {
        // Tenant boundary: an appointment in account 1 must not be
        // deletable by a caller passing account_id = 999. DeleteRecord
        // scopes its `first()` lookup by (id, account_id), so a wrong
        // account yields "Appointment not found" and nothing is touched.
        $appointment = Appointments::factory()->create(['account_id' => 1]);

        $result = Appointments::DeleteRecord($appointment->id, 999);

        $this->assertFalse($result['status']);
        $this->assertNotNull(
            Appointments::query()->find($appointment->id),
            'Cross-tenant DeleteRecord must NOT soft-delete the row.'
        );
    }

    public function test_appointment_audit_trail_is_written_on_create(): void
    {
        // The AppointmentEvent::created listener writes to audit_trails
        // via the boot() hook. Pin that a creation appends a row so a
        // regulator can prove who booked the slot. audit_trails is
        // normalised (audit_trail_table_name is a FK into
        // audit_trail_tables), so total-count is the robust pin here —
        // the lookup-id resolution is covered separately in the Audit
        // trail tests.
        $countBefore = AuditTrails::query()->count();

        Appointments::factory()->create();

        $countAfter = AuditTrails::query()->count();
        $this->assertGreaterThan(
            $countBefore,
            $countAfter,
            'Appointment creation must append at least one row to audit_trails.'
        );
    }

    public function test_appointment_audit_trail_is_written_on_update(): void
    {
        $appointment = Appointments::factory()->create();
        $countBefore = AuditTrails::query()->count();

        $appointment->update(['name' => 'Updated Appointment Name']);

        $countAfter = AuditTrails::query()->count();
        $this->assertGreaterThan(
            $countBefore,
            $countAfter,
            'Appointment update must append at least one row to audit_trails.'
        );
    }

    public function test_appointment_audit_trail_is_written_on_soft_delete(): void
    {
        $appointment = Appointments::factory()->create();
        $countBefore = AuditTrails::query()->count();

        $appointment->delete();

        $countAfter = AuditTrails::query()->count();
        $this->assertGreaterThan(
            $countBefore,
            $countAfter,
            'Appointment soft-delete must append at least one row to audit_trails.'
        );
    }
}
