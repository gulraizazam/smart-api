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
 * The Overview "Today's Activities" feed renders times in Asia/Karachi.
 *
 * Storage convention (shared by crm2 + crm3): activity timestamps are kept in
 * UTC and read back as UTC→Asia/Karachi for display. A writer supplies the PKT
 * wall-clock it observed; the Activity model normalises it to UTC on write;
 * ActivityPulseMetric converts it back to PKT. This pins that round-trip — a
 * no-convert read would surface the stored UTC value, 5 hours early.
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

    public function test_feed_time_round_trips_pkt_through_utc_storage(): void
    {
        // Writer logs a payment at 7:30 PM PKT (the wall-clock it observed).
        Activity::create([
            'account_id' => 1,
            'activity_type' => 'payment_received',
            'action' => 'received',
            'description' => 'Test payment',
            'amount' => 100,
            'created_at' => '2026-06-06 19:30:00',
        ]);

        // The model hook normalised it to UTC on write (7:30 PM PKT → 2:30 PM UTC).
        $this->assertDatabaseHas('activities', [
            'activity_type' => 'payment_received',
            'created_at' => '2026-06-06 14:30:00',
        ]);

        $out = app(ActivityPulseMetric::class)->fetch(
            MetricScope::company(1),
            DateRange::fromStrings('2026-06-06', '2026-06-06'),
        );

        $this->assertNotEmpty($out['rows'], 'the notable payment activity must be in the feed');
        // …and the feed converts the stored UTC (14:30) back to the original
        // 7:30 PM PKT. A 14:30 result is the no-convert bug that showed every
        // fresh activity "5 hours ago".
        $this->assertSame(
            '2026-06-06 19:30:00',
            $out['rows'][0]['time'],
            'Stored UTC (14:30) must render +5 as Asia/Karachi (19:30); 14:30 is the no-convert bug.',
        );
    }
}
