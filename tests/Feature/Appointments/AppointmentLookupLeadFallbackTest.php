<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\Leads;
use App\Models\Patients;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The consultation phone lookup (POST /api/appointments/load/lead) must, when
 * no PATIENT matches the phone, fall back to the latest non-junk LEAD — so a
 * consultation attaches to the existing lead (and its source/status/history)
 * instead of minting a duplicate. An existing PATIENT still takes precedence.
 */
class AppointmentLookupLeadFallbackTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_falls_back_to_latest_lead_when_no_patient(): void
    {
        $lead = Leads::factory()->create([
            'phone' => '3110099887',
            'name' => 'Lead Person',
            'email' => 'lead@example.com',
            'gender' => 1,
            'patient_id' => null,
            'account_id' => 1,
        ]);

        $resp = $this->postJson('/api/appointments/load/lead', ['phone' => '3110099887']);

        $resp->assertOk();
        $this->assertSame(0, $resp->json('data.status'), 'A lead match is "found" (status 0), not new (1).');
        $this->assertSame($lead->id, $resp->json('data.lead_id'));
        $this->assertNull($resp->json('data.patient_id'), 'Lead has no patient yet — patient_id is null.');
        $this->assertSame('Lead Person', $resp->json('data.name'));
        $this->assertSame('lead@example.com', $resp->json('data.email'));
        $this->assertSame('1', $resp->json('data.gender'));
    }

    public function test_existing_patient_takes_precedence_over_a_lead_on_the_same_phone(): void
    {
        $patient = Patients::factory()->create(['phone' => '3220088776', 'name' => 'Pat Patient']);
        Leads::factory()->create(['phone' => '3220088776', 'name' => 'Lead Dup', 'patient_id' => null, 'account_id' => 1]);

        $resp = $this->postJson('/api/appointments/load/lead', ['phone' => '3220088776']);

        $resp->assertOk();
        $this->assertSame(0, $resp->json('data.status'));
        $this->assertSame($patient->id, $resp->json('data.patient_id'));
        $this->assertSame('Pat Patient', $resp->json('data.name'), 'Name comes from the patient, not the lead.');
    }

    public function test_unknown_phone_is_reported_as_new(): void
    {
        $resp = $this->postJson('/api/appointments/load/lead', ['phone' => '3009990000']);

        $resp->assertOk();
        $this->assertSame(1, $resp->json('data.status'), 'No patient and no lead → new person.');
        $this->assertNull($resp->json('data.lead_id'));
    }
}
