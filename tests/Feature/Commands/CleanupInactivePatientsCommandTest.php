<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for the `patients:cleanup-inactive` command — the
 * cron that soft-deletes patient rows that have no linked
 * appointments, packages, or package advances.
 *
 * Pins:
 *   1. With no inactive patients, the command exits SUCCESS and
 *      prints the "none found" branch.
 *   2. With an inactive patient and --dry-run, the patient is NOT
 *      actually deleted.
 *   3. The --year filter scopes to the requested year.
 */
class CleanupInactivePatientsCommandTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_command_exits_success_when_no_inactive_patients_exist(): void
    {
        $this->artisan('patients:cleanup-inactive', ['--dry-run' => true])
            ->expectsOutputToContain('No inactive patients found')
            ->assertExitCode(0);
    }

    public function test_dry_run_does_not_actually_delete_inactive_patients(): void
    {
        // Create an orphan patient user (no appointments / packages /
        // package advances) — should be flagged as inactive by the
        // cleanup query.
        $patientId = DB::table('users')->insertGetId([
            'name' => 'Orphan Patient',
            'email' => 'orphan-' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
            'account_id' => (int) Auth::user()->account_id,
            'user_type_id' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('patients:cleanup-inactive', ['--dry-run' => true])
            ->assertExitCode(0);

        // Dry run: deleted_at stays null.
        $this->assertNull(DB::table('users')->where('id', $patientId)->value('deleted_at'));
    }

    public function test_year_filter_restricts_the_cleanup_scope(): void
    {
        // Seed a patient created outside the requested year — it
        // should not appear in the "found" count at all.
        DB::table('users')->insert([
            'name' => 'Old Patient',
            'email' => 'old-' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
            'account_id' => (int) Auth::user()->account_id,
            'user_type_id' => 3,
            'created_at' => '2020-06-15 00:00:00',
            'updated_at' => '2020-06-15 00:00:00',
        ]);

        $this->artisan('patients:cleanup-inactive', [
            '--dry-run' => true,
            '--year' => '1999',
        ])
            ->expectsOutputToContain('No inactive patients found')
            ->assertExitCode(0);
    }
}
