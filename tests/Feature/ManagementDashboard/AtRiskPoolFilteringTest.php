<?php

declare(strict_types=1);

namespace Tests\Feature\ManagementDashboard;

use App\Models\Appointments;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\User;
use App\Services\Dashboard\Metrics\AtRiskPatientsMetric;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins two fixes to the At-Risk pool (AtRiskPatientsMetric):
 *
 *   #1 A future appointment only counts as "rebooked → on track" (and so
 *      drops the patient from the pool) when it is genuinely upcoming. A
 *      CANCELLED / NO-SHOW future row must NOT suppress the patient — that
 *      patient is exactly who the front desk should chase.
 *
 *   #2 The pool is an actionable contact list, so deactivated (active=0)
 *      and soft-deleted patient records must never appear.
 *
 * Status ids follow the METRIC's production constants (not the generic
 * fixture lookup names): treatment type=2, arrived=2, no-show=3,
 * cancelled=4, booked=1.
 */
class AtRiskPoolFilteringTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const ACCOUNT_ID = 1;
    private const TYPE_TREATMENT = 2;
    private const STATUS_ARRIVED = 2;   // DoctorDashboardHelper::TREATMENT_STATUS_IDS = [2]
    private const STATUS_NO_SHOW = 3;   // AtRiskPatientsMetric::NO_SHOW_STATUS_ID
    private const STATUS_CANCELLED = 4; // AtRiskPatientsMetric::CANCELLED_STATUS_ID
    private const STATUS_BOOKED = 1;    // a genuinely-upcoming future appointment

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
        $this->branch = Locations::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'name' => 'At-Risk Branch',
        ])->id;
    }

    /* ----------------------------------------------------------------- */
    /* #1 — future-booking exclusion must ignore cancelled / no-show     */
    /* ----------------------------------------------------------------- */

    public function test_cancelled_future_appointment_does_not_exclude_patient(): void
    {
        $p = $this->cadencePatient();
        // Their only future row is a CANCELLED appointment — they did NOT rebook.
        $this->appt($p, -7, self::TYPE_TREATMENT, self::STATUS_CANCELLED);

        $ids = $this->pooledPatientIds();

        $this->assertContains($p, $ids, 'a patient whose next visit was cancelled must stay at-risk');
    }

    public function test_no_show_future_appointment_does_not_exclude_patient(): void
    {
        $p = $this->cadencePatient();
        $this->appt($p, -7, self::TYPE_TREATMENT, self::STATUS_NO_SHOW);

        $this->assertContains($p, $this->pooledPatientIds(), 'a future no-show is not a rebooking');
    }

    public function test_genuine_future_booking_still_excludes_patient(): void
    {
        $p = $this->cadencePatient();
        // A real upcoming (booked) appointment → on track → excluded.
        $this->appt($p, -7, self::TYPE_TREATMENT, self::STATUS_BOOKED);

        $this->assertNotContains($p, $this->pooledPatientIds(), 'a booked upcoming visit removes the patient');
    }

    /* ----------------------------------------------------------------- */
    /* #2 — inactive / soft-deleted patients must never appear           */
    /* ----------------------------------------------------------------- */

    public function test_inactive_patient_is_excluded_active_control_included(): void
    {
        $active = $this->cadencePatient(true);
        $inactive = $this->cadencePatient(false); // active = 0

        $ids = $this->pooledPatientIds();

        $this->assertContains($active, $ids, 'active at-risk patient is shown');
        $this->assertNotContains($inactive, $ids, 'deactivated patient must not appear in the worklist');
    }

    public function test_soft_deleted_patient_is_excluded(): void
    {
        $p = $this->cadencePatient();
        Patients::withoutGlobalScopes()->where('id', $p)->delete(); // sets deleted_at

        $this->assertNotContains($p, $this->pooledPatientIds(), 'soft-deleted patient must not appear');
    }

    /* ----------------------------------------------------------------- */
    /* Cadence break has an upper bound — dormant patients aren't flagged */
    /* ----------------------------------------------------------------- */

    public function test_cadence_break_beyond_90_days_is_not_flagged(): void
    {
        $p = (int) Patients::factory()->create(['account_id' => self::ACCOUNT_ID, 'active' => 1])->id;
        // 2 arrived treatments; last visit 100 days ago (median gap 40d) →
        // overdue vs rhythm, but past the 90-day "recently slipping" ceiling.
        $this->appt($p, 140);
        $this->appt($p, 100);

        $this->assertNotContains($p, $this->pooledPatientIds(), 'a patient last seen >90 days ago is dormant, not a cadence-break');
    }

    public function test_cadence_break_within_90_days_is_flagged(): void
    {
        // cadencePatient: visits 90/55d ago → last visit 55d, inside the cap.
        $this->assertContains($this->cadencePatient(), $this->pooledPatientIds(), 'overdue but seen within 90 days is flagged');
    }

    /* ----------------------------------------------------------------- */
    /* helpers                                                           */
    /* ----------------------------------------------------------------- */

    /**
     * A patient who lands in the pool via the CADENCE-break signal: two
     * arrived treatments 90 and 55 days ago (median gap 35d), last visit
     * 55 days out → 55 > max(30, 1.5×35=52.5) fires cadence; 2 visits so
     * it isn't dropped as single-visit-only. No packages needed.
     */
    private function cadencePatient(bool $active = true): int
    {
        $id = Patients::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'active' => $active ? 1 : 0,
        ])->id;
        $this->appt($id, 90);
        $this->appt($id, 55);

        return (int) $id;
    }

    /** One appointment $daysAgo before today (negative $daysAgo = future). */
    private function appt(
        int $patientId,
        int $daysAgo,
        int $type = self::TYPE_TREATMENT,
        int $status = self::STATUS_ARRIVED,
    ): void {
        Appointments::factory()->create([
            'account_id' => self::ACCOUNT_ID,
            'patient_id' => $patientId,
            'location_id' => $this->branch,
            'doctor_id' => $this->doctorId,
            'lead_id' => $this->leadId,
            'service_id' => $this->serviceId,
            'appointment_type_id' => $type,
            'appointment_status_id' => $status,
            'scheduled_date' => now()->subDays($daysAgo)->toDateString(),
        ]);
    }

    /** Patient ids currently in the at-risk pool for the test branch. */
    private function pooledPatientIds(): array
    {
        $metric = $this->app->make(AtRiskPatientsMetric::class);
        $overview = $metric->overview(MetricScope::branches(self::ACCOUNT_ID, [$this->branch]), 50);

        return array_map(
            static fn (array $r): int => (int) $r['patient_id'],
            $overview['top_patients_by_recoverable'],
        );
    }
}
