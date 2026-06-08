<?php

declare(strict_types=1);

namespace Tests\Feature\ManagementDashboard;

use App\Models\Appointments;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\User;
use App\Services\Dashboard\Metrics\NewReturningMetric;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * N+1 guard for NewReturningMetric::retentionTrend() — the monthly
 * retention-rate trend on the management dashboard.
 *
 * The trend builds one matured 30-60 day cohort PER month anchor. It used
 * to run a followUpSplit() + followUpPool() pair PER month (2 queries each),
 * so a 6-month trend issued ~12 queries and the count grew by 2 for every
 * extra month — a classic per-month N+1 on a deferred panel.
 *
 * The fix batches ALL months into a constant pair of grouped queries (one
 * cross-joins the cohort self-join against every anchor for the per-branch
 * split, one for the deduped pool), so the query count is FLAT as the
 * monthsBack window grows. Each anchor still carries its own cohort window
 * and its own asOf follow-up cutoff, so the numbers are byte-identical to
 * the per-month form (pinned separately by ClientRetentionRateTest).
 *
 * Pin: the retentionTrend query count must NOT scale with monthsBack.
 * Revert the batching and 3 → 6 months adds ~6 queries; this test then
 * goes red.
 */
class RetentionTrendQueryCountTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const ACCOUNT_ID = 1;
    private const TYPE_TREATMENT = 2;
    private const STATUS_ARRIVED = 2;

    private NewReturningMetric $metric;
    private int $branch;
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
        $this->branch = (int) Locations::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'name' => 'Retention Trend Branch',
        ])->id;

        // Spread cohort patients across several months so the batched
        // queries actually hit rows for the older anchors too (otherwise a
        // broken N+1 could hide behind empty months).
        foreach ([45, 75, 105, 135, 165, 195] as $daysAgo) {
            $p = (int) Patients::factory()->create(['account_id' => self::ACCOUNT_ID])->id;
            $this->appt($p, $daysAgo);          // cohort treatment
            $this->appt($p, $daysAgo - 25);     // returned 25 days later
        }

        $this->metric = $this->app->make(NewReturningMetric::class);
    }

    public function test_retention_trend_query_count_does_not_scale_with_months(): void
    {
        $scope = MetricScope::company(self::ACCOUNT_ID);

        // Warm one-off static lookups (treatment-status ids, branch resolution)
        // so they don't skew the first measured call.
        $this->metric->retentionTrend($scope, 1);

        $threeMonths = $this->countTrendQueries($scope, 3);
        $sixMonths = $this->countTrendQueries($scope, 6);

        $delta = $sixMonths - $threeMonths;

        // Batched: both 3- and 6-month trends issue the same constant pair of
        // grouped queries, so 3 extra months add ~0. The old per-month N+1
        // added 2 queries per month → +6 for 3 extra months. Allow tiny
        // headroom (1) for any incidental per-call variation, but reject the
        // N+1 growth.
        $this->assertLessThanOrEqual(
            1,
            $delta,
            "retentionTrend query count grew by {$delta} for 3 extra months "
            ."({$threeMonths} → {$sixMonths}) — followUpSplit()/followUpPool() "
            .'is running per month (N+1) instead of batching all anchors.'
        );
    }

    /* ----------------------------------------------------------------- */
    /* helpers                                                           */
    /* ----------------------------------------------------------------- */

    private function countTrendQueries(MetricScope $scope, int $monthsBack): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->metric->retentionTrend($scope, $monthsBack);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function appt(int $patientId, int $daysAgo): void
    {
        Appointments::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'patient_id' => $patientId,
            'location_id' => $this->branch,
            'doctor_id' => $this->doctorId,
            'lead_id' => $this->leadId,
            'service_id' => $this->serviceId,
            'appointment_type_id' => self::TYPE_TREATMENT,
            'appointment_status_id' => self::STATUS_ARRIVED,
            'scheduled_date' => now()->subDays($daysAgo)->toDateString(),
        ]);
    }
}
