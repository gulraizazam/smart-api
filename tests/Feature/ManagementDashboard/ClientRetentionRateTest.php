<?php

declare(strict_types=1);

namespace Tests\Feature\ManagementDashboard;

use App\Models\Appointments;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\User;
use App\Services\Dashboard\Metrics\NewReturningMetric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Characterization tests for the "Client Retention Rate" metric — the
 * figure on the management dashboard's Client Retention Rate panel,
 * produced by NewReturningMetric as `follow_up_rate` / `follow_up_treated`
 * / `follow_up_returned` (see NewReturningMetric::followUpSplit).
 *
 * The rule, in one sentence: of the patients who had an ARRIVED TREATMENT
 * at a branch 30-60 days ago (the "matured cohort"), what percentage
 * returned for ANOTHER arrived treatment at the SAME branch, scheduled
 * MORE THAN 7 days after the first, up to today.
 *
 *   follow_up_treated  = cohort  (distinct patients treated 30-60 days ago)
 *   follow_up_returned = of those, how many came back (per the rule above)
 *   follow_up_rate     = returned / treated * 100
 *
 * Each test pins exactly ONE rule so a regression points at the broken
 * invariant. Counts and rate are asserted exactly. Deterministic map —
 * if you change... / run...:
 *   - cohort window (30-60d)        -> test_basic_*, test_treatment_outside_window_*
 *   - the 7-day rebooking gap       -> test_seven_day_gap_*
 *   - same-branch requirement       -> test_return_at_different_branch_*
 *   - treatment-only (a2)           -> test_consultation_return_*
 *   - arrived-only (a2)             -> test_non_arrived_return_*
 *   - treatment-only (cohort a1)    -> test_consultation_in_window_*
 *   - distinct-patient counting     -> test_patient_with_multiple_treatments_*
 *   - the whole formula end-to-end  -> test_comprehensive_*
 *
 * Integer values the metric hardcodes (production mapping):
 *   appointment_type_id   2 = Treatment, 1 = Consultancy
 *   appointment_status_id 2 = Arrived,   5 = Cancelled
 */
class ClientRetentionRateTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const ACCOUNT_ID = 1;
    private const TYPE_TREATMENT = 2;
    private const TYPE_CONSULTATION = 1;
    private const STATUS_ARRIVED = 2;
    private const STATUS_CANCELLED = 5;

    private NewReturningMetric $metric;
    private int $branchA;
    private int $branchB;
    private int $doctorId;
    private int $leadId;
    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeds account/region/city/lookups AND flushes the cache, so each
        // test starts clean — important because compute() memoises its
        // result per scope+range via the (in-process) array cache.
        $this->seedFinancialFixtures();

        $this->serviceId = (int) DB::table('services')->value('id');
        $this->doctorId = User::factory()->doctor()->create()->id;
        $this->leadId = Leads::factory()->create()->id;
        $this->branchA = Locations::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'name' => 'Retention Branch A',
        ])->id;
        $this->branchB = Locations::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'name' => 'Retention Branch B',
        ])->id;

        $this->metric = $this->app->make(NewReturningMetric::class);
    }

    /* ---------------------------------------------------------------- */
    /* Rule 1: basic cohort + return → rate is returned / treated.       */
    /* ---------------------------------------------------------------- */
    public function test_basic_cohort_and_return_rate(): void
    {
        $retained = $this->newPatient();
        $this->appt($retained, $this->branchA, 45);     // treated in window
        $this->appt($retained, $this->branchA, 20);     // returned 25 days later

        $notRetained = $this->newPatient();
        $this->appt($notRetained, $this->branchA, 45);   // treated, never returned

        $row = $this->retentionFor($this->branchA);

        $this->assertSame(2, $row['follow_up_treated'], 'cohort = both treated patients');
        $this->assertSame(1, $row['follow_up_returned'], 'only one came back');
        $this->assertEqualsWithDelta(50.0, $row['follow_up_rate'], 0.05);
    }

    /* Rule 2: a return within 7 days is NOT a rebooking (same-package noise). */
    public function test_seven_day_gap_excludes_near_term_return(): void
    {
        $p = $this->newPatient();
        $this->appt($p, $this->branchA, 45);   // a1
        $this->appt($p, $this->branchA, 40);   // only 5 days later → excluded

        $row = $this->retentionFor($this->branchA);

        $this->assertSame(1, $row['follow_up_treated']);
        $this->assertSame(0, $row['follow_up_returned'], '5-day gap is not a return');
        $this->assertEqualsWithDelta(0.0, $row['follow_up_rate'], 0.05);
    }

    /* Rule 3: returning at a DIFFERENT branch does not count for this branch. */
    public function test_return_at_different_branch_not_counted(): void
    {
        $p = $this->newPatient();
        $this->appt($p, $this->branchA, 45);   // treated at A
        $this->appt($p, $this->branchB, 20);   // returned at B, not A

        $row = $this->retentionFor($this->branchA);

        $this->assertSame(1, $row['follow_up_treated']);
        $this->assertSame(0, $row['follow_up_returned'], 'return must be at the same branch');
    }

    /* Rule 4: the return visit must be a TREATMENT — a consultation doesn't count. */
    public function test_consultation_return_not_counted(): void
    {
        $p = $this->newPatient();
        $this->appt($p, $this->branchA, 45);
        $this->appt($p, $this->branchA, 20, self::TYPE_CONSULTATION, self::STATUS_ARRIVED);

        $row = $this->retentionFor($this->branchA);

        $this->assertSame(1, $row['follow_up_treated']);
        $this->assertSame(0, $row['follow_up_returned'], 'a consultation return is not retention');
    }

    /* Rule 5: the return visit must be ARRIVED — a cancelled/no-show doesn't count. */
    public function test_non_arrived_return_not_counted(): void
    {
        $p = $this->newPatient();
        $this->appt($p, $this->branchA, 45);
        $this->appt($p, $this->branchA, 20, self::TYPE_TREATMENT, self::STATUS_CANCELLED);

        $row = $this->retentionFor($this->branchA);

        $this->assertSame(1, $row['follow_up_treated']);
        $this->assertSame(0, $row['follow_up_returned'], 'a cancelled return is not retention');
    }

    /* Rule 6: a treatment OUTSIDE the 30-60 day window is not in the cohort. */
    public function test_treatment_outside_window_not_in_cohort(): void
    {
        $p = $this->newPatient();
        $this->appt($p, $this->branchA, 15);   // only 15 days ago → too recent to be matured

        $row = $this->retentionFor($this->branchA);

        $this->assertSame(0, $row['follow_up_treated'], 'recent treatment is not yet in the matured cohort');
        $this->assertNull($row['follow_up_rate'], 'no cohort → rate is null, not 0');
    }

    /* Rule 7: a CONSULTATION in the window is not in the (treatment-only) cohort. */
    public function test_consultation_in_window_not_in_cohort(): void
    {
        $p = $this->newPatient();
        $this->appt($p, $this->branchA, 45, self::TYPE_CONSULTATION, self::STATUS_ARRIVED);

        $row = $this->retentionFor($this->branchA);

        $this->assertSame(0, $row['follow_up_treated'], 'cohort counts treatments only, not consultations');
    }

    /* Rule 8: a patient with several treatments in the window is counted ONCE. */
    public function test_patient_with_multiple_treatments_counted_once(): void
    {
        $p = $this->newPatient();
        $this->appt($p, $this->branchA, 50);   // treatment 1 in window
        $this->appt($p, $this->branchA, 40);   // treatment 2 in window (also a valid 10-day-later return)
        $this->appt($p, $this->branchA, 15);   // later return

        $row = $this->retentionFor($this->branchA);

        $this->assertSame(1, $row['follow_up_treated'], 'one patient, counted once despite 2 in-window treatments');
        $this->assertSame(1, $row['follow_up_returned']);
        $this->assertEqualsWithDelta(100.0, $row['follow_up_rate'], 0.05);
    }

    /* Rule 9: all rules together — the exact end-to-end formula. */
    public function test_comprehensive_mixed_scenario(): void
    {
        // Retained (came back >7d later, same branch, arrived treatment)
        $p1 = $this->newPatient();
        $this->appt($p1, $this->branchA, 45);
        $this->appt($p1, $this->branchA, 20);

        // Treated, never returned
        $p2 = $this->newPatient();
        $this->appt($p2, $this->branchA, 45);

        // Returned within 7 days → excluded
        $p3 = $this->newPatient();
        $this->appt($p3, $this->branchA, 45);
        $this->appt($p3, $this->branchA, 40);

        // Returned at a different branch → excluded
        $p4 = $this->newPatient();
        $this->appt($p4, $this->branchA, 45);
        $this->appt($p4, $this->branchB, 20);

        // Return was a consultation → excluded
        $p5 = $this->newPatient();
        $this->appt($p5, $this->branchA, 45);
        $this->appt($p5, $this->branchA, 20, self::TYPE_CONSULTATION, self::STATUS_ARRIVED);

        // Return was cancelled → excluded
        $p6 = $this->newPatient();
        $this->appt($p6, $this->branchA, 45);
        $this->appt($p6, $this->branchA, 20, self::TYPE_TREATMENT, self::STATUS_CANCELLED);

        // Treated too recently (out of window) → not in cohort
        $p7 = $this->newPatient();
        $this->appt($p7, $this->branchA, 15);

        // Consultation in window → not in cohort
        $p8 = $this->newPatient();
        $this->appt($p8, $this->branchA, 45, self::TYPE_CONSULTATION, self::STATUS_ARRIVED);

        // Multiple in-window treatments, returned → counted once, retained
        $p9 = $this->newPatient();
        $this->appt($p9, $this->branchA, 50);
        $this->appt($p9, $this->branchA, 40);
        $this->appt($p9, $this->branchA, 15);

        $row = $this->retentionFor($this->branchA);

        // Cohort = P1,P2,P3,P4,P5,P6,P9 = 7  (P7 out of window, P8 consultation)
        // Returned = P1,P9 = 2
        $this->assertSame(7, $row['follow_up_treated']);
        $this->assertSame(2, $row['follow_up_returned']);
        $this->assertEqualsWithDelta(28.6, $row['follow_up_rate'], 0.05);
    }

    /* ---------------------------------------------------------------- */
    /* helpers                                                           */
    /* ---------------------------------------------------------------- */

    private function newPatient(): int
    {
        return Patients::factory()->create(['account_id' => self::ACCOUNT_ID])->id;
    }

    /**
     * One appointment scheduled $daysAgo before today. Defaults to an
     * arrived treatment at the given branch (the cohort / return shape).
     */
    private function appt(
        int $patientId,
        int $branchId,
        int $daysAgo,
        int $type = self::TYPE_TREATMENT,
        int $status = self::STATUS_ARRIVED,
    ): void {
        Appointments::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'patient_id' => $patientId,
            'location_id' => $branchId,
            'doctor_id' => $this->doctorId,
            'lead_id' => $this->leadId,
            'service_id' => $this->serviceId,
            'appointment_type_id' => $type,
            'appointment_status_id' => $status,
            'scheduled_date' => now()->subDays($daysAgo)->toDateString(),
        ]);
    }

    /**
     * Run the metric scoped to a single branch and return its follow-up
     * retention figures. The range is wide enough (90 days) to include the
     * cohort appointments so the branch row is emitted; the follow-up
     * window itself is fixed at 30-60 days regardless of the range.
     *
     * @return array{follow_up_treated:int, follow_up_returned:int, follow_up_rate: float|null}
     */
    private function retentionFor(int $branchId): array
    {
        $range = DateRange::fromStrings(
            now()->subDays(90)->toDateString(),
            now()->toDateString(),
        );
        $out = $this->metric->compute(MetricScope::branches(self::ACCOUNT_ID, [$branchId]), $range);

        foreach ($out['rows'] as $row) {
            if ((int) $row['branch_id'] === $branchId) {
                return [
                    'follow_up_treated' => (int) $row['follow_up_treated'],
                    'follow_up_returned' => (int) $row['follow_up_returned'],
                    'follow_up_rate' => $row['follow_up_rate'],
                ];
            }
        }

        return ['follow_up_treated' => 0, 'follow_up_returned' => 0, 'follow_up_rate' => null];
    }
}
