<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Appointments;
use App\Services\SMS\AppointmentSmsDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Send the booking-confirmation SMS for any appointment whose
 * `send_message=1` flag is still raised. Runs every minute.
 *
 * Eligibility (the "pending" gate):
 *   - status is in the Booked tree (`base_appointment_status_id = 1`)
 *   - current sub-status allows messaging (`appointment_status_allow_message = 1`)
 *   - not soft-deleted, not a walk-in (`coming_from IS NULL`)
 *   - the appointment is still in the future (combined date+time > now)
 *
 * Retry policy:
 *   - First attempt waits a 90s settle window after `updated_at`,
 *     giving the operator a brief grace period to fix mistakes.
 *   - On provider failure the row stays flagged; cron retries.
 *   - Cadence: every minute for the first 30 minutes after booking,
 *     then every 30 minutes thereafter. Bounded by the appointment
 *     time itself — once that passes, the row is dropped (flag flipped,
 *     "skipped — past" logged) without another send attempt.
 *   - No fixed attempt cap: outages of 4 hours still recover; a bad
 *     phone number for a 2-day-out booking gets ~30 fast retries
 *     plus ~96 slow retries instead of 2,880.
 */
final class DeliverOnAppointmentBook extends Command
{
    protected $signature = 'appointment:deliver-on-appointment-book';

    protected $description = 'Deliver booking-confirmation SMS for queued appointments (retries until the appointment time passes).';

    private const BATCH_SIZE = 200;
    private const SETTLE_SECONDS = 90;
    private const FAST_PHASE_MINUTES = 30;
    private const FAST_RETRY_SECONDS = 60;
    private const SLOW_RETRY_SECONDS = 1800;

    public function __construct(
        private readonly AppointmentSmsDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = Carbon::now();
        $nowDb = $now->format('Y-m-d H:i:s');

        $expired = $this->skipPastAppointments($nowDb);
        if ($expired > 0) {
            Log::info('appointment:deliver-on-appointment-book skipped past-due', [
                'count' => $expired,
            ]);
        }

        $appointments = $this->dueForSend($now)->get();
        if ($appointments->isEmpty()) {
            return self::SUCCESS;
        }

        if ($appointments->count() === self::BATCH_SIZE) {
            Log::info('appointment:deliver-on-appointment-book hit batch cap', [
                'cap' => self::BATCH_SIZE,
            ]);
        }

        $sent = 0;
        $failed = 0;
        foreach ($appointments as $appointment) {
            try {
                $result = $this->dispatcher->dispatchBookingMessage($appointment);

                if ($result['sent']) {
                    Appointments::where('id', $appointment->id)->update([
                        'send_message' => 0,
                        'msg_count' => 1,
                        'sms_last_attempt_at' => $now,
                    ]);
                    $sent++;
                } else {
                    // Provider / template / settings failure — leave
                    // the flag raised, stamp the attempt timestamp so
                    // the cadence logic spaces the next retry.
                    Appointments::where('id', $appointment->id)->update([
                        'sms_last_attempt_at' => $now,
                    ]);
                    $failed++;
                }
            } catch (\Throwable $e) {
                Log::error('appointment:deliver-on-appointment-book unexpected error', [
                    'appointment_id' => $appointment->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                Appointments::where('id', $appointment->id)->update([
                    'sms_last_attempt_at' => $now,
                ]);
                $failed++;
            }
        }

        $this->info("Booking SMS — sent: {$sent}, retry-pending: {$failed}, expired: {$expired}");

        return self::SUCCESS;
    }

    /**
     * Mark any flagged-but-now-past appointments as done. Once the
     * scheduled time has elapsed the SMS would arrive late and is
     * worse than no SMS — skip cleanly and log the count.
     */
    private function skipPastAppointments(string $nowDb): int
    {
        return Appointments::query()
            ->where('send_message', 1)
            ->whereRaw(
                "CONCAT(scheduled_date, ' ', COALESCE(scheduled_time, '23:59:59')) <= ?",
                [$nowDb],
            )
            ->update([
                'send_message' => 0,
                'sms_last_attempt_at' => DB::raw('COALESCE(sms_last_attempt_at, NOW())'),
            ]);
    }

    /**
     * Build the eligibility query: pending status, future appointment,
     * cadence-due (90s settle for first attempt; fast then slow
     * retries thereafter).
     */
    private function dueForSend(Carbon $now)
    {
        $nowDb = $now->format('Y-m-d H:i:s');
        $settleCutoff = $now->copy()->subSeconds(self::SETTLE_SECONDS)->format('Y-m-d H:i:s');
        $fastPhaseCutoff = $now->copy()->subMinutes(self::FAST_PHASE_MINUTES)->format('Y-m-d H:i:s');
        $fastRetryCutoff = $now->copy()->subSeconds(self::FAST_RETRY_SECONDS)->format('Y-m-d H:i:s');
        $slowRetryCutoff = $now->copy()->subSeconds(self::SLOW_RETRY_SECONDS)->format('Y-m-d H:i:s');

        return Appointments::query()
            ->where('send_message', 1)
            ->where('base_appointment_status_id', 1)
            ->where('appointment_status_allow_message', 1)
            ->whereNull('coming_from')
            ->whereRaw(
                "CONCAT(scheduled_date, ' ', COALESCE(scheduled_time, '23:59:59')) > ?",
                [$nowDb],
            )
            ->where(function ($q) use ($settleCutoff, $fastPhaseCutoff, $fastRetryCutoff, $slowRetryCutoff): void {
                // First attempt: row was last touched ≥90s ago and
                // has never been attempted.
                $q->where(function ($q1) use ($settleCutoff): void {
                    $q1->whereNull('sms_last_attempt_at')
                       ->where('updated_at', '<=', $settleCutoff);
                })
                // Fast retry: row is <30 min old, last attempt ≥60s ago.
                ->orWhere(function ($q1) use ($fastPhaseCutoff, $fastRetryCutoff): void {
                    $q1->whereNotNull('sms_last_attempt_at')
                       ->where('created_at', '>', $fastPhaseCutoff)
                       ->where('sms_last_attempt_at', '<=', $fastRetryCutoff);
                })
                // Slow retry: row is ≥30 min old, last attempt ≥30 min ago.
                ->orWhere(function ($q1) use ($fastPhaseCutoff, $slowRetryCutoff): void {
                    $q1->whereNotNull('sms_last_attempt_at')
                       ->where('created_at', '<=', $fastPhaseCutoff)
                       ->where('sms_last_attempt_at', '<=', $slowRetryCutoff);
                });
            })
            ->limit(self::BATCH_SIZE);
    }
}
