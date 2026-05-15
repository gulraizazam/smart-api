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
 * Adversarial QA pass for App\Support\OperatingDays — probes edge cases
 * the happy-path test suite (OperatingDaysTest.php) doesn't cover:
 *
 *   - Account isolation (rules from account 2 don't leak into account 1)
 *   - Reversed / empty / equal-day ranges
 *   - Duplicate working_day_exceptions rows for the same date
 *   - Multiple settings rows for the same slug (data-integrity drift)
 *   - Invalid JSON in business_working_days settings
 *   - Pseudo-location ("All Centres") closures
 *   - Closure whose pivot has mixed pseudo + real locations
 *   - Closure fully outside the query window
 *
 * If any of these regress, the helper would silently produce a wrong
 * answer for invoice / utilization / dashboard denominators.
 */
class OperatingDaysEdgeCaseTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const ACCOUNT_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_account_isolation_other_accounts_exceptions_do_not_leak(): void
    {
        // Account 2 has a closed-exception on a Tuesday. Account 1's query
        // for the same Tuesday must STILL show it as working.
        DB::table('accounts')->insertOrIgnore([
            ['id' => 2, 'name' => 'Other Tenant', 'email' => 'other@x.test', 'contact' => '0', 'suspended' => '0', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('working_day_exceptions')->insert([
            'account_id' => 2,
            'exception_date' => '2026-05-12',
            'is_working' => 0,
            'title' => 'Tenant 2 closure',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dates = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-12'),
            CarbonImmutable::parse('2026-05-12'),
        );

        $this->assertSame(['2026-05-12'], $dates, 'Tenant 2 exceptions must not affect tenant 1 results.');
    }

    public function test_account_isolation_other_accounts_closures_do_not_leak(): void
    {
        DB::table('accounts')->insertOrIgnore([
            ['id' => 2, 'name' => 'Other Tenant', 'email' => 'other@x.test', 'contact' => '0', 'suspended' => '0', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('business_closures')->insert([
            'account_id' => 2,
            'title' => 'Tenant 2 holiday',
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-12',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dates = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-12'),
            CarbonImmutable::parse('2026-05-12'),
        );

        $this->assertSame(['2026-05-12'], $dates);
    }

    public function test_reversed_range_returns_empty(): void
    {
        $dates = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-10'),
            CarbonImmutable::parse('2026-05-05'),
        );
        $this->assertSame([], $dates);
    }

    public function test_invalid_json_in_settings_falls_back_to_defaults(): void
    {
        // Someone poked the settings row directly. Helper must not crash.
        DB::table('settings')->insert([
            'account_id' => self::ACCOUNT_ID,
            'slug' => 'business_working_days',
            'data' => 'this is not json',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dates = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-04'),
            CarbonImmutable::parse('2026-05-10'),
        );

        // Defaults Mon-Sat — 6 days.
        $this->assertCount(6, $dates);
        $this->assertNotContains('2026-05-10', $dates);
    }

    public function test_partial_json_settings_only_overrides_named_days(): void
    {
        // Settings row only flips Sunday true; other days inherit defaults.
        DB::table('settings')->insert([
            'account_id' => self::ACCOUNT_ID,
            'slug' => 'business_working_days',
            'data' => json_encode(['sunday' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dates = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-04'),
            CarbonImmutable::parse('2026-05-10'),
        );

        $this->assertCount(7, $dates, 'Mon-Sat default + Sunday opened → 7.');
    }

    public function test_duplicate_exception_rows_for_same_date(): void
    {
        // Data-integrity drift: two rows for the same (account, date) — the
        // model has no unique constraint. Pluck keeps the LAST row; we
        // assert the result is at least deterministic enough to be one of
        // the two values, not a crash.
        DB::table('working_day_exceptions')->insert([
            ['account_id' => self::ACCOUNT_ID, 'exception_date' => '2026-05-13', 'is_working' => 1, 'title' => 'First', 'created_at' => now(), 'updated_at' => now()],
            ['account_id' => self::ACCOUNT_ID, 'exception_date' => '2026-05-13', 'is_working' => 0, 'title' => 'Second', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $dates = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-13'),
            CarbonImmutable::parse('2026-05-13'),
        );

        // Don't pin which exception wins — just confirm no crash. Document
        // the known limitation: callers should enforce uniqueness at the
        // write path.
        $this->assertContains(count($dates), [0, 1], 'Duplicate exception rows produce a 0-or-1 result, not a crash.');
    }

    public function test_pseudo_location_closure_acts_as_global(): void
    {
        // A closure linked to ONLY pseudo-locations ("All " prefix) is
        // treated as global — applies to every branch.
        $pseudo = (int) \App\Models\Locations::factory()->create(['name' => 'All Centres'])->id;
        $real = (int) $this->defaultLocation->id;

        $closureId = DB::table('business_closures')->insertGetId([
            'account_id' => self::ACCOUNT_ID,
            'title' => 'Company-wide off-site',
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-12',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('business_closure_locations')->insert([
            'business_closure_id' => $closureId,
            'location_id' => $pseudo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $datesOrg = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-12'),
            CarbonImmutable::parse('2026-05-12'),
        );
        $datesReal = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [$real],
            CarbonImmutable::parse('2026-05-12'),
            CarbonImmutable::parse('2026-05-12'),
        );

        $this->assertSame([], $datesOrg, 'Pseudo-only closure must subtract org-wide.');
        $this->assertSame([], $datesReal, 'Pseudo-only closure must also subtract for a real branch caller.');
    }

    public function test_mixed_pseudo_and_real_locations_is_branch_specific(): void
    {
        // Pivot has [pseudo, real]. Mixed list → NOT global (per allPseudo()
        // which requires every name to start with "All "). The closure
        // applies to the listed locations, not org-wide.
        $pseudo = (int) \App\Models\Locations::factory()->create(['name' => 'All Centres'])->id;
        $real = (int) $this->defaultLocation->id;
        $other = (int) \App\Models\Locations::factory()->create(['name' => 'Lahore'])->id;

        $closureId = DB::table('business_closures')->insertGetId([
            'account_id' => self::ACCOUNT_ID,
            'title' => 'Mixed pivot weirdness',
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-12',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('business_closure_locations')->insert([
            ['business_closure_id' => $closureId, 'location_id' => $pseudo, 'created_at' => now(), 'updated_at' => now()],
            ['business_closure_id' => $closureId, 'location_id' => $real, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 'other' branch wasn't in pivot → not closed.
        $datesOther = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [$other],
            CarbonImmutable::parse('2026-05-12'),
            CarbonImmutable::parse('2026-05-12'),
        );
        $this->assertSame(['2026-05-12'], $datesOther,
            'A mixed-pivot closure must NOT close branches not in its pivot.');

        // 'real' branch IS in pivot → closed.
        $datesReal = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [$real],
            CarbonImmutable::parse('2026-05-12'),
            CarbonImmutable::parse('2026-05-12'),
        );
        $this->assertSame([], $datesReal);
    }

    public function test_closure_fully_outside_query_window_does_nothing(): void
    {
        DB::table('business_closures')->insert([
            'account_id' => self::ACCOUNT_ID,
            'title' => 'Distant past closure',
            'start_date' => '2020-01-01',
            'end_date' => '2020-01-10',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dates = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-04'),
            CarbonImmutable::parse('2026-05-09'),
        );

        $this->assertCount(6, $dates, 'Out-of-window closure must not affect the count.');
    }

    public function test_multiple_settings_rows_uses_one_deterministically(): void
    {
        // Drift scenario: two settings rows for the same slug.
        DB::table('settings')->insert([
            ['account_id' => self::ACCOUNT_ID, 'slug' => 'business_working_days', 'data' => json_encode(['sunday' => true]), 'created_at' => now(), 'updated_at' => now()],
            ['account_id' => self::ACCOUNT_ID, 'slug' => 'business_working_days', 'data' => json_encode(['sunday' => false]), 'created_at' => now(), 'updated_at' => now()],
        ]);

        // value() returns the first match; either answer is acceptable as
        // long as we don't crash and the count is reasonable.
        $dates = OperatingDays::datesInRange(
            self::ACCOUNT_ID,
            [],
            CarbonImmutable::parse('2026-05-04'),
            CarbonImmutable::parse('2026-05-10'),
        );
        $this->assertContains(count($dates), [6, 7]);
    }
}
