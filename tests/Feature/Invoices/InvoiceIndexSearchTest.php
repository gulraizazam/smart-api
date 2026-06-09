<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Models\Invoices;
use App\Models\Locations;
use App\Models\Patients;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * GET /api/invoices?q=… — the unified search box the SPA shares with the
 * Plans / Treatments / Consultations lists. It must:
 *   - route a phone number through PatientSearchService and return only the
 *     matching patient's invoices (not silently return everything)
 *   - route a `C-<id>` client code to that patient's invoices
 *   - treat a plain number as ambiguous and match the invoice number (the
 *     id, which InvoiceResource zero-pads via %05d) OR that patient's id,
 *     mirroring the Plans datatable's short_id branch
 *
 * Before `q` was wired, the endpoint only knew the invoice-number-only
 * `search` param, so phone / name / client-code searches were dropped.
 */
class InvoiceIndexSearchTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    /** Link the admin to the location so ACL::getUserCentres lets the rows through. */
    private function scopedLocation(int $userId): Locations
    {
        $location = Locations::factory()->create();
        DB::table('user_has_locations')->updateOrInsert(
            ['user_id' => $userId, 'location_id' => $location->id],
            ['region_id' => 1],
        );

        return $location;
    }

    public function test_q_by_phone_returns_only_that_patients_invoice(): void
    {
        $admin = $this->actingAsAdmin();
        $location = $this->scopedLocation($admin->id);

        $wanted = Patients::factory()->create([
            'phone' => '03077168463',
            'account_id' => $admin->account_id,
        ]);
        $other = Patients::factory()->create([
            'phone' => '03001112222',
            'account_id' => $admin->account_id,
        ]);

        $wantedInvoice = Invoices::factory()->create([
            'patient_id' => $wanted->id,
            'location_id' => $location->id,
        ]);
        Invoices::factory()->create([
            'patient_id' => $other->id,
            'location_id' => $location->id,
        ]);

        // Cleaned form (no leading 0) — the form receptionists most often type.
        $res = $this->getJson('/api/invoices?q=3077168463');

        $res->assertOk();
        $this->assertSame(
            [$wantedInvoice->id],
            collect($res->json('data.items'))->pluck('id')->all(),
            'q by phone must return only the matching patient\'s invoice.',
        );
    }

    public function test_q_by_client_code_returns_only_that_patients_invoice(): void
    {
        $admin = $this->actingAsAdmin();
        $location = $this->scopedLocation($admin->id);

        $wanted = Patients::factory()->create(['account_id' => $admin->account_id]);
        $other = Patients::factory()->create(['account_id' => $admin->account_id]);

        $wantedInvoice = Invoices::factory()->create([
            'patient_id' => $wanted->id,
            'location_id' => $location->id,
        ]);
        Invoices::factory()->create([
            'patient_id' => $other->id,
            'location_id' => $location->id,
        ]);

        $res = $this->getJson('/api/invoices?q=C-' . $wanted->id);

        $res->assertOk();
        $this->assertSame(
            [$wantedInvoice->id],
            collect($res->json('data.items'))->pluck('id')->all(),
            'q by C-<id> client code must return only that patient\'s invoice.',
        );
    }

    public function test_q_plain_number_matches_invoice_number_or_patient_id(): void
    {
        $admin = $this->actingAsAdmin();
        $location = $this->scopedLocation($admin->id);

        $wantedPatient = Patients::factory()->create(['account_id' => $admin->account_id]);
        $decoyPatient = Patients::factory()->create(['account_id' => $admin->account_id]);

        // Patient match — the wanted patient's own invoice (id kept far from
        // the patient id so it can only match via patient_id).
        $byPatient = Invoices::factory()->create([
            'id' => 900001,
            'patient_id' => $wantedPatient->id,
            'location_id' => $location->id,
        ]);
        // Invoice-number match — an invoice whose *id* equals the wanted
        // patient id but belongs to someone else (only matches via the id).
        $byNumber = Invoices::factory()->create([
            'id' => $wantedPatient->id,
            'patient_id' => $decoyPatient->id,
            'location_id' => $location->id,
        ]);
        // Decoy — matches neither the id nor the patient.
        Invoices::factory()->create([
            'id' => 900002,
            'patient_id' => $decoyPatient->id,
            'location_id' => $location->id,
        ]);

        $res = $this->getJson('/api/invoices?q=' . $wantedPatient->id);

        $res->assertOk();
        $this->assertSame(
            collect([$byPatient->id, $byNumber->id])->sort()->values()->all(),
            collect($res->json('data.items'))->pluck('id')->sort()->values()->all(),
            'A plain number must match the invoice number OR the patient id.',
        );
    }
}
