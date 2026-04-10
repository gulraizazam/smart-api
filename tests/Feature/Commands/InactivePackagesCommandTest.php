<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for the `bundles:inactive` command — the nightly
 * cron that flips the `active` flag to 0 on any bundle whose
 * `end` date has already passed.
 *
 * Pins:
 *   1. Command runs cleanly against an empty fixture.
 *   2. A bundle whose end date is yesterday gets deactivated.
 *   3. A bundle whose end date is in the future is left alone.
 */
class InactivePackagesCommandTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    private function seedBundle(string $endDate, int $active): int
    {
        return (int) DB::table('bundles')->insertGetId([
            'name' => 'Smoke Bundle ' . uniqid(),
            'price' => 1000.00,
            'services_price' => 1000.00,
            'total_services' => 1,
            'apply_discount' => 0,
            'type' => 'multiple',
            'start' => now()->subMonth()->format('Y-m-d'),
            'end' => $endDate,
            'active' => $active,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_command_runs_clean_on_empty_fixture(): void
    {
        $this->artisan('bundles:inactive')->assertExitCode(0);
    }

    public function test_command_deactivates_bundles_past_end_date(): void
    {
        $expiredId = $this->seedBundle(now()->subDays(3)->format('Y-m-d'), 1);

        $this->artisan('bundles:inactive')->assertExitCode(0);

        $this->assertSame(
            0,
            (int) DB::table('bundles')->where('id', $expiredId)->value('active'),
        );
    }

    public function test_command_leaves_future_bundle_untouched(): void
    {
        $futureId = $this->seedBundle(now()->addWeek()->format('Y-m-d'), 1);

        $this->artisan('bundles:inactive')->assertExitCode(0);

        $this->assertSame(
            1,
            (int) DB::table('bundles')->where('id', $futureId)->value('active'),
        );
    }
}
