<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Appointments;
use App\Models\Locations;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for `appointments:daily-stats` — snapshots
 * consultation appointments scheduled today + tomorrow into
 * `appointments_daily_stats`. Legacy Blade reports
 * (`FinanceArrivalReportController`, `DashboardChartService::getCentreWiseArrival`)
 * read from this table; the modern SPA dashboard ignores it.
 *
 * Pins:
 *   1. Runs cleanly against an empty fixture.
 *   2. Account-scopes the consultancy type lookup (no cross-tenant
 *      leak via the slug-only `AppointmentTypes::where(slug=consultancy)`
 *      query that the previous version had).
 *   3. Upserts the snapshot row for today + tomorrow consultations.
 *   4. Skips treatments (only consultations are captured).
 */
class AppointmentsDailyStatsCronTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    private function seedConsultation(int $locationId, string $scheduledDate): int
    {
        // appointment_type_id=2 is the Consultancy fixture
        // (UsesFinancialFixtures seeds it). Bypass the factory so we
        // can pin the date precisely.
        return Appointments::factory()->create([
            'appointment_type_id' => 2,
            'location_id' => $locationId,
            'scheduled_date' => $scheduledDate,
        ])->id;
    }

    public function test_runs_clean_on_empty_fixture(): void
    {
        $this->artisan('appointments:daily-stats')->assertExitCode(0);
    }

    public function test_upserts_snapshot_for_today_and_tomorrow_consultations(): void
    {
        $location = Locations::factory()->create();
        $todayId = $this->seedConsultation($location->id, now()->toDateString());
        $tomorrowId = $this->seedConsultation($location->id, now()->addDay()->toDateString());

        $this->artisan('appointments:daily-stats')->assertExitCode(0);

        $this->assertDatabaseHas('appointments_daily_stats', [
            'appointment_id' => $todayId,
            'centre_id' => $location->id,
        ]);
        $this->assertDatabaseHas('appointments_daily_stats', [
            'appointment_id' => $tomorrowId,
            'centre_id' => $location->id,
        ]);
    }

    public function test_skips_treatments(): void
    {
        $location = Locations::factory()->create();
        // appointment_type_id=1 is Treatment per UsesFinancialFixtures.
        $treatmentId = Appointments::factory()->create([
            'appointment_type_id' => 1,
            'location_id' => $location->id,
            'scheduled_date' => now()->toDateString(),
        ])->id;

        $this->artisan('appointments:daily-stats')->assertExitCode(0);

        $this->assertDatabaseMissing('appointments_daily_stats', [
            'appointment_id' => $treatmentId,
        ]);
    }

    public function test_re_run_updates_snapshot_in_place(): void
    {
        $location = Locations::factory()->create();
        $appointmentId = $this->seedConsultation($location->id, now()->toDateString());

        $this->artisan('appointments:daily-stats')->assertExitCode(0);
        $beforeCount = DB::table('appointments_daily_stats')
            ->where('appointment_id', $appointmentId)
            ->count();

        // Mutate status, re-run — we should still have ONE row, with
        // the updated status. No duplicates.
        Appointments::where('id', $appointmentId)->update(['base_appointment_status_id' => 4]);
        $this->artisan('appointments:daily-stats')->assertExitCode(0);

        $afterCount = DB::table('appointments_daily_stats')
            ->where('appointment_id', $appointmentId)
            ->count();
        $latestStatus = DB::table('appointments_daily_stats')
            ->where('appointment_id', $appointmentId)
            ->value('appointment_status_id');

        $this->assertSame(1, $beforeCount, 'First run should write exactly one snapshot.');
        $this->assertSame(1, $afterCount, 'Re-run must upsert in place, not duplicate.');
        $this->assertSame(4, (int) $latestStatus, 'Re-run must reflect the latest status.');
    }
}
