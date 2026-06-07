<?php

declare(strict_types=1);

namespace Tests\Feature\ManagementDashboard;

use App\Models\Appointments;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\User;
use App\Services\Dashboard\Metrics\AtRiskPatientsMetric;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins that the at-risk patient's "trailing 12-month spend" (which drives
 * the value tiers and the By-Spend ranking) is scoped to the BRANCH the
 * dashboard is showing — not the patient's company-wide spend.
 */
class AtRiskSpendScopeTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const ACCOUNT_ID = 1;

    private int $branchA;
    private int $branchB;
    private int $doctorId;
    private int $leadId;
    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();

        $this->serviceId = (int) DB::table('services')->value('id');
        $this->doctorId = User::factory()->doctor()->create()->id;
        $this->leadId = Leads::factory()->create()->id;
        $this->branchA = Locations::factory()->create(['account_id' => self::ACCOUNT_ID, 'name' => 'Branch A'])->id;
        $this->branchB = Locations::factory()->create(['account_id' => self::ACCOUNT_ID, 'name' => 'Branch B'])->id;
    }

    public function test_trailing_spend_counts_only_the_in_scope_branch(): void
    {
        // In Branch A's at-risk pool via cadence break (2 arrived treatments).
        $p = (int) Patients::factory()->create(['account_id' => self::ACCOUNT_ID, 'active' => 1])->id;
        $this->treatment($p, $this->branchA, 90);
        $this->treatment($p, $this->branchA, 55);

        // Spend split across both branches.
        $this->spend($p, $this->branchA, 5000.0);
        $this->spend($p, $this->branchB, 3000.0);

        $metric = $this->app->make(AtRiskPatientsMetric::class);
        $overview = $metric->overview(MetricScope::branches(self::ACCOUNT_ID, [$this->branchA]), 50);

        $row = collect($overview['top_patients_by_spend'])->firstWhere('patient_id', $p);

        $this->assertNotNull($row, 'patient should be in Branch A at-risk pool');
        $this->assertEqualsWithDelta(
            5000.0,
            (float) $row['trailing_12mo_spend'],
            0.01,
            'spend must count only Branch A — not the 3,000 spent at Branch B',
        );
    }

    /* ----------------------------------------------------------------- */
    /* helpers                                                           */
    /* ----------------------------------------------------------------- */

    private function treatment(int $patientId, int $branchId, int $daysAgo): void
    {
        Appointments::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'patient_id' => $patientId,
            'location_id' => $branchId,
            'doctor_id' => $this->doctorId,
            'lead_id' => $this->leadId,
            'service_id' => $this->serviceId,
            'appointment_type_id' => 2,
            'appointment_status_id' => 2, // arrived treatment
            'scheduled_date' => now()->subDays($daysAgo)->toDateString(),
        ]);
    }

    /** A paid-in package advance of $amount booked at $branchId. */
    private function spend(int $patientId, int $branchId, float $amount): void
    {
        $pkg = (int) Packages::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'patient_id' => $patientId,
            'location_id' => $branchId,
            'active' => 1,
            'is_refund' => 0,
            'total_price' => $amount,
        ])->id;

        PackageAdvances::factory()->create([
            'package_id' => $pkg,
            'patient_id' => $patientId,
            'account_id' => self::ACCOUNT_ID,
            'location_id' => $branchId,
            'cash_flow' => 'in',
            'cash_amount' => $amount,
            'is_refund' => 0,
            'is_adjustment' => 0,
            'is_tax' => 0,
            'is_cancel' => 0,
        ]);
    }
}
