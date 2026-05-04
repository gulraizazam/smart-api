<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Models\Appointments;
use App\Models\AuditTrails;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\Patients;
use App\Services\PatientManagement\PatientService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Patient CRUD via PatientService — pins the contracts the audit
 * called out as load-bearing for the patient domain.
 *
 * NOTE: PatientService::create was removed in 2026-05. Patients are
 * never created directly; they appear as a side-effect of booking a
 * consultation, appointment, or treatment (AppointmentService::create
 * handles the User::create with user_type_id=3 plus the paired Lead).
 * The remaining tests pin update + delete + scope contracts:
 *
 *   - update() propagates name changes to ALL related Appointments
 *     rows. The dashboard joins appointments with patient names by
 *     copy, not by FK lookup, so a stale patient name leaks into
 *     reports until the next sync.
 *   - update() propagates name/phone/gender changes to Leads keyed
 *     on the OLD phone, so the lead funnel does not lose track of a
 *     patient who corrects their number after conversion.
 *   - delete() refuses when the patient has child Leads, Appointments,
 *     Documents, Packages, Measurements, Medical, or Invoices rows —
 *     the audit found a destructive-action shortcut that wiped a
 *     patient's history; this test pins that the guard is in place.
 *   - delete() soft-deletes (uses SoftDeletes trait) so the patient
 *     can be restored without losing the audit trail.
 *   - delete() writes a row to AuditTrails so the action is auditable
 *     for compliance review.
 *
 * The test runs with an authenticated admin so the GuardsTenantBoundary
 * trait stamps account_id correctly.
 */
class PatientCrudTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PatientService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->service = app(PatientService::class);
    }

    public function test_direct_create_method_is_gone(): void
    {
        // Patients are created only as a side-effect of booking flows
        // (AppointmentService::create). PatientService must NOT expose a
        // direct create() — re-introducing it would re-open the duplicate
        // path that the marketing imports kept tripping over.
        $this->assertFalse(
            method_exists($this->service, 'create'),
            'PatientService::create() must stay removed. If you need to '
            . 'create a patient, do it through AppointmentService when a '
            . 'consultation/appointment/treatment is booked.'
        );
        $this->assertFalse(
            method_exists($this->service, 'getCreateData'),
            'PatientService::getCreateData() must stay removed alongside create().'
        );
    }

    public function test_post_patients_endpoint_is_gone(): void
    {
        // The SPA must not have a path back to direct creation.
        // POST /api/patients used to map to PatientController::store,
        // which the route file no longer registers.
        $hasStoreRoute = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->contains(fn ($route) => $route->uri() === 'api/patients'
                && in_array('POST', $route->methods(), true));

        $this->assertFalse(
            $hasStoreRoute,
            'POST /api/patients must stay deregistered. Re-adding it brings '
            . 'back the direct-create path the team explicitly removed.'
        );
    }

    public function test_update_propagates_name_change_to_related_appointments(): void
    {
        // The audit found that appointments store the patient name as a
        // copy, so the dashboard's appointment list shows stale names
        // unless update() pushes the new name across. Pin it.
        $location = Locations::factory()->create();
        $patient = Patients::factory()->create(['name' => 'Initial Name', 'phone' => '3007777777']);
        $appointment = Appointments::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $location->id,
            'name' => 'Initial Name',
        ]);

        $this->service->update($patient->id, [
            'name' => 'Renamed Patient',
            'email' => $patient->email,
            'phone' => '3007777777',
            'gender' => 0,
        ]);

        $this->assertSame(
            'Renamed Patient',
            $appointment->fresh()->name,
            'Appointment.name must reflect the patient rename or the dashboard list goes stale.'
        );
    }

    public function test_update_propagates_phone_and_name_change_to_leads_keyed_on_old_phone(): void
    {
        // The audit added a Leads::where('phone', $oldPhone)->update(...)
        // step so a converted lead inherits the patient's corrected
        // contact details. Pin it.
        $patient = Patients::factory()->create([
            'name' => 'Lead Original',
            'phone' => '3001110000',
            'gender' => 0,
        ]);

        $lead = Leads::factory()->create([
            'patient_id' => $patient->id,
            'name' => 'Lead Original',
            'phone' => '3001110000',
            'gender' => 1,
        ]);

        $this->service->update($patient->id, [
            'name' => 'Lead Updated',
            'email' => $patient->email,
            'phone' => '3002220000',
            'gender' => 2,
            'old_phone' => '3001110000',
        ]);

        $freshLead = Leads::query()->find($lead->id);
        $this->assertSame('Lead Updated', $freshLead->name, 'Lead name must follow the patient rename.');
        $this->assertSame('3002220000', $freshLead->phone, 'Lead phone must follow the patient phone change.');
    }

    public function test_delete_soft_deletes_a_childless_patient_and_writes_an_audit_trail(): void
    {
        $patient = Patients::factory()->create();

        $beforeAudit = AuditTrails::query()->count();

        $result = $this->service->delete($patient->id);

        $this->assertTrue($result['status'], 'Childless delete must succeed.');
        $this->assertNotNull(
            Patients::withTrashed()->find($patient->id)?->deleted_at,
            'delete() must soft-delete via the SoftDeletes trait, not hard-delete.'
        );
        $this->assertNull(
            Patients::query()->find($patient->id),
            'A soft-deleted patient must be excluded from the default query.'
        );
        $this->assertGreaterThan(
            $beforeAudit,
            AuditTrails::query()->count(),
            'delete() must append at least one row to audit_trails for compliance.'
        );
    }

    public function test_delete_refuses_when_the_patient_has_child_appointments(): void
    {
        // The destructive-action guard the audit pinned: a patient with
        // any child record (Leads, Appointments, Documents, Packages,
        // Measurements, Medical, Invoices) cannot be deleted because
        // doing so would orphan history. Pin Appointments specifically
        // since it is the most common child.
        $location = Locations::factory()->create();
        $patient = Patients::factory()->create();
        Appointments::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $location->id,
        ]);

        $result = $this->service->delete($patient->id);

        $this->assertFalse($result['status'], 'Patient with child appointments must NOT delete.');
        $this->assertStringContainsStringIgnoringCase('appointments', $result['message']);
        $this->assertNull(
            Patients::withTrashed()->find($patient->id)?->deleted_at,
            'A refused delete must NOT soft-delete the patient row.'
        );
    }

    public function test_delete_refuses_when_the_patient_has_child_leads(): void
    {
        // Same guard, exercised through the Leads child path. The two
        // paths are independent OR-clauses in hasChildRecords; if one
        // is broken the other still catches.
        $patient = Patients::factory()->create();
        Leads::factory()->create([
            'patient_id' => $patient->id,
        ]);

        $result = $this->service->delete($patient->id);

        $this->assertFalse($result['status']);
        $this->assertStringContainsStringIgnoringCase('leads', $result['message']);
    }

    public function test_delete_returns_resource_not_found_for_an_unknown_id(): void
    {
        $result = $this->service->delete(999999);

        $this->assertFalse($result['status']);
        $this->assertSame('Resource not found.', $result['message']);
    }

    public function test_global_scope_filters_non_patient_users_out_of_patients_queries(): void
    {
        // The Patients model installs a global scope `patients_only`
        // that pins user_type_id = 3. Any other user_type — admin,
        // doctor, front desk — must be invisible through the Patients
        // model. The audit added this scope after a leak where a
        // doctor's row appeared in the patient list.
        $patient = Patients::factory()->create();

        // Existing admin from setUp() is user_type 1 — must NOT be
        // visible through the Patients model.
        $totalUsersInTable = \Illuminate\Support\Facades\DB::table('users')->count();
        $patientCountThroughScope = Patients::query()->count();

        $this->assertGreaterThan(
            $patientCountThroughScope,
            $totalUsersInTable,
            'The users table must have more rows than the Patients model returns — '
            . 'the global scope is the difference.'
        );
        $this->assertNotNull(
            Patients::query()->find($patient->id),
            'A user_type_id=3 row must be visible through Patients.'
        );
    }
}
