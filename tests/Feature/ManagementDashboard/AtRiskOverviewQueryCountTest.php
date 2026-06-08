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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * N+1 guard for AtRiskPatientsMetric::overview() — the cold-cache build that
 * powers ManagementDashboardService::atRiskOverview().
 *
 * The overview iterates every branch in scope. Two lookups used to run once
 * PER branch inside buildBranchPoolBody:
 *   - refundedPackageIds()        — "which abandoned packages were refunded?"
 *   - BranchResolver::names([id]) — the branch's display name
 * On a real account (~12 branches) that was ~24 of the ~46 queries — a classic
 * per-branch N+1. The fix prefetches the refunded-package set across ALL
 * branches in one query and reuses the branch names already resolved once for
 * the whole scope, so neither lookup repeats per branch.
 *
 * Pin: the per-branch query count must stay essentially FLAT as the branch
 * count grows. Each extra branch with candidates still adds nothing for these
 * two lookups; revert the prefetch and the count climbs by ~2 per branch.
 */
class AtRiskOverviewQueryCountTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const ACCOUNT_ID = 1;
    private const TYPE_TREATMENT = 2;
    private const STATUS_ARRIVED = 2;

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
    }

    public function test_overview_does_not_run_per_branch_refunded_or_name_queries(): void
    {
        // One fully-populated branch (a cadence candidate + an abandoned-package
        // candidate) — establishes the constant query floor.
        $one = $this->branchWithCandidates();
        $oneBranchQueries = $this->countOverviewQueries([$one]);

        // Three more identical branches. The per-branch refunded-package and
        // branch-name lookups, if still N+1, would add ~2 queries EACH (×3 = 6).
        $two = $this->branchWithCandidates();
        $three = $this->branchWithCandidates();
        $four = $this->branchWithCandidates();
        $fourBranchQueries = $this->countOverviewQueries([$one, $two, $three, $four]);

        $delta = $fourBranchQueries - $oneBranchQueries;

        // The fixed code re-uses one prefetched refunded set + one names map for
        // ALL branches, so 3 extra branches add only their own bulk-slice work,
        // NOT 2 lookups apiece. Allow generous headroom (3) for any genuinely
        // bulk per-branch shaping, but reject the N+1 growth (which would be 6+).
        $this->assertLessThanOrEqual(
            3,
            $delta,
            "Overview query count grew by {$delta} for 3 extra branches "
            ."({$oneBranchQueries} → {$fourBranchQueries}) — refundedPackageIds() "
            .'or BranchResolver::names() is running per branch (N+1).'
        );
    }

    /* ----------------------------------------------------------------- */
    /* helpers                                                           */
    /* ----------------------------------------------------------------- */

    /** Count cold-cache queries for an overview over the given branches. */
    private function countOverviewQueries(array $branchIds): int
    {
        // Cold cache so we measure the real build (warm path is pure cache hits).
        Cache::flush();
        $metric = $this->app->make(AtRiskPatientsMetric::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $metric->overview(MetricScope::branches(self::ACCOUNT_ID, $branchIds), 25);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * A branch carrying both at-risk signals so the build exercises the
     * abandoned-package valuation (→ refunded-id lookup) and the branch-name
     * resolution: one cadence-break patient + one abandoned-package patient.
     */
    private function branchWithCandidates(): int
    {
        $branch = (int) Locations::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'name' => 'QC Branch '.uniqid(),
        ])->id;

        // Cadence patient: 2 arrived treatments 90 + 55 days ago.
        $cadence = (int) Patients::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'active' => 1,
        ])->id;
        $this->appt($cadence, $branch, 90);
        $this->appt($cadence, $branch, 55);

        // Abandoned-package patient: one arrived treatment 45 days ago + an
        // active package with an unused session.
        $abandoned = (int) Patients::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'active' => 1,
        ])->id;
        $this->appt($abandoned, $branch, 45);
        $this->abandonedPackage($abandoned, $branch);

        return $branch;
    }

    private function appt(int $patientId, int $branch, int $daysAgo): void
    {
        Appointments::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'patient_id' => $patientId,
            'location_id' => $branch,
            'doctor_id' => $this->doctorId,
            'lead_id' => $this->leadId,
            'service_id' => $this->serviceId,
            'appointment_type_id' => self::TYPE_TREATMENT,
            'appointment_status_id' => self::STATUS_ARRIVED,
            'scheduled_date' => now()->subDays($daysAgo)->toDateString(),
        ]);
    }

    /** Active package: 1 consumed + 1 unused session @1,000, paid 1,000. */
    private function abandonedPackage(int $patientId, int $branch): void
    {
        $pkg = (int) Packages::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'patient_id' => $patientId,
            'location_id' => $branch,
            'active' => 1,
            'is_refund' => 0,
            'total_price' => 2000.0,
        ])->id;

        PackageService::factory()->consumed()->create([
            'package_id' => $pkg,
            'price' => 1000.0,
            'tax_including_price' => 1000.0,
        ]);
        PackageService::factory()->create([
            'package_id' => $pkg,
            'price' => 1000.0,
            'tax_including_price' => 1000.0,
            'is_consumed' => 0,
        ]);

        PackageAdvances::factory()->create([
            'package_id' => $pkg,
            'patient_id' => $patientId,
            'account_id' => self::ACCOUNT_ID,
            'cash_flow' => 'in',
            'cash_amount' => 1000.0,
            'is_refund' => 0,
            'is_adjustment' => 0,
            'is_tax' => 0,
            'is_cancel' => 0,
        ]);
    }
}
