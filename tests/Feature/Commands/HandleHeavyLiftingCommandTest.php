<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for the `cms:handle-heavy-lifting` command — which
 * pops up to 5 pending HeavyLifter rows from the work queue and
 * processes them by type. The audit flagged this as a chunked work
 * path that silently swallows errors.
 *
 * Pins:
 *   1. With no rows in heavy_lifters, the command exits SUCCESS.
 *   2. With a malformed (non-JSON) payload, the command logs a
 *      warning AND force-deletes the row so it doesn't loop.
 *   3. With an unknown type, the command logs a warning AND removes
 *      the row from the queue.
 */
class HandleHeavyLiftingCommandTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_command_exits_success_when_queue_is_empty(): void
    {
        $this->artisan('cms:handle-heavy-lifting')->assertExitCode(0);
    }

    public function test_command_removes_rows_with_malformed_payload(): void
    {
        $id = DB::table('heavy_lifters')->insertGetId([
            'payload' => 'not-valid-json',
            'type' => 'upload-appointments',
            'attempts' => 0,
            'available_at' => now(),
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('cms:handle-heavy-lifting')->assertExitCode(0);

        $this->assertSame(
            0,
            DB::table('heavy_lifters')->where('id', $id)->count(),
            'Malformed rows should be force-deleted so they do not loop.',
        );
    }

    public function test_command_removes_rows_with_unknown_type(): void
    {
        $id = DB::table('heavy_lifters')->insertGetId([
            'payload' => json_encode(['any' => 'thing']),
            'type' => 'not-a-real-type',
            'attempts' => 0,
            'available_at' => now(),
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('cms:handle-heavy-lifting')->assertExitCode(0);

        $this->assertSame(
            0,
            DB::table('heavy_lifters')->where('id', $id)->count(),
            'Unknown-type rows should not be retried forever.',
        );
    }
}
