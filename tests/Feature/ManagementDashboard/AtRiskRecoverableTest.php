<?php

declare(strict_types=1);

namespace Tests\Feature\ManagementDashboard;

use App\Models\Appointments;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\PackageService;
use App\Models\Patients;
use App\Models\User;
use App\Services\Dashboard\Metrics\AtRiskPatientsMetric;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the money split on the At-Risk pool:
 *
 *   - Collectable (recoverable_value) = the outstanding balance the patient
 *     still OWES on undelivered sessions — money we can bring in.
 *   - Prepaid at risk (prepaid_unused) = cash already paid for undelivered
 *     sessions — refund exposure, NOT recoverable income.
 *   - A REFUNDED package is a closed deal: it contributes ZERO to both.
 *     (Before the fix, a refund inflated "recoverable" — money we'd handed
 *     back showed as winnable.)
 */
class AtRiskRecoverableTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const ACCOUNT_ID = 1;

    private int $branch;
    private int $doctorId;
    private int $leadId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();

        $this->doctorId = User::factory()->doctor()->create()->id;
        $this->leadId = Leads::factory()->create()->id;
        $this->branch = Locations::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'name' => 'Recoverable Branch',
        ])->id;
    }

    public function test_owed_balance_shows_as_collectable(): void
    {
        $p = $this->abandonedPatient();
        // 2 sessions @1,000; 1 used, 1 unused; only 1,000 paid → owes 1,000.
        $this->package($p, 1000.0, consumed: 1, unused: 1, paidIn: 1000.0);

        $t = $this->branchTotals();

        $this->assertEqualsWithDelta(1000.0, $t['recoverable_value'], 0.01, 'unpaid balance on remaining sessions is collectable');
        $this->assertEqualsWithDelta(0.0, $t['prepaid_unused'], 0.01, 'nothing prepaid beyond what was consumed');
    }

    public function test_fully_paid_unused_session_is_prepaid_at_risk_not_collectable(): void
    {
        $p = $this->abandonedPatient();
        // 2 sessions @1,000; 1 used, 1 unused; fully paid 2,000 → 1,000 prepaid sitting idle, nothing owed.
        $this->package($p, 1000.0, consumed: 1, unused: 1, paidIn: 2000.0);

        $t = $this->branchTotals();

        $this->assertEqualsWithDelta(1000.0, $t['prepaid_unused'], 0.01, 'cash paid for an undelivered session is prepaid-at-risk');
        $this->assertEqualsWithDelta(0.0, $t['recoverable_value'], 0.01, 'nothing to collect when fully paid');
    }

    public function test_refunded_package_contributes_no_collectable_or_prepaid(): void
    {
        $p = $this->abandonedPatient();
        // Fully paid (2,000) then partially refunded (200): the deal is closing.
        $this->package($p, 1000.0, consumed: 1, unused: 1, paidIn: 2000.0, refundOut: 200.0);

        $t = $this->branchTotals();

        $this->assertEqualsWithDelta(0.0, $t['recoverable_value'], 0.01, 'a refunded package is a closed deal — nothing to recover');
        $this->assertEqualsWithDelta(0.0, $t['prepaid_unused'], 0.01, 'a refunded package carries no prepaid exposure');
    }

    /* ----------------------------------------------------------------- */
    /* helpers                                                           */
    /* ----------------------------------------------------------------- */

    /** A patient who is a pool candidate (1 arrived treatment 45d ago). */
    private function abandonedPatient(): int
    {
        $id = (int) Patients::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'active' => 1,
        ])->id;

        Appointments::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'patient_id' => $id,
            'location_id' => $this->branch,
            'doctor_id' => $this->doctorId,
            'lead_id' => $this->leadId,
            'service_id' => (int) DB::table('services')->value('id'),
            'appointment_type_id' => 2,
            'appointment_status_id' => 2, // arrived treatment
            'scheduled_date' => now()->subDays(45)->toDateString(),
        ]);

        return $id;
    }

    /** Build an abandoned package with N consumed + N unused sessions, a
     *  paid-in advance, and an optional refund-out advance. */
    private function package(
        int $patientId,
        float $sessionPrice,
        int $consumed,
        int $unused,
        float $paidIn,
        ?float $refundOut = null,
    ): void {
        $pkg = (int) Packages::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'patient_id' => $patientId,
            'location_id' => $this->branch,
            'active' => 1,
            'is_refund' => 0,
            'total_price' => $sessionPrice * ($consumed + $unused),
        ])->id;

        for ($i = 0; $i < $consumed; $i++) {
            PackageService::factory()->consumed()->create([
                'package_id' => $pkg,
                'price' => $sessionPrice,
                'tax_including_price' => $sessionPrice,
            ]);
        }
        for ($i = 0; $i < $unused; $i++) {
            PackageService::factory()->create([
                'package_id' => $pkg,
                'price' => $sessionPrice,
                'tax_including_price' => $sessionPrice,
                'is_consumed' => 0,
            ]);
        }

        PackageAdvances::factory()->create([
            'package_id' => $pkg,
            'patient_id' => $patientId,
            'account_id' => self::ACCOUNT_ID,
            'cash_flow' => 'in',
            'cash_amount' => $paidIn,
            'is_refund' => 0,
            'is_adjustment' => 0,
            'is_tax' => 0,
            'is_cancel' => 0,
        ]);

        if ($refundOut !== null) {
            PackageAdvances::factory()->out()->create([
                'package_id' => $pkg,
                'patient_id' => $patientId,
                'account_id' => self::ACCOUNT_ID,
                'cash_amount' => $refundOut,
                'is_refund' => 1,
                'is_adjustment' => 0,
                'is_tax' => 0,
                'is_cancel' => 0,
            ]);
        }
    }

    /** @return array{recoverable_value: float, prepaid_unused: float} */
    private function branchTotals(): array
    {
        $metric = $this->app->make(AtRiskPatientsMetric::class);
        $overview = $metric->overview(MetricScope::branches(self::ACCOUNT_ID, [$this->branch]), 50);
        $branch = $overview['branches'][0] ?? [];

        return [
            'recoverable_value' => (float) ($branch['recoverable_value'] ?? 0),
            'prepaid_unused' => (float) ($branch['prepaid_unused'] ?? 0),
        ];
    }
}
