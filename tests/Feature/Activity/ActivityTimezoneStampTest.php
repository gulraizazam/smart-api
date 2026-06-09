<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use App\Models\Activity;
use Illuminate\Support\Carbon;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins that an Activity created WITHOUT created_at/updated_at is stamped with
 * the PKT business time at the source (the model `booted()` hook), rather than
 * falling to the DB-default `current_timestamp()` — which is UTC on the prod
 * box and left the activity feed 5h behind. Mirrors the three auto-loggers
 * (ActivityLogObserver / AuthActivityListener / PermissionActivityListener)
 * that call Activity::create() without a timestamp.
 */
class ActivityTimezoneStampTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures(); // provides account_id = 1 (the FK target)
        // Freeze app "now" (Asia/Karachi wall-clock). The DB's current_timestamp()
        // default is NOT frozen, so a missing hook would store the real clock
        // (UTC), never this exact frozen PKT instant.
        Carbon::setTestNow('2026-06-09 15:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_activity_omitting_created_at_is_stamped_pkt_at_the_source(): void
    {
        $activity = Activity::create([
            'account_id' => 1,
            'action' => 'create',
            'description' => 'tz hook test',
            // created_at / updated_at intentionally omitted.
        ]);

        // The hook stamped the frozen PKT instant on the model…
        $this->assertSame('2026-06-09 15:30:00', (string) $activity->created_at);
        $this->assertSame('2026-06-09 15:30:00', (string) $activity->updated_at);

        // …and persisted it (proving the app value won over the UTC DB default).
        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'created_at' => '2026-06-09 15:30:00',
        ]);
    }

    public function test_explicit_created_at_is_preserved(): void
    {
        // A writer that DOES set created_at must keep its value (hook only fills gaps).
        $activity = Activity::create([
            'account_id' => 1,
            'action' => 'create',
            'description' => 'explicit ts',
            'created_at' => '2026-06-01 09:00:00',
        ]);

        $this->assertSame('2026-06-01 09:00:00', (string) $activity->created_at);
    }
}
