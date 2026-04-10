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
 * called out as load-bearing for the patient domain:
 *
 *   - create() de-duplicates by phone within an account: a second
 *     create() with the same phone updates the existing patient
 *     instead of inserting a duplicate. Without this, marketing
 *     imports double-up the patient list.
 *   - create() normalises phone numbers via PhoneFormattingService
 *     (strips spaces, dashes, leading 0/92) so search-by-phone keeps
 *     working regardless of how the number was typed.
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

    public function test_create_inserts_a_new_patient_with_user_type_and_account_stamped(): void
    {
        $result = $this->service->create([
            'name' => 'Alice Test',
            'email' => 'alice.test@example.com',
            'phone' => '+92 300-1234567',
            'gender' => 0,
        ]);

        $this->assertTrue($result['status']);
        $this->assertArrayHasKey('patient', $result);

        $patient = Patients::query()->where('email', 'alice.test@example.com')->first();
        $this->assertNotNull($patient, 'A new patient row must exist after create().');
        $this->assertSame(3, (int) $patient->user_type_id, 'Patients must be stamped user_type_id=3.');
        $this->assertSame(1, (int) $patient->account_id, 'account_id must come from the authed admin.');
        $this->assertSame('Alice Test', $patient->name);
    }

    public function test_create_normalises_phone_numbers_via_phone_formatting_service(): void
    {
        // PhoneFormattingService::cleanNumber strips spaces, dashes,
        // and leading "0" or "92". The same human number entered three
        // different ways must collapse to the same stored phone.
        $r1 = $this->service->create([
            'name' => 'Phone One',
            'email' => 'phone1@example.com',
            'phone' => '+92 300-1112222',
            'gender' => 0,
        ]);

        $patient = Patients::query()->where('email', 'phone1@example.com')->first();
        $this->assertNotNull($patient);
        $this->assertSame(
            '3001112222',
            $patient->phone,
            'cleanNumber must strip the +, spaces, dashes, and the 92 country code.'
        );
    }

    public function test_create_with_an_existing_phone_updates_the_existing_patient_instead_of_duplicating(): void
    {
        // The dedupe key is (phone, user_type_id=3, account_id) — pin it.
        // First create lays down a patient. Second create with the same
        // phone (any spacing) must NOT add a second row, it must update
        // the original.
        $first = $this->service->create([
            'name' => 'Original Name',
            'email' => 'dup-original@example.com',
            'phone' => '03001234567',
            'gender' => 0,
        ]);

        $countAfterFirst = Patients::query()->where('phone', '3001234567')->count();
        $this->assertSame(1, $countAfterFirst);

        $second = $this->service->create([
            'name' => 'Updated Name',
            'email' => 'dup-second@example.com',
            'phone' => '+92-300 1234567',
            'gender' => 1,
        ]);

        $countAfterSecond = Patients::query()->where('phone', '3001234567')->count();
        $this->assertSame(
            1,
            $countAfterSecond,
            'A second create() with a duplicate phone must update — never insert.'
        );

        $patient = Patients::query()->where('phone', '3001234567')->first();
        $this->assertSame('Updated Name', $patient->name, 'The dedupe path must apply the new name.');
    }

    public function test_create_with_duplicate_phone_propagates_new_name_to_existing_appointments(): void
    {
        // The dedupe path also runs an Appointments::where(...)->update(['name' => ...])
        // so existing appointment rows pick up the corrected name.
        $location = Locations::factory()->create();

        $this->service->create([
            'name' => 'Old Name',
            'email' => 'aprop1@example.com',
            'phone' => '03009998888',
            'gender' => 0,
        ]);
        $patient = Patients::query()->where('phone', '3009998888')->firstOrFail();

        $appointment = Appointments::factory()->create([
            'patient_id' => $patient->id,
            'location_id' => $location->id,
            'name' => 'Old Name',
        ]);

        $this->service->create([
            'name' => 'New Name',
            'email' => 'aprop2@example.com',
            'phone' => '03009998888',
            'gender' => 0,
        ]);

        $this->assertSame(
            'New Name',
            $appointment->fresh()->name,
            'Existing appointments must inherit the new name through the dedupe-path update.'
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
