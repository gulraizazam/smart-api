<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\BusinessClosure;
use App\Models\WorkingDayException;
use App\Services\DoctorDashboard\BenchmarkCalculator;
use App\Support\OperatingDays;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the freshness contract:
 *
 *   ANY write to a closure / working-day exception / weekly-pattern
 *   setting MUST bump OperatingDays::version() for the affected account,
 *   so downstream caches (BenchmarkCalculator's per-doctor revenue pool)
 *   invalidate immediately rather than waiting for their natural TTL.
 *
 * This matters operationally because closures are usually added the day
 * of (or day before) — a 1-hour stale cache would mis-rank doctors on
 * the very dashboard people check to spot mis-rankings.
 *
 * Zero cron / queue jobs involved — the path is synchronous: write →
 * Eloquent observer → Cache::increment → key changes on the next read.
 */
class OperatingDaysCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const ACCOUNT_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        // Clear any leakage from prior runs of the same cache store.
        Cache::forget("operating_days_version:" . self::ACCOUNT_ID);
    }

    public function test_version_starts_at_zero(): void
    {
        $this->assertSame(0, OperatingDays::version(self::ACCOUNT_ID));
    }

    public function test_business_closure_creation_bumps_version(): void
    {
        $before = OperatingDays::version(self::ACCOUNT_ID);

        BusinessClosure::create([
            'account_id' => self::ACCOUNT_ID,
            'title' => 'Test holiday',
            'start_date' => '2026-05-22',
            'end_date' => '2026-05-22',
        ]);

        $after = OperatingDays::version(self::ACCOUNT_ID);
        $this->assertGreaterThan($before, $after, 'Creating a closure must bump the version counter.');
    }

    public function test_business_closure_update_and_delete_bump_version(): void
    {
        $closure = BusinessClosure::create([
            'account_id' => self::ACCOUNT_ID,
            'title' => 'Eid',
            'start_date' => '2026-05-22',
            'end_date' => '2026-05-22',
        ]);

        $afterCreate = OperatingDays::version(self::ACCOUNT_ID);
        $closure->update(['title' => 'Eid Holidays']);
        $afterUpdate = OperatingDays::version(self::ACCOUNT_ID);
        $closure->delete();
        $afterDelete = OperatingDays::version(self::ACCOUNT_ID);

        $this->assertGreaterThan($afterCreate, $afterUpdate, 'Updating a closure must bump.');
        $this->assertGreaterThan($afterUpdate, $afterDelete, 'Deleting a closure must bump.');
    }

    public function test_working_day_exception_lifecycle_bumps_version(): void
    {
        $before = OperatingDays::version(self::ACCOUNT_ID);

        $exception = WorkingDayException::create([
            'account_id' => self::ACCOUNT_ID,
            'exception_date' => '2026-05-13',
            'is_working' => 0,
            'title' => 'Training',
        ]);

        $afterCreate = OperatingDays::version(self::ACCOUNT_ID);
        $exception->update(['title' => 'Staff training']);
        $afterUpdate = OperatingDays::version(self::ACCOUNT_ID);
        $exception->delete();
        $afterDelete = OperatingDays::version(self::ACCOUNT_ID);

        $this->assertGreaterThan($before, $afterCreate);
        $this->assertGreaterThan($afterCreate, $afterUpdate);
        $this->assertGreaterThan($afterUpdate, $afterDelete);
    }

    public function test_benchmark_cache_key_changes_after_closure_added(): void
    {
        $calc = app(BenchmarkCalculator::class);
        $reflection = new ReflectionClass($calc);
        $method = $reflection->getMethod('buildCacheKey');
        $method->setAccessible(true);

        $keyBefore = $method->invokeArgs($calc, ['2026-05-01', self::ACCOUNT_ID]);

        BusinessClosure::create([
            'account_id' => self::ACCOUNT_ID,
            'title' => 'Just added',
            'start_date' => '2026-05-22',
            'end_date' => '2026-05-22',
        ]);

        $keyAfter = $method->invokeArgs($calc, ['2026-05-01', self::ACCOUNT_ID]);

        $this->assertNotSame(
            $keyBefore,
            $keyAfter,
            'Adding a closure must change the benchmark cache key so the stale entry is bypassed.',
        );
    }

    public function test_version_bumps_are_account_isolated(): void
    {
        // Account 2 closure must not bump account 1's counter.
        \Illuminate\Support\Facades\DB::table('accounts')->insertOrIgnore([
            ['id' => 2, 'name' => 'Other', 'email' => 'o@x.test', 'contact' => '0', 'suspended' => '0', 'created_at' => now(), 'updated_at' => now()],
        ]);
        Cache::forget("operating_days_version:2");

        $account1Before = OperatingDays::version(self::ACCOUNT_ID);

        BusinessClosure::create([
            'account_id' => 2,
            'title' => 'Other tenant closure',
            'start_date' => '2026-05-22',
            'end_date' => '2026-05-22',
        ]);

        $account1After = OperatingDays::version(self::ACCOUNT_ID);
        $account2After = OperatingDays::version(2);

        $this->assertSame($account1Before, $account1After, 'Tenant 2 writes must not bump tenant 1 version.');
        $this->assertGreaterThan(0, $account2After, 'Tenant 2 version must have moved.');
    }
}
