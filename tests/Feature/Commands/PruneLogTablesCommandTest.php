<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Behaviour spec for `logs:prune` — exports log rows older than the retention
 * window to a gzipped CSV (the keep-copy), then deletes them.
 *
 * Pins:
 *   1. --dry-run reports eligible counts and changes nothing (no delete, no file).
 *   2. A real run deletes rows older than the cutoff, keeps recent rows, and
 *      writes an export file containing the removed data.
 *   3. --table scopes the run to a single table.
 *   4. Nothing-to-do path is clean.
 *   5. An unknown --table is rejected (INVALID).
 */
class PruneLogTablesCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $exportDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exportDir = storage_path('app/testing-log-archive');
        File::deleteDirectory($this->exportDir);

        config([
            'log_retention.export_path' => $this->exportDir,
            'log_retention.months' => 6,
            'log_retention.chunk' => 500,
            'log_retention.max_rows_per_run' => 0,
        ]);

        // Frozen so the 6-month cutoff (→ 2025-12-08 12:00) is deterministic.
        Carbon::setTestNow('2026-06-08 12:00:00');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->exportDir);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedSms(string $createdAt): int
    {
        return (int) DB::table('sms_logs')->insertGetId([
            'text' => 'hello world',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function seedActivity(string $createdAt): int
    {
        return (int) DB::table('activities')->insertGetId([
            'action' => 'create',
            'description' => 'did a thing',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function test_dry_run_reports_eligible_and_changes_nothing(): void
    {
        $this->seedSms('2025-09-01 00:00:00');   // ~9 months old → eligible
        $this->seedSms('2026-05-01 00:00:00');   // ~1 month old  → kept

        $this->artisan('logs:prune', ['--dry-run' => true])
            ->expectsOutputToContain('sms_logs: 1 row')
            ->assertExitCode(0);

        $this->assertSame(2, DB::table('sms_logs')->count());
        $this->assertDirectoryDoesNotExist($this->exportDir);
    }

    public function test_prune_deletes_old_keeps_recent_and_writes_export(): void
    {
        $oldSms = $this->seedSms('2025-09-01 00:00:00');
        $recentSms = $this->seedSms('2026-05-01 00:00:00');
        $oldActivity = $this->seedActivity('2025-08-01 00:00:00');
        $recentActivity = $this->seedActivity('2026-05-15 00:00:00');

        $this->artisan('logs:prune', ['--force' => true])->assertExitCode(0);

        // Old removed, recent retained.
        $this->assertDatabaseMissing('sms_logs', ['id' => $oldSms]);
        $this->assertDatabaseHas('sms_logs', ['id' => $recentSms]);
        $this->assertDatabaseMissing('activities', ['id' => $oldActivity]);
        $this->assertDatabaseHas('activities', ['id' => $recentActivity]);

        // Export written, with the removed row inside it.
        $smsExport = $this->latestExport('sms_logs');
        $this->assertNotNull($smsExport, 'sms_logs export file was not created');
        $this->assertStringContainsString('hello world', $this->readGz($smsExport));

        $this->assertNotNull($this->latestExport('activities'), 'activities export file was not created');
    }

    public function test_table_filter_limits_scope(): void
    {
        $oldSms = $this->seedSms('2025-09-01 00:00:00');
        $oldActivity = $this->seedActivity('2025-08-01 00:00:00');

        $this->artisan('logs:prune', ['--force' => true, '--table' => 'sms_logs'])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('sms_logs', ['id' => $oldSms]);
        $this->assertDatabaseHas('activities', ['id' => $oldActivity]); // untouched
    }

    public function test_nothing_to_prune_when_all_rows_recent(): void
    {
        $this->seedSms('2026-05-01 00:00:00');

        $this->artisan('logs:prune', ['--force' => true])
            ->expectsOutputToContain('nothing older than the cutoff')
            ->assertExitCode(0);

        $this->assertSame(1, DB::table('sms_logs')->count());
    }

    public function test_unknown_table_option_is_rejected(): void
    {
        $this->artisan('logs:prune', ['--table' => 'patients'])
            ->assertExitCode(2); // Command::INVALID
    }

    private function latestExport(string $table): ?string
    {
        $files = glob($this->exportDir."/{$table}_before_*.csv.gz") ?: [];

        return $files[0] ?? null;
    }

    private function readGz(string $path): string
    {
        return (string) gzdecode((string) file_get_contents($path));
    }
}
