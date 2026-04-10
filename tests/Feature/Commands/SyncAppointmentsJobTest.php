<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Jobs\SyncAppointmentsJob;
use App\Models\Accounts;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for SyncAppointmentsJob — pre-divides appointments
 * for an account into id-range batches and writes them to the
 * heavy_lifters work queue. Phase-F audit flagged this as a
 * performance-critical path (replaced an O(N) LIMIT/OFFSET walker).
 *
 * Pins:
 *   1. Empty appointments table produces zero heavy_lifter rows.
 *   2. A populated account gets at least one batch row written with
 *      the correct type + account_id.
 */
class SyncAppointmentsJobTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_job_produces_no_batches_when_account_has_no_appointments(): void
    {
        $account = Accounts::firstOrFail();

        DB::table('heavy_lifters')->where('account_id', $account->id)->delete();

        (new SyncAppointmentsJob($account))->handle();

        $this->assertSame(
            0,
            DB::table('heavy_lifters')
                ->where('account_id', $account->id)
                ->where('type', 'upload-appointments')
                ->count(),
            'Empty appointment table should produce no work rows.',
        );
    }

    public function test_job_writes_a_batch_row_for_populated_account(): void
    {
        $account = Accounts::firstOrFail();

        DB::table('heavy_lifters')->where('account_id', $account->id)->delete();

        // Seed a single appointment so the id-range walker has one range.
        DB::table('appointments')->insert([
            'name' => 'Smoke Appt',
            'scheduled_date' => now()->toDateString(),
            'scheduled_time' => '10:00:00',
            'lead_id' => 1,
            'patient_id' => 1,
            'doctor_id' => 1,
            'region_id' => 1,
            'city_id' => 1,
            'location_id' => 1,
            'account_id' => $account->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new SyncAppointmentsJob($account))->handle();

        $rows = DB::table('heavy_lifters')
            ->where('account_id', $account->id)
            ->where('type', 'upload-appointments')
            ->get();

        $this->assertCount(1, $rows, 'Expected exactly one batch row for a single seeded appointment.');

        $payload = json_decode($rows[0]->payload, true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('id_min', $payload);
        $this->assertArrayHasKey('id_max', $payload);
        $this->assertSame($account->id, $payload['account_id']);
    }

    public function test_soft_deleted_appointments_are_excluded_from_batches(): void
    {
        $account = Accounts::firstOrFail();

        DB::table('heavy_lifters')->where('account_id', $account->id)->delete();

        DB::table('appointments')->insert([
            'name' => 'Deleted Appt',
            'scheduled_date' => now()->toDateString(),
            'scheduled_time' => '10:00:00',
            'lead_id' => 1,
            'patient_id' => 1,
            'doctor_id' => 1,
            'region_id' => 1,
            'city_id' => 1,
            'location_id' => 1,
            'account_id' => $account->id,
            'deleted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new SyncAppointmentsJob($account))->handle();

        $this->assertSame(
            0,
            DB::table('heavy_lifters')
                ->where('account_id', $account->id)
                ->where('type', 'upload-appointments')
                ->count(),
            'Soft-deleted rows should not enter the work queue.',
        );
    }
}
