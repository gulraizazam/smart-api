<?php

declare(strict_types=1);

namespace Tests\Feature\Leads;

use App\Http\Resources\Lead\LeadDetailResource;
use App\Http\Resources\Lead\LeadResource;
use App\Models\Leads;
use App\Models\LeadSources;
use App\Models\LeadStatuses;
use App\Models\Locations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Legacy-data compatibility regression — same archetype as the
 * config/auth.php `password_reset_tokens` bug: clean dev data hides a
 * crash that real prod rows trigger.
 *
 * `Leads` casts `gender => App\Enums\Gender` (int-backed: Male=1,
 * Female=2). The `leads.gender` column is `tinyint NOT NULL DEFAULT 0`,
 * and production carries years of legacy rows with `gender = 0`
 * ("unspecified" — see StoreAppointmentRequest, which accepts 0/1/2, and
 * the dashboard metrics' `NULLIF(gender, 0)` rule). 0 is NOT an enum
 * case, so the moment the Eloquent cast accessor is touched on such a
 * row it throws:
 *
 *   ValueError: 0 is not a valid backing value for enum App\Enums\Gender
 *
 * The leads list endpoint (LeadsController@index → LeadResource::collection)
 * and the detail endpoint (→ new LeadResource / LeadDetailResource) both
 * 500 the first time a page includes a gender=0 lead. The factory only
 * ever produces gender ∈ {1,2}, so every existing test passed on
 * fabricated clean data — exactly the failure mode under audit.
 *
 * The fix reads the RAW attribute and degrades via Gender::tryFrom()
 * instead of the throwing cast. A legacy row must be inserted with raw
 * SQL because the model cast also rejects gender=0 on write (which is
 * precisely how these rows predate the cast in prod).
 */
class LeadResourceLegacyGenderTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    /**
     * Insert a lead row exactly as a legacy prod row exists: gender=0,
     * bypassing the model cast that would reject it on write.
     */
    private function insertLegacyGenderLead(int $gender): Leads
    {
        $accountId = (int) Auth::user()->account_id;

        $status = LeadStatuses::create([
            'name' => 'New', 'account_id' => $accountId,
            'is_default' => 1, 'active' => 1, 'sort_no' => 1,
        ]);
        $source = LeadSources::create([
            'name' => 'Walk-in', 'account_id' => $accountId,
            'sort_no' => 1, 'active' => 1,
        ]);
        $location = Locations::factory()->create(['account_id' => $accountId]);

        $id = DB::table('leads')->insertGetId([
            'name' => 'Legacy Lead',
            'email' => 'legacy@example.test',
            'phone' => '+923001234567',
            'gender' => $gender, // raw write — model cast would throw on 0
            'lead_status_id' => $status->id,
            'lead_source_id' => $source->id,
            'msg_count' => 0,
            'active' => 1,
            'account_id' => $accountId,
            'location_id' => $location->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Leads::query()->findOrFail($id);
    }

    public function test_lead_resource_does_not_throw_on_legacy_gender_zero(): void
    {
        $lead = $this->insertLegacyGenderLead(0);

        $payload = (new LeadResource($lead))->toArray(Request::create('/'));

        // Raw value is surfaced as-is, label degrades cleanly.
        $this->assertSame(0, (int) $payload['gender']);
        $this->assertSame('Unknown', $payload['gender_label']);
    }

    public function test_lead_detail_resource_does_not_throw_on_legacy_gender_zero(): void
    {
        $lead = $this->insertLegacyGenderLead(0);

        $payload = (new LeadDetailResource($lead))->toArray(Request::create('/'));

        $this->assertSame(0, (int) $payload['gender']);
        $this->assertSame('Unknown', $payload['gender_label']);
    }

    public function test_lead_resource_still_labels_valid_gender(): void
    {
        $male = $this->insertLegacyGenderLead(1);
        $female = $this->insertLegacyGenderLead(2);

        $this->assertSame('Male', (new LeadResource($male))->toArray(Request::create('/'))['gender_label']);
        $this->assertSame('Female', (new LeadResource($female))->toArray(Request::create('/'))['gender_label']);
    }

    public function test_lead_collection_serializes_a_page_mixing_legacy_and_clean_rows(): void
    {
        // The real failure surface: a list page whose result set contains
        // even one gender=0 row must not 500 the whole response.
        $this->insertLegacyGenderLead(0);
        $this->insertLegacyGenderLead(1);
        $this->insertLegacyGenderLead(2);

        $leads = Leads::query()->orderBy('id')->get();

        $collection = LeadResource::collection($leads)->toArray(Request::create('/'));

        $this->assertCount(3, $collection);
        $this->assertSame(['Unknown', 'Male', 'Female'], array_column($collection, 'gender_label'));
    }

    public function test_model_gender_accessor_does_not_throw_on_legacy_zero(): void
    {
        // Durable §4-A: the Leads model no longer casts gender to the Gender enum
        // (which threw ValueError on the legacy 0). Direct accessors that bypass
        // the resources — AppointmentService ($lead->gender ?? 0) and ExportLead
        // ($lead->gender == 1) — must read the raw int without throwing.
        $legacy = Leads::query()->findOrFail($this->insertLegacyGenderLead(0)->id);
        $this->assertSame(0, (int) $legacy->gender);

        // Valid values still read straight through as ints.
        $male = Leads::query()->findOrFail($this->insertLegacyGenderLead(1)->id);
        $this->assertSame(1, (int) $male->gender);
    }
}
