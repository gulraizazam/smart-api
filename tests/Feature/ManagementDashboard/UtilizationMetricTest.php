<?php

declare(strict_types=1);

namespace Tests\Feature\ManagementDashboard;

use App\Services\Dashboard\Metrics\UtilizationMetric;
use App\Services\Dashboard\Support\DoctorResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use App\Services\DoctorDashboard\DoctorIdentifier;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Characterization tests for UtilizationMetric — each case pins one of
 * the rules the metric has been iteratively taught to honour, so a
 * regression in any single rule trips a specific test.
 *
 * Scenarios covered:
 *   1. Allocation filter — rota at a de-allocated branch (orphan) is ignored.
 *   2. Multi-branch merge — two overlapping same-time rotas collapse to one.
 *   3. True split shift — disjoint intervals are summed, no policy break.
 *   4. AM/PM parsing — "01:00 PM" parses as 13:00.
 *   5. Midnight-end — "20:00 – 00:00" counts as 4h.
 *   6. Business closure — global closure zeroes a date's availability.
 *   7. Partial PTO — subtracts its minutes from the day.
 *   8. Break policy — 60 min for 8h+, 45 for 7h, 30 for 6h, 0 below 6h, cap 60.
 *   9. Busy path — arrived counted, cancelled excluded.
 *
 * Tests run against a fresh DB seeded with minimal fixtures. Each case
 * asserts explicit minute-level numbers rather than ranges, so the
 * moment the rules drift the failure points at the broken invariant.
 */
class UtilizationMetricTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private UtilizationMetric $metric;

    private int $defaultServiceId = 0;
    private int $arrivedStatusId = 0;
    private int $cancelledStatusId = 0;

    private const ACCOUNT_ID = 1;
    private const DOCTOR_ID = 99001;
    private const BRANCH_ALLOCATED = 201;
    private const BRANCH_ORPHAN = 202;
    private const BRANCH_ALL_CENTRES = 299;
    private const RESOURCE_TYPE_DOCTOR = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();

        // Production has is_arrived flagged on the Arrived status; the
        // fixture trait seeds the status rows but not the flag. Patch so
        // the busy-minutes query can see arrived vs cancelled.
        // The fixture trait seeds appointment_statuses via Eloquent
        // updateOrCreate, which doesn't honour explicit ids — auto-
        // increment assigns whatever is next. Look up the actual ids by
        // slug, then flip the flags the metric relies on.
        $this->arrivedStatusId = (int) DB::table('appointment_statuses')->where('name', 'Arrived')->value('id');
        $this->cancelledStatusId = (int) DB::table('appointment_statuses')->where('name', 'Cancelled')->value('id');
        DB::table('appointment_statuses')->where('id', $this->arrivedStatusId)->update(['is_arrived' => 1]);
        DB::table('appointment_statuses')->where('id', $this->cancelledStatusId)->update(['is_cancelled' => 1]);

        // resources.resource_type_id FKs resource_types — seed the two
        // rows the production enum relies on (id=1 Machine, id=2 Doctor).
        foreach ([
            ['id' => 1, 'slug' => 'Machine', 'name' => 'Machine'],
            ['id' => 2, 'slug' => 'Doctor', 'name' => 'Doctor'],
        ] as $type) {
            DB::table('resource_types')->updateOrInsert(
                ['id' => $type['id']],
                $type + ['created_at' => now(), 'updated_at' => now()],
            );
        }

        // `doctor_has_locations.service_id` FKs services.id, so the seed
        // needs a real service. `seedDefaultService()` in the fixtures
        // trait creates one — grab its id for the allocation rows.
        $this->defaultServiceId = (int) DB::table('services')->value('id');

        $this->seedDoctorAndAllocations();

        // Wire DoctorIdentifier to return our test doctor without the
        // Spatie role dance — the metric calls this to get the pool.
        $this->instance(
            DoctorIdentifier::class,
            Mockery::mock(DoctorIdentifier::class, function ($m) {
                $m->shouldReceive('getAllActiveDoctorIds')->andReturn([self::DOCTOR_ID]);
            }),
        );

        // DoctorResolver holds an instance-level memoisation cache; the
        // container re-uses the same instance across tests in this class,
        // so the cache would carry stale doctor ids between tests. Drop
        // the cached binding so each test gets a fresh resolver (which
        // then picks up the fresh DoctorIdentifier mock bound above).
        $this->app->forgetInstance(DoctorResolver::class);

        $this->metric = $this->app->make(UtilizationMetric::class);
    }

    /* Rule 1: orphan rota at a de-allocated branch is filtered out. */
    public function test_orphan_rota_at_deallocated_branch_is_ignored(): void
    {
        $this->seedRotaDay(self::BRANCH_ORPHAN, '2026-03-02', '12:00', '21:00');

        $out = $this->compute('2026-03-02', '2026-03-02');

        $this->assertSame(0, $this->row($out)['available_minutes']);
    }

    /* Rule 2: multi-branch same-time rotas merge; human can't duplicate. */
    public function test_multi_branch_same_time_rotas_merge_to_union(): void
    {
        // Seed two identical rota_day rows on the same allocated branch —
        // same-time overlap should merge to one 9h block (break 60m → 8h).
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-02', '12:00', '21:00');
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-02', '12:00', '21:00');

        $out = $this->compute('2026-03-02', '2026-03-02');

        $this->assertSame(8 * 60, $this->row($out)['available_minutes']);
    }

    /* Rule 3: true split shift — disjoint blocks; no policy break. */
    public function test_split_shift_sums_both_blocks_with_no_break(): void
    {
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-02', '12:00', '17:00');
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-02', '19:30', '23:30');

        $out = $this->compute('2026-03-02', '2026-03-02');

        $this->assertSame((5 * 60) + (4 * 60), $this->row($out)['available_minutes']);
    }

    /* Rule 4: AM/PM suffix — "01:00 PM" = 13:00. */
    public function test_am_pm_strings_parse_as_pm(): void
    {
        // "01:00 PM – 09:00 PM" = 13:00–21:00 = 8h, break 60m, net 7h.
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-02', '01:00 PM', '09:00 PM');

        $out = $this->compute('2026-03-02', '2026-03-02');

        $this->assertSame(7 * 60, $this->row($out)['available_minutes']);
    }

    /* Rule 5: midnight-end — "20:00 – 00:00" is 4h, not dropped. */
    public function test_midnight_end_counts_as_end_of_day(): void
    {
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-02', '20:00', '00:00');

        $out = $this->compute('2026-03-02', '2026-03-02');

        $this->assertSame(4 * 60, $this->row($out)['available_minutes']);
    }

    /* Rule 6: global business closure zeroes the date. */
    public function test_global_business_closure_zeroes_availability(): void
    {
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-20', '12:00', '21:00');
        $this->seedGlobalClosure('Eid-ul-Fitr', '2026-03-20', '2026-03-20');

        $out = $this->compute('2026-03-20', '2026-03-20');

        $this->assertSame(0, $this->row($out)['available_minutes']);
    }

    /* Rule 7: partial PTO subtracts its own minutes. */
    public function test_partial_pto_subtracts_its_minutes(): void
    {
        // 12:00–21:00 (9h, break 60m, net 8h) minus partial PTO 19:00–21:00 (2h)
        //  → expected 8h − 2h = 6h.
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-14', '12:00', '21:00');
        $this->seedPartialPto('2026-03-14', '19:00', '21:00');

        $out = $this->compute('2026-03-14', '2026-03-14');

        $this->assertSame(6 * 60, $this->row($out)['available_minutes']);
    }

    /* Rule 8: break policy bands. Cap at 60 min even for 12h shift. */
    public function test_break_policy_bands_by_shift_length(): void
    {
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-02', '12:00', '17:00'); // 5h → 0 break
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-03', '12:00', '18:00'); // 6h → 30m
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-04', '12:00', '19:00'); // 7h → 45m
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-05', '12:00', '20:00'); // 8h → 60m
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-06', '11:00', '23:00'); // 12h → still 60m (cap)

        $out = $this->compute('2026-03-02', '2026-03-06');
        $byDate = [];
        foreach ($this->row($out)['daily'] as $cell) {
            $byDate[$cell['d']] = $cell['a'];
        }

        $this->assertSame(5 * 60, $byDate['2026-03-02']);
        $this->assertSame(5 * 60 + 30, $byDate['2026-03-03']);
        $this->assertSame(6 * 60 + 15, $byDate['2026-03-04']);
        $this->assertSame(7 * 60, $byDate['2026-03-05']);
        $this->assertSame(11 * 60, $byDate['2026-03-06']);
    }

    /* Rule 9: non-working-day pattern + exceptions. */
    public function test_non_working_day_pattern_zeroes_availability(): void
    {
        // Default Sunday pattern comes from settings.business_working_days;
        // we don't assume a seeded row, so force the "Sunday closed" state.
        DB::table('settings')->updateOrInsert(
            ['account_id' => self::ACCOUNT_ID, 'slug' => 'business_working_days'],
            [
                'name' => 'Business Working Days',
                'data' => json_encode([
                    'monday' => true, 'tuesday' => true, 'wednesday' => true,
                    'thursday' => true, 'friday' => true, 'saturday' => true,
                    'sunday' => false,
                ]),
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        // Mar 8 is a Sunday — rota seeded but weekly pattern says closed.
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-08', '12:00', '21:00');
        // Mar 15 is also a Sunday — force open via exception; rota counts.
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-15', '12:00', '21:00');
        DB::table('working_day_exceptions')->insert([
            'account_id' => self::ACCOUNT_ID,
            'exception_date' => '2026-03-15',
            'is_working' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $out = $this->compute('2026-03-08', '2026-03-15');
        $byDate = [];
        foreach ($this->row($out)['daily'] as $cell) {
            $byDate[$cell['d']] = $cell['a'];
        }

        $this->assertSame(0, $byDate['2026-03-08']); // Sunday, no exception → closed
        $this->assertSame(8 * 60, $byDate['2026-03-15']); // Sunday but forced open → 8h (9 − 60m break)
    }

    /* Historical doctor not in active roster: if a user had arrived
       appointments in the window, the metric should include them even
       when DoctorIdentifier's current-role pool excludes them. */
    public function test_historical_doctor_outside_active_pool_is_included(): void
    {
        // Create a second user who is NOT on the mocked "active doctors"
        // list (setUp only returns DOCTOR_ID), but who has rota + arrived
        // appointments at an allocated branch. Their activity should
        // still be counted.
        $historicalDoctorId = 99002;
        DB::table('users')->insertOrIgnore([
            'id' => $historicalDoctorId,
            'account_id' => self::ACCOUNT_ID,
            'name' => 'Dr Historical',
            'email' => 'dr.historical@example.test',
            'password' => bcrypt('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Allocation row + resources + rota at the primary branch.
        DB::table('doctor_has_locations')->insert([
            'user_id' => $historicalDoctorId,
            'location_id' => self::BRANCH_ALLOCATED,
            'service_id' => $this->defaultServiceId,
            'end_node' => 1,
            'is_allocated' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('resources')->insert([
            'account_id' => self::ACCOUNT_ID,
            'name' => 'Res Historical',
            'active' => 1,
            'resource_type_id' => self::RESOURCE_TYPE_DOCTOR,
            'external_id' => $historicalDoctorId,
            'location_id' => self::BRANCH_ALLOCATED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $resId = (int) DB::table('resources')
            ->where('external_id', $historicalDoctorId)
            ->where('location_id', self::BRANCH_ALLOCATED)
            ->value('id');
        $rotaId = DB::table('resource_has_rota')->insertGetId([
            'account_id' => self::ACCOUNT_ID,
            'resource_id' => $resId,
            'resource_type_id' => self::RESOURCE_TYPE_DOCTOR,
            'location_id' => self::BRANCH_ALLOCATED,
            'start' => '2026-01-01',
            'end' => '2026-12-31',
            'active' => 1,
            'is_treatment' => 1,
            'is_consultancy' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('resource_has_rota_days')->insert([
            'resource_has_rota_id' => $rotaId,
            'date' => '2026-03-02',
            'start_time' => '12:00',
            'end_time' => '21:00',
            'start_timestamp' => '2026-03-02 00:00:00',
            'end_timestamp' => '2026-03-02 23:59:59',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Arrived appointment with this historical doctor.
        $serviceId = $this->seedServiceWithDuration('00:30');
        $leadId = DB::table('leads')->where('name', 'Test Lead')->value('id')
            ?? DB::table('leads')->insertGetId([
                'account_id' => self::ACCOUNT_ID,
                'name' => 'Test Lead',
                'phone' => '0000000000',
                'gender' => 1,
                'location_id' => self::BRANCH_ALLOCATED,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        DB::table('appointments')->insert([
            'account_id' => self::ACCOUNT_ID,
            'lead_id' => $leadId,
            'patient_id' => $historicalDoctorId,
            'doctor_id' => $historicalDoctorId,
            'region_id' => 1,
            'city_id' => 1,
            'location_id' => self::BRANCH_ALLOCATED,
            'appointment_status_id' => $this->arrivedStatusId,
            'appointment_type_id' => 1,
            'service_id' => $serviceId,
            'scheduled_date' => '2026-03-02',
            'scheduled_time' => '13:00',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $out = $this->compute('2026-03-02', '2026-03-02');

        // The historical doctor should appear as their own row with the
        // 30-min arrived appointment counted.
        $historicalRow = collect($out['rows'])->firstWhere('resource_id', $historicalDoctorId);
        $this->assertNotNull(
            $historicalRow,
            'Historical doctor should be included via activity even though DoctorIdentifier excludes them',
        );
        $this->assertSame(30, $historicalRow['busy_minutes']);
        $this->assertSame(8 * 60, $historicalRow['available_minutes']); // 9h shift - 60m break
    }

    /* Activity-gated allocation rule: a doctor de-allocated from a
       branch still has their rota + appointments counted if they had
       arrived activity at that branch in the window. */
    public function test_activity_gated_allocation_keeps_historical_activity(): void
    {
        // Force the orphan branch to be `is_allocated = 0` (already set
        // that way in setUp, just confirming the starting state).
        DB::table('doctor_has_locations')
            ->where('user_id', self::DOCTOR_ID)
            ->where('location_id', self::BRANCH_ORPHAN)
            ->update(['is_allocated' => 0]);

        // Rota AND arrived appointment at the de-allocated branch —
        // both should count because of historical activity.
        $this->seedRotaDay(self::BRANCH_ORPHAN, '2026-03-02', '12:00', '21:00');
        $serviceId = $this->seedServiceWithDuration('00:30');
        $this->seedAppointmentAtBranch(
            '2026-03-02',
            '13:00',
            $serviceId,
            statusId: $this->arrivedStatusId,
            branchId: self::BRANCH_ORPHAN,
        );

        $out = $this->compute('2026-03-02', '2026-03-02');
        $row = $this->row($out);

        $this->assertSame(8 * 60, $row['available_minutes']); // 9h − 60m break
        $this->assertSame(30, $row['busy_minutes']);
    }

    /* Rule 10: arrived counted, cancelled excluded. */
    public function test_busy_counts_arrived_but_not_cancelled(): void
    {
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-02', '12:00', '21:00');
        $serviceId = $this->seedServiceWithDuration('00:30');
        $this->seedAppointment('2026-03-02', '13:00', $serviceId, statusId: $this->arrivedStatusId);
        $this->seedAppointment('2026-03-02', '14:00', $serviceId, statusId: $this->cancelledStatusId);

        $out = $this->compute('2026-03-02', '2026-03-02');
        $row = $this->row($out);

        $this->assertSame(30, $row['busy_minutes']);
        $this->assertSame(30, $row['cancelled_minutes']);
    }

    /* Rule 11: util % = busy ÷ available. 9h shift = 480 net available;
       240 busy → exactly 50%. */
    public function test_util_pct_is_busy_over_available(): void
    {
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-02', '12:00', '21:00'); // 480 net
        $this->seedAppointment('2026-03-02', '13:00', $this->seedServiceWithDuration('04:00'), $this->arrivedStatusId); // 240

        $row = $this->row($this->compute('2026-03-02', '2026-03-02'));

        $this->assertSame(480, $row['available_minutes']);
        $this->assertSame(240, $row['busy_minutes']);
        $this->assertSame(50.0, $row['util_pct']);
    }

    /* Rule 12: band thresholds. green > 60, amber 40–60 (inclusive of
       both ends), red < 40. Available fixed at 480 so busy sets the %. */
    public function test_band_above_60_is_green(): void
    {
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-02', '12:00', '21:00'); // 480
        $this->seedAppointment('2026-03-02', '13:00', $this->seedServiceWithDuration('04:50'), $this->arrivedStatusId); // 290 → 60.4%

        $row = $this->row($this->compute('2026-03-02', '2026-03-02'));

        $this->assertEqualsWithDelta(60.4, $row['util_pct'], 0.05);
        $this->assertSame('green', $row['health']);
    }

    public function test_band_exactly_60_is_amber(): void
    {
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-02', '12:00', '21:00'); // 480
        $this->seedAppointment('2026-03-02', '13:00', $this->seedServiceWithDuration('04:48'), $this->arrivedStatusId); // 288 → 60.0%

        $row = $this->row($this->compute('2026-03-02', '2026-03-02'));

        $this->assertSame(60.0, $row['util_pct']);
        $this->assertSame('amber', $row['health'], 'exactly 60% is amber — green is strictly above 60');
    }

    public function test_band_exactly_40_is_amber(): void
    {
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-02', '12:00', '21:00'); // 480
        $this->seedAppointment('2026-03-02', '13:00', $this->seedServiceWithDuration('03:12'), $this->arrivedStatusId); // 192 → 40.0%

        $row = $this->row($this->compute('2026-03-02', '2026-03-02'));

        $this->assertSame(40.0, $row['util_pct']);
        $this->assertSame('amber', $row['health'], '40% is the amber floor');
    }

    public function test_band_below_40_is_red(): void
    {
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-02', '12:00', '21:00'); // 480
        $this->seedAppointment('2026-03-02', '13:00', $this->seedServiceWithDuration('03:08'), $this->arrivedStatusId); // 188 → 39.2%

        $row = $this->row($this->compute('2026-03-02', '2026-03-02'));

        $this->assertEqualsWithDelta(39.2, $row['util_pct'], 0.05);
        $this->assertSame('red', $row['health']);
    }

    /* Rule 13: off-rota — a doctor with no roster has zero available, so
       util is NULL (not 0/red), even if they have arrived activity. */
    public function test_off_rota_yields_null_util_not_red(): void
    {
        // No rota seeded for the doctor. Give them an arrived appointment to
        // prove busy>0 still can't divide by zero-available.
        $this->seedAppointment('2026-03-02', '13:00', $this->seedServiceWithDuration('00:30'), $this->arrivedStatusId);

        $row = $this->row($this->compute('2026-03-02', '2026-03-02'));

        $this->assertSame(0, $row['available_minutes']);
        $this->assertSame(30, $row['busy_minutes']);
        $this->assertNull($row['util_pct'], 'no roster → util is null');
        $this->assertNull($row['health'], 'off-rota is null, never red');
    }

    /* Rule 14: pool average is total-busy ÷ total-available (weighted),
       NOT the mean of per-doctor percentages. Doctor A 600/600 (100%),
       doctor B 0/200 (0%): weighted = 600/800 = 75%, mean-of-% = 50%. */
    public function test_pool_average_is_weighted_not_mean_of_percentages(): void
    {
        $doctorB = 99003;
        $this->registerSecondDoctor($doctorB, 'Dr Pool B');

        // Doctor A (DOCTOR_ID): 11h shift = 600 net, 600 busy → 100%.
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-02', '11:00', '22:00');
        $this->seedAppointment('2026-03-02', '12:00', $this->seedServiceWithDuration('10:00'), $this->arrivedStatusId);

        // Doctor B: 3h20m shift = 200 net (no break under 6h), 0 busy → 0%.
        $this->seedRotaDay(self::BRANCH_ALLOCATED, '2026-03-02', '12:00', '15:20', $doctorB);

        $out = $this->compute('2026-03-02', '2026-03-02');

        $this->assertSame(100.0, $this->rowFor($out, self::DOCTOR_ID)['util_pct']);
        $this->assertSame(0.0, $this->rowFor($out, $doctorB)['util_pct']);

        // Weighted pool: 600 busy / 800 available = 75%, not the 50% mean.
        $this->assertSame(800, $out['totals']['available_minutes']);
        $this->assertSame(600, $out['totals']['busy_minutes']);
        $this->assertSame(75.0, $out['totals']['util_pct']);
    }

    /* ----------------------------------------------------------------
     * Helpers
     * --------------------------------------------------------------- */

    private function compute(string $from, string $to): array
    {
        return $this->metric->compute(
            MetricScope::company(self::ACCOUNT_ID),
            DateRange::fromStrings($from, $to),
        );
    }

    /** @param array<string, mixed> $out */
    private function row(array $out): array
    {
        $this->assertNotEmpty($out['rows'], 'expected at least one doctor row');

        return $out['rows'][0];
    }

    private function seedDoctorAndAllocations(): void
    {
        DB::table('users')->insertOrIgnore([
            'id' => self::DOCTOR_ID,
            'account_id' => self::ACCOUNT_ID,
            'name' => 'Dr Test',
            'email' => 'dr.test@example.test',
            'password' => bcrypt('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([self::BRANCH_ALLOCATED, self::BRANCH_ORPHAN] as $bid) {
            DB::table('locations')->updateOrInsert(
                ['id' => $bid],
                [
                    'account_id' => self::ACCOUNT_ID,
                    'name' => "Test Branch {$bid}",
                    'region_id' => 1,
                    'city_id' => 1,
                    'active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        DB::table('doctor_has_locations')->insert([
            [
                'user_id' => self::DOCTOR_ID,
                'location_id' => self::BRANCH_ALLOCATED,
                'service_id' => $this->defaultServiceId,
                'end_node' => 1,
                'is_allocated' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => self::DOCTOR_ID,
                'location_id' => self::BRANCH_ORPHAN,
                'service_id' => $this->defaultServiceId,
                'end_node' => 1,
                'is_allocated' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        foreach ([self::BRANCH_ALLOCATED, self::BRANCH_ORPHAN] as $bid) {
            DB::table('resources')->insert([
                'account_id' => self::ACCOUNT_ID,
                'name' => "Res {$bid}",
                'active' => 1,
                'resource_type_id' => self::RESOURCE_TYPE_DOCTOR,
                'external_id' => self::DOCTOR_ID,
                'location_id' => $bid,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedRotaDay(int $branchId, string $date, string $start, string $end, int $doctorId = self::DOCTOR_ID): void
    {
        $resourceId = (int) DB::table('resources')
            ->where('external_id', $doctorId)
            ->where('location_id', $branchId)
            ->value('id');

        $rotaId = DB::table('resource_has_rota')
            ->where('resource_id', $resourceId)
            ->where('location_id', $branchId)
            ->value('id');

        if ($rotaId === null) {
            $rotaId = DB::table('resource_has_rota')->insertGetId([
                'account_id' => self::ACCOUNT_ID,
                'resource_id' => $resourceId,
                'resource_type_id' => self::RESOURCE_TYPE_DOCTOR,
                'location_id' => $branchId,
                'start' => '2026-01-01',
                'end' => '2026-12-31',
                'active' => 1,
                'is_treatment' => 1,
                'is_consultancy' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('resource_has_rota_days')->insert([
            'resource_has_rota_id' => $rotaId,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'start_timestamp' => "{$date} 00:00:00",
            'end_timestamp' => "{$date} 23:59:59",
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedGlobalClosure(string $title, string $from, string $to): void
    {
        $cid = DB::table('business_closures')->insertGetId([
            'account_id' => self::ACCOUNT_ID,
            'title' => $title,
            'start_date' => $from,
            'end_date' => $to,
            'created_by' => self::DOCTOR_ID,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mark global by attaching an "All Centres" pseudo-location.
        DB::table('locations')->updateOrInsert(
            ['id' => self::BRANCH_ALL_CENTRES],
            [
                'account_id' => self::ACCOUNT_ID,
                'name' => 'All Centres',
                'region_id' => 1,
                'city_id' => 1,
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('business_closure_locations')->insert([
            'business_closure_id' => $cid,
            'location_id' => self::BRANCH_ALL_CENTRES,
        ]);
    }

    private function seedPartialPto(string $date, string $start, string $end): void
    {
        $resourceId = (int) DB::table('resources')
            ->where('external_id', self::DOCTOR_ID)
            ->where('location_id', self::BRANCH_ALLOCATED)
            ->value('id');

        DB::table('resource_time_offs')->insert([
            'resource_id' => $resourceId,
            'location_id' => self::BRANCH_ALLOCATED,
            'account_id' => self::ACCOUNT_ID,
            'type' => 'time_off',
            'start_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'is_full_day' => 0,
            'is_repeat' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedServiceWithDuration(string $duration): int
    {
        return (int) DB::table('services')->insertGetId([
            'account_id' => self::ACCOUNT_ID,
            'name' => "Service {$duration}",
            'duration' => $duration,
            'price' => 0,
            'tax_treatment_type_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedAppointment(string $date, string $time, int $serviceId, int $statusId, int $doctorId = self::DOCTOR_ID): void
    {
        $this->seedAppointmentAtBranch($date, $time, $serviceId, $statusId, self::BRANCH_ALLOCATED, $doctorId);
    }

    private function seedAppointmentAtBranch(string $date, string $time, int $serviceId, int $statusId, int $branchId, int $doctorId = self::DOCTOR_ID): void
    {
        // appointments.lead_id is NOT NULL with an FK to leads. Seed a
        // minimal lead once — reused across appointments in the same
        // test — keyed off the doctor's branch for determinism.
        $leadId = DB::table('leads')->where('name', 'Test Lead')->value('id');
        if ($leadId === null) {
            $leadId = DB::table('leads')->insertGetId([
                'account_id' => self::ACCOUNT_ID,
                'name' => 'Test Lead',
                'phone' => '0000000000',
                'gender' => 1,
                'location_id' => self::BRANCH_ALLOCATED,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('appointments')->insert([
            'account_id' => self::ACCOUNT_ID,
            'lead_id' => $leadId,
            'patient_id' => $doctorId,
            'doctor_id' => $doctorId,
            'region_id' => 1,
            'city_id' => 1,
            'location_id' => $branchId,
            'appointment_status_id' => $statusId,
            'appointment_type_id' => 1,
            'service_id' => $serviceId,
            'scheduled_date' => $date,
            'scheduled_time' => $time,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Register a second doctor (user + allocation + resource at the
     * allocated branch) and re-mock the active-doctor pool to include both,
     * so pool-level totals can be exercised across more than one row.
     */
    private function registerSecondDoctor(int $doctorId, string $name): void
    {
        DB::table('users')->insertOrIgnore([
            'id' => $doctorId,
            'account_id' => self::ACCOUNT_ID,
            'name' => $name,
            'email' => "user{$doctorId}@example.test",
            'password' => bcrypt('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('doctor_has_locations')->insert([
            'user_id' => $doctorId,
            'location_id' => self::BRANCH_ALLOCATED,
            'service_id' => $this->defaultServiceId,
            'end_node' => 1,
            'is_allocated' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('resources')->insert([
            'account_id' => self::ACCOUNT_ID,
            'name' => "Res {$doctorId}",
            'active' => 1,
            'resource_type_id' => self::RESOURCE_TYPE_DOCTOR,
            'external_id' => $doctorId,
            'location_id' => self::BRANCH_ALLOCATED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->instance(
            DoctorIdentifier::class,
            Mockery::mock(DoctorIdentifier::class, function ($m) use ($doctorId): void {
                $m->shouldReceive('getAllActiveDoctorIds')->andReturn([self::DOCTOR_ID, $doctorId]);
            }),
        );
        $this->app->forgetInstance(DoctorResolver::class);
        $this->metric = $this->app->make(UtilizationMetric::class);
    }

    /**
     * Find the row for a specific resource id (rows are sorted by util, so
     * positional access isn't reliable once there's more than one doctor).
     *
     * @param  array<string, mixed>  $out
     * @return array<string, mixed>
     */
    private function rowFor(array $out, int $resourceId): array
    {
        foreach ($out['rows'] as $row) {
            if ((int) $row['resource_id'] === $resourceId) {
                return $row;
            }
        }

        $this->fail("no utilization row for resource {$resourceId}");
    }
}
