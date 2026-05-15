<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\OperatingDays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * OperatingDays is the canonical "was the clinic operating on date X at
 * branch Y?" calculator. It composes three independently-edited inputs:
 * weekly pattern, per-date exceptions, and date-range business closures.
 *
 * These pins were written when InvoiceGenerationService stopped using a
 * Sundays-only heuristic for its working-days denominator and moved to
 * this helper. Keep the scenarios in lockstep with the docblock at the
 * top of the class.
 */
class OperatingDaysTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const ACCOUNT_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_default_pattern_excludes_sundays(): void
    {
        // 2026-05-04 (Mon) .. 2026-05-10 (Sun) — six Mon-Sat days, one Sun.
        $dates = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-04'),
            CarbonImmutable::parse('2026-05-10'),
        );

        $this->assertCount(6, $dates);
        $this->assertNotContains('2026-05-10', $dates, 'Sunday default-off must be excluded.');
        $this->assertSame(
            ['2026-05-04', '2026-05-05', '2026-05-06', '2026-05-07', '2026-05-08', '2026-05-09'],
            $dates,
        );
    }

    public function test_custom_weekly_pattern_from_settings_is_respected(): void
    {
        // Half the world keeps Friday off, Sunday on. Pin that the helper
        // reads the JSON shape `business_working_days` writes.
        DB::table('settings')->insert([
            'account_id' => self::ACCOUNT_ID,
            'slug' => 'business_working_days',
            'data' => json_encode([
                'monday' => true, 'tuesday' => true, 'wednesday' => true,
                'thursday' => true, 'friday' => false, 'saturday' => true, 'sunday' => true,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dates = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-04'),
            CarbonImmutable::parse('2026-05-10'),
        );

        $this->assertNotContains('2026-05-08', $dates, 'Friday must be excluded under the custom pattern.');
        $this->assertContains('2026-05-10', $dates, 'Sunday must be included under the custom pattern.');
    }

    public function test_forced_closed_exception_subtracts_a_default_open_day(): void
    {
        // Wed 2026-05-13 marked closed (e.g. staff training).
        DB::table('working_day_exceptions')->insert([
            'account_id' => self::ACCOUNT_ID,
            'exception_date' => '2026-05-13',
            'is_working' => 0,
            'title' => 'Staff training',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dates = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-11'),
            CarbonImmutable::parse('2026-05-16'),
        );

        $this->assertNotContains('2026-05-13', $dates);
        $this->assertCount(5, $dates, 'Mon-Sat minus the trained Wednesday → 5 operating days.');
    }

    public function test_forced_open_exception_adds_back_a_default_closed_day(): void
    {
        // Sun 2026-05-10 explicitly opened (e.g. Eid sale).
        DB::table('working_day_exceptions')->insert([
            'account_id' => self::ACCOUNT_ID,
            'exception_date' => '2026-05-10',
            'is_working' => 1,
            'title' => 'Special Sunday opening',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dates = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-04'),
            CarbonImmutable::parse('2026-05-10'),
        );

        $this->assertContains('2026-05-10', $dates);
        $this->assertCount(7, $dates, 'Six Mon-Sat days + one forced-open Sunday → 7.');
    }

    public function test_global_business_closure_subtracts_days_for_all_callers(): void
    {
        // Three-day Eid closure with NO location pivot rows = global.
        $closureId = DB::table('business_closures')->insertGetId([
            'account_id' => self::ACCOUNT_ID,
            'title' => 'Eid-ul-Fitr Holidays',
            'start_date' => '2026-05-21',
            'end_date' => '2026-05-23',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertGreaterThan(0, $closureId);

        $orgRollup = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-18'),
            CarbonImmutable::parse('2026-05-25'),
        );

        $this->assertNotContains('2026-05-21', $orgRollup);
        $this->assertNotContains('2026-05-22', $orgRollup);
        $this->assertNotContains('2026-05-23', $orgRollup);

        // Same closure must also subtract when scoping to a single branch —
        // a global closure is global by definition.
        $perBranch = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [$this->defaultLocation->id],
            CarbonImmutable::parse('2026-05-18'),
            CarbonImmutable::parse('2026-05-25'),
        );
        $this->assertNotContains('2026-05-22', $perBranch);
    }

    public function test_branch_scoped_closure_does_not_subtract_for_other_branches(): void
    {
        // Closure on Branch A. Caller scoped to Branch B → no subtraction.
        $branchA = $this->defaultLocation->id;
        $branchB = (int) \App\Models\Locations::factory()->create(['name' => 'Branch B'])->id;

        $closureId = DB::table('business_closures')->insertGetId([
            'account_id' => self::ACCOUNT_ID,
            'title' => 'Branch A renovation',
            'start_date' => '2026-05-05',
            'end_date' => '2026-05-07',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('business_closure_locations')->insert([
            'business_closure_id' => $closureId,
            'location_id' => $branchA,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Caller selects only Branch B → all working days included.
        $datesB = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [$branchB],
            CarbonImmutable::parse('2026-05-04'),
            CarbonImmutable::parse('2026-05-09'),
        );
        $this->assertContains('2026-05-05', $datesB);
        $this->assertContains('2026-05-06', $datesB);
        $this->assertContains('2026-05-07', $datesB);

        // Caller selects only Branch A → closure subtracts.
        $datesA = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [$branchA],
            CarbonImmutable::parse('2026-05-04'),
            CarbonImmutable::parse('2026-05-09'),
        );
        $this->assertNotContains('2026-05-05', $datesA);
        $this->assertNotContains('2026-05-06', $datesA);
        $this->assertNotContains('2026-05-07', $datesA);

        // Caller selects BOTH branches → closure does NOT subtract because
        // Branch B was open on those days.
        $datesBoth = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [$branchA, $branchB],
            CarbonImmutable::parse('2026-05-04'),
            CarbonImmutable::parse('2026-05-09'),
        );
        $this->assertContains('2026-05-06', $datesBoth);
    }

    public function test_overlapping_closures_on_same_date_dedup_to_a_single_subtraction(): void
    {
        // Two closure rows covering 2026-05-22. Counting must not double-
        // subtract — the operating-day count is a set, not a sum.
        DB::table('business_closures')->insert([
            'account_id' => self::ACCOUNT_ID,
            'title' => 'Eid Holidays',
            'start_date' => '2026-05-21',
            'end_date' => '2026-05-23',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('business_closures')->insert([
            'account_id' => self::ACCOUNT_ID,
            'title' => 'Inventory audit',
            'start_date' => '2026-05-22',
            'end_date' => '2026-05-22',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dates = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-18'),
            CarbonImmutable::parse('2026-05-25'),
        );

        // Mon 18, Tue 19, Wed 20, Thu 21 closed, Fri 22 closed, Sat 23 closed,
        // Sun 24 default-off, Mon 25 → 4 operating days.
        $this->assertCount(4, $dates);
        $this->assertSame(
            ['2026-05-18', '2026-05-19', '2026-05-20', '2026-05-25'],
            $dates,
        );
    }

    public function test_closure_wins_over_forced_open_exception(): void
    {
        // A typo created both: someone forced Sun 2026-05-10 open, but a
        // closure also covers it. The closure must win — branch is shut.
        DB::table('working_day_exceptions')->insert([
            'account_id' => self::ACCOUNT_ID,
            'exception_date' => '2026-05-10',
            'is_working' => 1,
            'title' => 'Misfired exception',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('business_closures')->insert([
            'account_id' => self::ACCOUNT_ID,
            'title' => 'Whole week off',
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-10',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dates = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-04'),
            CarbonImmutable::parse('2026-05-10'),
        );

        $this->assertNotContains('2026-05-10', $dates);
        $this->assertCount(0, $dates, 'Whole-week closure beats every other signal.');
    }

    public function test_soft_deleted_closures_are_ignored(): void
    {
        // Cancelled Eid plan that was soft-deleted instead of forceDelete'd
        // must not subtract operating days.
        DB::table('business_closures')->insert([
            'account_id' => self::ACCOUNT_ID,
            'title' => 'Cancelled Eid plan',
            'start_date' => '2026-05-21',
            'end_date' => '2026-05-23',
            'deleted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dates = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-21'),
            CarbonImmutable::parse('2026-05-23'),
        );

        $this->assertContains('2026-05-21', $dates);
        $this->assertContains('2026-05-22', $dates);
        $this->assertContains('2026-05-23', $dates);
    }

    public function test_closure_clamped_to_query_window_when_partially_overlapping(): void
    {
        // A closure that runs Apr 25 – May 5 must only subtract the slice
        // that falls inside a May 1 – May 10 query window.
        DB::table('business_closures')->insert([
            'account_id' => self::ACCOUNT_ID,
            'title' => 'Cross-month shutdown',
            'start_date' => '2026-04-25',
            'end_date' => '2026-05-05',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dates = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-01'),
            CarbonImmutable::parse('2026-05-10'),
        );

        // May 1 (Fri), 2 (Sat) closed; May 3 (Sun) default off; May 4 (Mon),
        // 5 (Tue) closed; May 6 (Wed) .. May 9 (Sat) operating; May 10 (Sun) off.
        $this->assertNotContains('2026-05-01', $dates);
        $this->assertNotContains('2026-05-05', $dates);
        $this->assertContains('2026-05-06', $dates);
        $this->assertCount(4, $dates);
    }

    public function test_single_day_range_yields_one_or_zero_dates(): void
    {
        $monday = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-04'),
            CarbonImmutable::parse('2026-05-04'),
        );
        $this->assertSame(['2026-05-04'], $monday);

        $sunday = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-10'),
            CarbonImmutable::parse('2026-05-10'),
        );
        $this->assertSame([], $sunday);
    }
}
