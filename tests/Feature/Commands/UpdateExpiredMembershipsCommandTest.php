<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for the `memberships:expire` command — the nightly
 * cron that flips the `active` flag to 0 on any membership whose
 * `end_date` has passed.
 *
 * Pins:
 *   1. Command runs on an empty fixture without error.
 *   2. A membership with end_date in the past and active=1 gets
 *      flipped to active=0 after the command runs.
 *   3. A membership with a future end_date is untouched.
 */
class UpdateExpiredMembershipsCommandTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private int $membershipTypeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        // membership_types FK comes from an isolated lookup table;
        // seed a minimal row inline — only this test uses it.
        $this->membershipTypeId = (int) DB::table('membership_types')->insertGetId([
            'name' => 'Gold',
            'period' => 12,
            'amount' => 1000.00,
            'active' => 1,
            'created_by' => (int) Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedMembership(string $endDate, int $active): int
    {
        return (int) DB::table('memberships')->insertGetId([
            'code' => 'MEM-' . uniqid(),
            'membership_type_id' => $this->membershipTypeId,
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => $endDate,
            'patient_id' => null,
            'active' => $active,
            'created_by' => (int) Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_command_runs_successfully_on_empty_fixture(): void
    {
        $this->artisan('memberships:expire')->assertExitCode(0);
    }

    public function test_command_flips_expired_membership_to_inactive(): void
    {
        $expiredId = $this->seedMembership(now()->subDay()->format('Y-m-d'), 1);

        $this->artisan('memberships:expire')->assertExitCode(0);

        $this->assertSame(0, (int) DB::table('memberships')->where('id', $expiredId)->value('active'));
    }

    public function test_command_leaves_future_membership_untouched(): void
    {
        $futureId = $this->seedMembership(now()->addMonth()->format('Y-m-d'), 1);

        $this->artisan('memberships:expire')->assertExitCode(0);

        $this->assertSame(1, (int) DB::table('memberships')->where('id', $futureId)->value('active'));
    }
}
