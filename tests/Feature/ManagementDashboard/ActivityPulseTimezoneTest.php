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
 * `activities.created_at` is written by every ActivityLogger writer via now()
 * (app-timezone wall-clock) and the DB connection applies no UTC conversion, so
 * the stored value is ALREADY local. The dashboard must therefore read it as
 * local and NOT convert from UTC — doing so double-applied a +5h offset and
 * pushed fresh rows into the future ("4 hours from now" on just-recorded
 * feedback). Display-only — no storage change, so crm2 is unaffected.
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

    public function test_feed_time_is_read_as_local_not_converted_from_utc(): void
    {
        // Stored value is local wall-clock time (how ActivityLogger writes it).
        // It must render unchanged — re-interpreting it as UTC and adding +5h
        // is the bug that put fresh activities in the future.
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
            '2026-06-06 14:30:00',
            $out['rows'][0]['time'],
            'locally-stored feed time must render unchanged; a 19:30 (+5) result is the double-offset bug.',
        );
    }

    public function test_just_recorded_activity_is_not_in_the_future(): void
    {
        // Reproduces the reported bug: feedback logged "now" via now() must read
        // as the present, never "X hours from now".
        Activity::create([
            'account_id' => 1,
            'activity_type' => 'feedback_added',
            'action' => 'Feedback Added',
            'description' => 'Test feedback',
            'created_at' => now(),
        ]);

        $out = app(ActivityPulseMetric::class)->fetch(
            MetricScope::company(1),
            DateRange::fromStrings(now()->format('Y-m-d'), now()->format('Y-m-d')),
        );

        $this->assertNotEmpty($out['rows'], 'the just-recorded feedback must be in the feed');
        $this->assertStringNotContainsString(
            'from now',
            (string) $out['rows'][0]['time_for_humans'],
            'a just-recorded activity must not render as being in the future.',
        );
    }
}
