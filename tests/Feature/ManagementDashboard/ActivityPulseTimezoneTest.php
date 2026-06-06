<?php

declare(strict_types=1);

namespace Tests\Feature\ManagementDashboard;

use App\Models\Activity;
use App\Services\Dashboard\Metrics\ActivityPulseMetric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The Overview "Today's Activities" feed must render times in the app timezone
 * (Asia/Karachi) — matching crm2's dashboard and crm3's own ActivityLogService.
 *
 * The feed's bulk rows are auto-logged events stored in GMT; reading them as
 * already-local rendered them 5 hours behind ("5 hours ago"). ActivityPulseMetric
 * now reads created_at as UTC and converts to the app timezone for display.
 * Display-only — no storage change, so crm2 is unaffected.
 */
class ActivityPulseTimezoneTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_feed_time_is_converted_from_utc_to_app_timezone(): void
    {
        // A GMT-stored payment activity at 14:30 UTC == 19:30 Asia/Karachi (+5).
        Activity::create([
            'account_id' => 1,
            'activity_type' => 'payment_received',
            'action' => 'received',
            'description' => 'Test payment',
            'amount' => 100,
            'created_at' => '2026-06-06 14:30:00',
        ]);

        $out = app(ActivityPulseMetric::class)->fetch(
            MetricScope::company(1),
            DateRange::fromStrings('2026-06-06', '2026-06-06'),
        );

        $this->assertNotEmpty($out['rows'], 'the notable payment activity must be in the feed');
        $this->assertSame(
            '2026-06-06 19:30:00',
            $out['rows'][0]['time'],
            'GMT-stored feed time must render +5 (Asia/Karachi); a 14:30 result is the no-convert bug.',
        );
    }
}
