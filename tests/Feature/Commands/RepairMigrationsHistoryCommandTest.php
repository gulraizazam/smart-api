<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the `migrations:repair-history` command:
 *
 *  1. Missing rows for migration files on disk are inserted at the
 *     requested batch number.
 *  2. Re-running the command is a no-op (idempotent).
 *  3. --dry-run does not write.
 */
class RepairMigrationsHistoryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_inserts_missing_rows_for_files_on_disk(): void
    {
        DB::table('migrations')->truncate();

        $diskCount = count(File::glob(database_path('migrations/*.php')));

        $this->artisan('migrations:repair-history', ['--batch' => 1])
            ->expectsOutputToContain("Missing rows: {$diskCount}")
            ->expectsOutputToContain("Inserted {$diskCount} migration history rows at batch 1.")
            ->assertSuccessful();

        $this->assertSame($diskCount, DB::table('migrations')->count());
        $this->assertSame(1, DB::table('migrations')->distinct('batch')->count('batch'));
    }

    public function test_is_idempotent_on_rerun(): void
    {
        DB::table('migrations')->truncate();
        $this->artisan('migrations:repair-history')->assertSuccessful();

        $countAfterFirst = DB::table('migrations')->count();

        $this->artisan('migrations:repair-history')
            ->expectsOutputToContain('Nothing to repair.')
            ->assertSuccessful();

        $this->assertSame($countAfterFirst, DB::table('migrations')->count());
    }

    public function test_dry_run_does_not_write(): void
    {
        DB::table('migrations')->truncate();

        $this->artisan('migrations:repair-history', ['--dry-run' => true])
            ->expectsOutputToContain('--dry-run: skipping write')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('migrations')->count());
    }
}
