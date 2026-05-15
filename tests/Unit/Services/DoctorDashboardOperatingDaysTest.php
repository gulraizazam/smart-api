<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DoctorDashboard\BenchmarkCalculator;
use App\Services\DoctorDashboard\DoctorDashboardService;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the OperatingDays wire-up across the doctor-dashboard surface
 * (closed 2026-05-15 — until then these consumers used hardcoded Mon-Sat
 * loops that ignored closures + per-date exceptions, silently inflating
 * per-day revenue averages on Eid / training / branch-closure weeks).
 *
 * Each consumer's wrapper is tested via reflection because they're
 * private members of larger services; pinning at this seam catches a
 * future regression where someone "simplifies" the call back to a
 * Carbon loop.
 */
class DoctorDashboardOperatingDaysTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const ACCOUNT_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_doctor_dashboard_count_working_days_respects_business_closure(): void
    {
        DB::table('business_closures')->insert([
            'account_id' => self::ACCOUNT_ID,
            'title' => 'Eid Holidays',
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-06',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(DoctorDashboardService::class);
        $count = $this->invoke($service, 'countWorkingDays', ['2026-05-04', '2026-05-09', self::ACCOUNT_ID]);

        // Mon-Sat = 6 days; closure removes May 4/5/6 (Mon/Tue/Wed). Expect 3.
        $this->assertSame(3, $count, 'Closure must shrink the per-day-revenue denominator.');
    }

    public function test_doctor_dashboard_count_working_days_respects_forced_open_exception(): void
    {
        DB::table('working_day_exceptions')->insert([
            'account_id' => self::ACCOUNT_ID,
            'exception_date' => '2026-05-10',
            'is_working' => 1,
            'title' => 'Sunday open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(DoctorDashboardService::class);
        $count = $this->invoke($service, 'countWorkingDays', ['2026-05-04', '2026-05-10', self::ACCOUNT_ID]);

        // Mon-Sat = 6 days + forced-open Sunday = 7.
        $this->assertSame(7, $count);
    }

    public function test_doctor_dashboard_working_days_remaining_subtracts_operating_days_only(): void
    {
        // Force "now" to a known anchor inside the window so the elapsed
        // count is predictable.
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-05-15 10:00:00'));

        // Closed days inside the elapsed slice (May 1–15).
        DB::table('business_closures')->insert([
            'account_id' => self::ACCOUNT_ID,
            'title' => 'Eid Holidays',
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-06',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(DoctorDashboardService::class);
        // Hypothetical 26-day target month, range May 1 – May 31.
        $remaining = $this->invoke(
            $service,
            'getWorkingDaysRemaining',
            [26, '2026-05-01', '2026-05-31', self::ACCOUNT_ID],
        );

        // Operating days May 1 – May 15: Fri 1, Sat 2 = 2; (Sun 3 off);
        // May 4–6 closed; (Sun 10 off); Mon 4, Tue 5, Wed 6 closed;
        // May 7 Thu, 8 Fri, 9 Sat → 3; May 11–15 Mon–Fri → 5.
        // Total elapsed = 2 + 0 + 3 + 5 = 10. Remaining = 26 - 10 = 16.
        $this->assertSame(16, $remaining);

        \Carbon\Carbon::setTestNow(); // reset
    }

    public function test_benchmark_calculator_revenue_per_day_per_doctor_respects_closure(): void
    {
        DB::table('business_closures')->insert([
            'account_id' => self::ACCOUNT_ID,
            'title' => 'Eid Holidays',
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-06',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $calc = app(BenchmarkCalculator::class);
        $result = $this->invoke(
            $calc,
            'getRevenuePerDayPerDoctor',
            [[42 => 30000.0, 99 => 60000.0], '2026-05-04', '2026-05-09', self::ACCOUNT_ID],
        );

        // 3 operating days (Thu 7, Fri 8, Sat 9). 30000/3 = 10000; 60000/3 = 20000.
        $this->assertSame(10000.0, (float) $result[42]);
        $this->assertSame(20000.0, (float) $result[99]);
    }

    public function test_benchmark_calculator_returns_empty_when_no_operating_days(): void
    {
        // A range with only Sundays — no operating days, no per-doctor avg.
        $calc = app(BenchmarkCalculator::class);
        $result = $this->invoke(
            $calc,
            'getRevenuePerDayPerDoctor',
            [[42 => 30000.0], '2026-05-10', '2026-05-10', self::ACCOUNT_ID],
        );

        $this->assertSame([], $result);
    }

    /**
     * Invoke a private/protected method by name.
     */
    private function invoke(object $target, string $method, array $args)
    {
        $reflection = new ReflectionClass($target);
        $m = $reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($target, $args);
    }
}
