<?php

declare(strict_types=1);

namespace Tests\Feature\Upselling;

use App\Models\Locations;
use App\Models\Patients;
use App\Models\User;
use App\Services\Upselling\Rollup\UpsellCollectedBuilder;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The Collected report endpoints — Booked+Collected per doctor for a month and
 * the per-line drill-down ledger — plus the permission gate. The heavy math is
 * pinned by UpsellCollectedBuilderTest / CollectedAllocatorTest; this verifies
 * the rollup-read + HTTP wiring.
 */
class UpsellCollectedApiTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private int $accountId;
    private int $consultant;
    private int $upseller;
    private int $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->accountId = (int) (auth()->user()->account_id ?? 1);
        $this->consultant = User::factory()->doctor()->create()->id;
        $this->upseller = User::factory()->doctor()->create()->id;
        $this->location = Locations::factory()->create()->id;
    }

    private function seedPlanAndRollup(string $month): void
    {
        $patient = Patients::factory()->create();
        $apptId = DB::table('appointments')->insertGetId([
            'appointment_type_id' => 1, 'doctor_id' => $this->consultant,
            'patient_id' => $patient->id, 'location_id' => $this->location,
            'account_id' => $this->accountId, 'lead_id' => 0, 'region_id' => 0, 'city_id' => 0,
            'created_at' => '2026-03-05',
        ]);
        $packageId = DB::table('packages')->insertGetId([
            'account_id' => $this->accountId, 'patient_id' => $patient->id,
            'location_id' => $this->location, 'appointment_id' => $apptId,
            'total_price' => 200, 'created_at' => '2026-03-05',
        ]);
        DB::table('package_services')->insert([
            ['package_id' => $packageId, 'sold_by' => $this->consultant, 'tax_including_price' => 100, 'service_id' => 1, 'is_consumed' => 0, 'created_at' => '2026-03-05'],
            ['package_id' => $packageId, 'sold_by' => $this->upseller, 'tax_including_price' => 100, 'service_id' => 1, 'is_consumed' => 1, 'created_at' => '2026-03-05'],
        ]);
        DB::table('package_advances')->insert([
            'account_id' => $this->accountId, 'package_id' => $packageId,
            'cash_flow' => 'in', 'cash_amount' => 200, 'created_at' => '2026-03-05',
        ]);

        app(UpsellCollectedBuilder::class)->rebuild($this->accountId, $month);
    }

    public function test_collected_endpoint_returns_booked_and_collected_for_the_month(): void
    {
        $this->seedPlanAndRollup('2026-03-01');

        $response = $this->postJson('/api/reports/upselling/collected', [
            'month' => '2026-03',
            'location_id' => [$this->location],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $rows = collect($response->json('data.rows'));
        $upsellerRow = $rows->firstWhere('doctor_id', $this->upseller);
        $this->assertNotNull($upsellerRow, 'the upseller should appear');
        $this->assertSame(100.0, (float) $upsellerRow['collected_amount']);
        // Merge consistency: gap is always booked − collected (the exact Booked
        // value depends on the existing role-filtered query, tested elsewhere).
        $this->assertArrayHasKey('booked_in_month', $upsellerRow);
        $this->assertSame(
            round((float) $upsellerRow['booked_in_month'] - 100.0, 2),
            (float) $upsellerRow['gap'],
        );

        // The consultant's own sale is excluded from upsell entirely.
        $this->assertNull($rows->firstWhere('doctor_id', $this->consultant));
    }

    public function test_ledger_endpoint_returns_per_line_detail(): void
    {
        $this->seedPlanAndRollup('2026-03-01');

        $response = $this->postJson("/api/reports/upselling/collected/{$this->upseller}/ledger", [
            'month' => '2026-03',
            'location_id' => [$this->location],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.doctor_id', $this->upseller);
        $this->assertSame(100.0, (float) $response->json('data.total_collected'));

        $rows = $response->json('data.rows');
        $this->assertCount(1, $rows);
        $this->assertSame(100.0, (float) $rows[0]['collected_in_month']);
        $this->assertSame(100.0, (float) $rows[0]['line_value']);
        $this->assertTrue((bool) $rows[0]['is_consumed']);
    }

    public function test_collected_endpoint_requires_the_upselling_report_permission(): void
    {
        // A plain user (not the super-admin) fails the upselling_report gate.
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/reports/upselling/collected', ['month' => '2026-03'])
            ->assertStatus(403);
    }
}
