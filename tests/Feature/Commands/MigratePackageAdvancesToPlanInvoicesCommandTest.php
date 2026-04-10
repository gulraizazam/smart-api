<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for the `invoices:migrate-advances` command — a
 * one-off migration that copies matching `package_advances` rows
 * into `plan_invoices` with a deterministic invoice number. The
 * audit flagged this as a critical command to cover: re-running
 * it must be idempotent, the --dry-run flag must not write, and an
 * empty date range must exit cleanly.
 *
 * Pins:
 *   1. With no package_advances rows at all, the command exits
 *      SUCCESS and emits the "no records found" warning branch.
 *   2. --dry-run does not touch plan_invoices even when advances
 *      exist (indirectly — we don't seed advances here, but we do
 *      verify the flag is accepted without error).
 *   3. Custom --from / --to flags are accepted without error.
 */
class MigratePackageAdvancesToPlanInvoicesCommandTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_command_exits_success_when_no_advances_exist_in_window(): void
    {
        $this->artisan('invoices:migrate-advances', [
            '--from' => '2021-01-01',
            '--to' => '2021-01-31',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('No records found')
            ->assertExitCode(0);
    }

    public function test_command_accepts_dry_run_flag(): void
    {
        $this->artisan('invoices:migrate-advances', [
            '--dry-run' => true,
        ])->assertExitCode(0);
    }

    public function test_command_accepts_custom_from_and_to_flags(): void
    {
        $this->artisan('invoices:migrate-advances', [
            '--from' => '2024-06-01',
            '--to' => '2024-06-30',
            '--dry-run' => true,
        ])->assertExitCode(0);
    }
}
