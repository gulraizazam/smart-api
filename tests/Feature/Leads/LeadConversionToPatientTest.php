<?php

declare(strict_types=1);

namespace Tests\Feature\Leads;

use App\Exceptions\LeadException;
use App\Models\Leads;
use App\Models\LeadSources;
use App\Models\LeadStatuses;
use App\Models\Patients;
use App\Services\Lead\LeadService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The lead → patient conversion flow is where marketing/funnel data
 * crosses into the clinical data model. Every step here is load-bearing
 * for reporting: if the lead loses its converted_by stamp or the
 * patient_id back-link, the Conversion Report undercount is silent.
 *
 * Invariants pinned:
 *
 *   1. `getConvertedLeadStatus()` returns the LeadStatuses row where
 *      `is_converted=1` for the caller's account. Only ONE such row
 *      should exist per tenant — the seed path owns this invariant.
 *
 *   2. Moving a lead to the converted status via `updateLeadStatus()`
 *      stamps `converted_by = Auth::id()`. Without this stamp the
 *      Marketing Report cannot attribute conversions to operators.
 *
 *   3. Once a lead is converted, its status is TERMINAL — a second
 *      `updateLeadStatus()` call that tries to move it back to an
 *      open state throws `LeadException::statusChangeNotAllowed`.
 *      This is the "no un-converting" guard; otherwise an operator
 *      could rewrite conversion history to spike their KPIs.
 *
 *   4. A lead carries a `patient_id` FK — the field is stamped by the
 *      caller (the public Leads conversion controller) when the
 *      corresponding Patients row is created. These tests pin the
 *      relationship (`$lead->patient` returns the Patient) so the
 *      report JOINs stay stable.
 *
 *   5. `getConversionData()` — the data-payload behind the convert
 *      modal — returns a `lead` key and a `lead_sources` key. A
 *      refactor that dropped either would break the modal template.
 */
class LeadConversionToPatientTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private LeadService $service;

    private LeadStatuses $defaultStatus;

    private LeadStatuses $convertedStatus;

    private LeadSources $leadSource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $accountId = (int) Auth::user()->account_id;

        $this->service = app(LeadService::class);

        $this->defaultStatus = LeadStatuses::create([
            'name' => 'New',
            'account_id' => $accountId,
            'is_default' => 1,
            'active' => 1,
            'sort_no' => 1,
        ]);

        $this->convertedStatus = LeadStatuses::create([
            'name' => 'Converted',
            'account_id' => $accountId,
            'is_converted' => 1,
            'active' => 1,
            'sort_no' => 2,
        ]);

        $this->leadSource = LeadSources::create([
            'name' => 'Walk-in',
            'account_id' => $accountId,
            'sort_no' => 1,
            'active' => 1,
        ]);

        Cache::flush();
    }

    public function test_get_converted_lead_status_returns_the_tenant_row_with_is_converted_flag(): void
    {
        $status = $this->service->getConvertedLeadStatus((int) Auth::user()->account_id);

        $this->assertNotNull($status);
        $this->assertSame($this->convertedStatus->id, $status->id);
        $this->assertTrue((bool) $status->is_converted);
    }

    public function test_update_lead_status_to_converted_stamps_converted_by_field(): void
    {
        $lead = $this->service->createLead([
            'new_lead' => true,
            'name' => 'Iris',
            'phone' => '03008889999',
            'lead_source_id' => $this->leadSource->id,
            'service_id' => 1,
        ]);

        // The convert modal posts lead_status_parent_id = the
        // "Converted" status row id.
        $updated = $this->service->updateLeadStatus($lead->id, [
            'lead_status_parent_id' => $this->convertedStatus->id,
        ]);

        $this->assertSame($this->convertedStatus->id, $updated->lead_status_id);
        $this->assertSame((int) Auth::id(), (int) $updated->converted_by);
    }

    public function test_a_lead_in_converted_state_cannot_be_moved_back_to_open(): void
    {
        $lead = $this->service->createLead([
            'new_lead' => true,
            'name' => 'Jack',
            'phone' => '03009990000',
            'lead_source_id' => $this->leadSource->id,
            'service_id' => 1,
        ]);

        // First move: legal — open → converted.
        $this->service->updateLeadStatus($lead->id, [
            'lead_status_parent_id' => $this->convertedStatus->id,
        ]);

        // Second move: illegal — converted → open (refuses).
        $this->expectException(LeadException::class);
        $this->expectExceptionMessageMatches('/not allowed|Converted/i');

        $this->service->updateLeadStatus($lead->id, [
            'lead_status_parent_id' => $this->defaultStatus->id,
        ]);
    }

    public function test_lead_patient_relationship_resolves_when_patient_id_is_set(): void
    {
        $patient = Patients::factory()->create(['name' => 'Karla']);

        // Create the lead then wire up the patient_id. In production
        // this is stamped by the Patients controller at conversion
        // time; here we write it directly so the relationship test
        // doesn't depend on the full conversion controller.
        $lead = $this->service->createLead([
            'new_lead' => true,
            'name' => 'Karla',
            'phone' => '03001020304',
            'lead_source_id' => $this->leadSource->id,
            'service_id' => 1,
        ]);

        Leads::where('id', $lead->id)->update([
            'patient_id' => $patient->id,
            'lead_status_id' => $this->convertedStatus->id,
        ]);

        $reloaded = Leads::with('patient')->find($lead->id);

        $this->assertNotNull($reloaded->patient);
        $this->assertInstanceOf(Patients::class, $reloaded->patient);
        $this->assertSame($patient->id, $reloaded->patient->id);
    }

    public function test_conversion_data_payload_carries_lead_and_lead_sources_keys(): void
    {
        $lead = $this->service->createLead([
            'new_lead' => true,
            'name' => 'Leo',
            'phone' => '03001020305',
            'lead_source_id' => $this->leadSource->id,
            'service_id' => 1,
        ]);

        $payload = $this->service->getConversionData($lead->id);

        $this->assertNotNull($payload);
        $this->assertArrayHasKey('lead', $payload);
        $this->assertArrayHasKey('lead_sources', $payload);
        $this->assertArrayHasKey('services', $payload);
        $this->assertSame($lead->id, $payload['lead']->id);
    }

    public function test_conversion_data_returns_null_for_cross_tenant_lead(): void
    {
        // A lead belonging to account 999 is invisible to the admin
        // acting on account 1 — getConversionData returns null,
        // matching the intent of the account_id guard in the query.
        $leadId = \DB::table('leads')->insertGetId([
            'account_id' => 999,
            'name' => 'Foreign Lead',
            'phone' => '03005001111',
            'lead_status_id' => $this->defaultStatus->id,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = $this->service->getConversionData($leadId);

        $this->assertNull($payload);
    }
}
