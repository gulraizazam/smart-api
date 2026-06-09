<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Http\Resources\Plan\PlanLogResource;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Pins that plan-log timestamps are emitted in PKT (+05:00) — the same offset
 * every other API resource uses — rather than being force-shifted to UTC with
 * `->utc()`. The stored value is already PKT wall-clock (app tz = Asia/Karachi,
 * no DB UTC conversion), so `->utc()` was relabelling AND shifting it −5h.
 */
class PlanLogResourceTest extends TestCase
{
    public function test_timestamps_are_emitted_in_pkt_not_utc(): void
    {
        $resource = new PlanLogResource((object) [
            'created_at_orignal' => '2026-06-09 15:30:00',
            'updated_at_orignal' => '2026-06-09 16:45:00',
        ]);

        $arr = $resource->toArray(new Request());

        // PKT wall-clock + +05:00 offset — NOT the UTC shift (10:30:00+00:00).
        $this->assertSame('2026-06-09T15:30:00+05:00', $arr['created_at']);
        $this->assertSame('2026-06-09T16:45:00+05:00', $arr['updated_at']);
    }

    public function test_missing_timestamps_stay_null(): void
    {
        $resource = new PlanLogResource((object) ['id' => 7]);

        $arr = $resource->toArray(new Request());

        $this->assertNull($arr['created_at']);
        $this->assertNull($arr['updated_at']);
    }
}
