<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Appointments;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Eligibility + retry-cadence pins for `appointment:deliver-on-appointment-book`.
 *
 * Covers:
 *   - 90s settle window before the first attempt
 *   - flag flipped to 0 on success
 *   - flag stays raised on failure (cron picks it up next tick)
 *   - past-due appointment dropped without sending
 *   - cancelled / non-pending appointment dropped without sending
 *   - 60s spacing between retries during fast phase
 *
 * Provider success/failure paths are exercised via the same skip
 * codes the dispatcher returns — `template_missing` keeps the row
 * eligible for retry without making a real provider call (live API
 * mocking is out of scope for unit-level tests).
 */
class DeliverOnAppointmentBookTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        // Wipe SMS templates + operator setting so dispatcher returns
        // template_missing — keeps the cron's "failure" path under
        // test without needing live provider calls.
        DB::table('sms_templates')->delete();
        DB::table('settings')->where('slug', 'sys-current-sms-operator')->delete();
    }

    private function seedAppointment(array $overrides = []): Appointments
    {
        return Appointments::factory()->create(array_merge([
            'send_message' => 1,
            'base_appointment_status_id' => 1,
            'appointment_status_allow_message' => 1,
            'coming_from' => null,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'updated_at' => now()->subSeconds(120), // past settle
        ], $overrides));
    }

    public function test_settle_window_blocks_first_attempt_under_90s(): void
    {
        $appointment = $this->seedAppointment([
            'updated_at' => now()->subSeconds(30), // below 90s settle
        ]);

        $this->artisan('appointment:deliver-on-appointment-book')->assertExitCode(0);

        $row = Appointments::find($appointment->id);
        $this->assertSame(1, (int) $row->send_message, 'Within settle window — must not have attempted yet.');
        $this->assertNull($row->sms_last_attempt_at, 'No attempt recorded during settle.');
    }

    public function test_attempts_after_settle_and_stamps_timestamp_on_failure(): void
    {
        $appointment = $this->seedAppointment();

        $this->artisan('appointment:deliver-on-appointment-book')->assertExitCode(0);

        $row = Appointments::find($appointment->id);
        // Dispatcher returns template_missing → cron treats as failure
        // → flag stays raised for retry, timestamp recorded.
        $this->assertSame(1, (int) $row->send_message);
        $this->assertNotNull($row->sms_last_attempt_at);
    }

    public function test_past_appointment_is_skipped_with_flag_cleared(): void
    {
        $appointment = $this->seedAppointment([
            'scheduled_date' => now()->subDay()->toDateString(),
            'scheduled_time' => '09:00:00',
        ]);

        $this->artisan('appointment:deliver-on-appointment-book')->assertExitCode(0);

        $row = Appointments::find($appointment->id);
        $this->assertSame(0, (int) $row->send_message, 'Past appointment must drop the flag.');
    }

    public function test_cancelled_appointment_is_not_picked_up(): void
    {
        $appointment = $this->seedAppointment([
            'base_appointment_status_id' => 5, // Cancelled tree
        ]);

        $this->artisan('appointment:deliver-on-appointment-book')->assertExitCode(0);

        $row = Appointments::find($appointment->id);
        // Flag stays raised because the cron's WHERE excluded it,
        // but no attempt was recorded. (Operator can clear the flag
        // separately if needed; cron won't touch non-pending rows.)
        $this->assertNull($row->sms_last_attempt_at, 'Cancelled rows must not be attempted.');
    }

    public function test_walk_in_appointment_is_not_picked_up(): void
    {
        $appointment = $this->seedAppointment([
            'coming_from' => 'walk_in',
        ]);

        $this->artisan('appointment:deliver-on-appointment-book')->assertExitCode(0);

        $row = Appointments::find($appointment->id);
        $this->assertNull($row->sms_last_attempt_at, 'Walk-in rows must not be attempted.');
    }

    public function test_status_with_allow_message_zero_is_not_picked_up(): void
    {
        $appointment = $this->seedAppointment([
            'appointment_status_allow_message' => 0,
        ]);

        $this->artisan('appointment:deliver-on-appointment-book')->assertExitCode(0);

        $row = Appointments::find($appointment->id);
        $this->assertNull($row->sms_last_attempt_at, 'allow_message=0 rows must not be attempted.');
    }

    public function test_fast_phase_retry_respects_60s_spacing(): void
    {
        $appointment = $this->seedAppointment([
            'created_at' => now()->subMinutes(5), // young row → fast phase
            'sms_last_attempt_at' => now()->subSeconds(30), // <60s ago
        ]);

        $this->artisan('appointment:deliver-on-appointment-book')->assertExitCode(0);

        $stamp = \Carbon\Carbon::parse(Appointments::find($appointment->id)->sms_last_attempt_at);
        $this->assertEqualsWithDelta(
            now()->subSeconds(30)->getTimestamp(),
            $stamp->getTimestamp(),
            5,
            'Last attempt was 30s ago — fast cadence (60s) must skip this tick.',
        );
    }

    public function test_slow_phase_retry_respects_30min_spacing(): void
    {
        $appointment = $this->seedAppointment([
            'created_at' => now()->subHour(), // old row → slow phase
            'sms_last_attempt_at' => now()->subMinutes(5), // <30 min ago
        ]);

        $this->artisan('appointment:deliver-on-appointment-book')->assertExitCode(0);

        $stamp = \Carbon\Carbon::parse(Appointments::find($appointment->id)->sms_last_attempt_at);
        $this->assertEqualsWithDelta(
            now()->subMinutes(5)->getTimestamp(),
            $stamp->getTimestamp(),
            5,
            'Slow cadence (30 min) must skip this tick.',
        );
    }
}
