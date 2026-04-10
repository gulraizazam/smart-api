<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for the `audit:archive` command — moves old
 * audit_trail + audit_trail_changes rows into paired _archive
 * tables. This command is retention-policy critical and was flagged
 * in the audit.
 *
 * Pins:
 *   1. With nothing older than the cutoff, the command prints
 *      "Nothing to archive" and exits SUCCESS.
 *   2. --dry-run accepted as a flag.
 *   3. A trail older than the cutoff is reported as eligible (the
 *      "Found N trails ..." output branch fires).
 */
class ArchiveAuditTrailsCommandTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_command_reports_nothing_to_archive_on_empty_fixture(): void
    {
        $this->artisan('audit:archive', ['--dry-run' => true])
            ->expectsOutputToContain('Nothing to archive')
            ->assertExitCode(0);
    }

    public function test_command_accepts_custom_month_window(): void
    {
        $this->artisan('audit:archive', [
            '--months' => 24,
            '--dry-run' => true,
        ])->assertExitCode(0);
    }

    public function test_dry_run_reports_old_trails_as_eligible_without_moving_them(): void
    {
        // Seed a single trail row older than the default 12-month
        // cutoff so the command's eligible-count branch fires.
        DB::table('audit_trails')->insert([
            'audit_trail_action_name' => 1,
            'audit_trail_table_name' => 1,
            'table_record_id' => 1,
            'user_id' => (int) \Illuminate\Support\Facades\Auth::id(),
            'created_at' => now()->subYears(2),
            'updated_at' => now()->subYears(2),
        ]);

        $this->artisan('audit:archive', ['--dry-run' => true])
            ->expectsOutputToContain('eligible for archival')
            ->expectsOutputToContain('Dry run')
            ->assertExitCode(0);

        // Row is still in the source table after dry-run.
        $this->assertSame(1, DB::table('audit_trails')->count());
    }
}
