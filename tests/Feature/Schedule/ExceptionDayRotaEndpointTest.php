<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * End-to-end: saving a Business-Working-Days exception that opens a
 * normally-off day auto-builds that day's rota from the previous working
 * day (POST /api/business-working-days/exceptions). Pins the controller
 * wiring + the normally-off guard.
 */
class ExceptionDayRotaEndpointTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const FRIDAY = '2026-06-05';
    private const SATURDAY = '2026-06-06';
    private const SUNDAY = '2026-06-07'; // normally OFF in the default pattern

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    private function makeRota(int $locationId = 1, int $resourceId = 101): int
    {
        return (int) DB::table('resource_has_rota')->insertGetId([
            'start' => self::FRIDAY,
            'end' => '2026-06-30',
            'account_id' => 1,
            'location_id' => $locationId,
            'resource_id' => $resourceId,
            'resource_type_id' => 2,
            'active' => 1,
            'is_consultancy' => 1,
            'is_treatment' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeRotaDay(int $rotaId, string $date, ?string $start, ?string $end): void
    {
        DB::table('resource_has_rota_days')->insert([
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'start_timestamp' => $start ? "{$date} {$start}:00" : null,
            'end_timestamp' => $end ? "{$date} {$end}:00" : null,
            'active' => 1,
            'resource_has_rota_id' => $rotaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function rotaDayCount(int $rotaId, string $date): int
    {
        return DB::table('resource_has_rota_days')
            ->where('resource_has_rota_id', $rotaId)
            ->whereDate('date', $date)
            ->whereNull('deleted_at')
            ->count();
    }

    public function test_opening_an_off_day_builds_rota_from_previous_day(): void
    {
        $rota = $this->makeRota();
        $this->makeRotaDay($rota, self::SATURDAY, '09:00', '17:00');

        $response = $this->postJson('/api/business-working-days/exceptions', [
            'exception_date' => self::SUNDAY,
            'is_working' => true,
            'title' => 'Special Sunday opening',
        ]);

        $response->assertStatus(201);

        $sunday = DB::table('resource_has_rota_days')
            ->where('resource_has_rota_id', $rota)
            ->whereDate('date', self::SUNDAY)
            ->first();
        $this->assertNotNull($sunday, 'Saving a working Sunday exception must build the rota.');
        $this->assertSame('09:00', substr((string) $sunday->start_time, 0, 5));
        $this->assertSame('17:00', substr((string) $sunday->end_time, 0, 5));
    }

    public function test_off_exception_does_not_build_rota(): void
    {
        $rota = $this->makeRota();
        $this->makeRotaDay($rota, self::SATURDAY, '09:00', '17:00');

        $this->postJson('/api/business-working-days/exceptions', [
            'exception_date' => self::SUNDAY,
            'is_working' => false,
            'title' => 'Confirmed closed',
        ])->assertStatus(201);

        $this->assertSame(0, $this->rotaDayCount($rota, self::SUNDAY), 'An off-exception must not build any rota.');
    }

    public function test_working_exception_on_a_normally_working_day_does_not_clone(): void
    {
        // Saturday is a working day in the default weekly pattern, so an
        // is_working exception there must NOT back-fill from Friday — the
        // per-doctor off-rows on working days are intentional.
        $rota = $this->makeRota();
        $this->makeRotaDay($rota, self::FRIDAY, '08:00', '16:00');

        $this->postJson('/api/business-working-days/exceptions', [
            'exception_date' => self::SATURDAY,
            'is_working' => true,
        ])->assertStatus(201);

        $this->assertSame(0, $this->rotaDayCount($rota, self::SATURDAY), 'A normally-working day must not be cloned into.');
    }
}
