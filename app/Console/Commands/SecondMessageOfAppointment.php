<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\SMSLogs;
use App\Services\SMS\AppointmentSmsDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Send the day-before reminder SMS to patients whose appointment is
 * scheduled for tomorrow. Runs at 19:55 PKT.
 *
 * Honours the per-appointment `appointment_status_allow_message`
 * flag — once a status transitions to one that disables outgoing
 * messages (e.g. cancelled), the reminder is skipped even if the
 * scheduled date still matches.
 *
 * Dedups against same-day SMSLogs so a manual re-run within the
 * same day doesn't double-send.
 */
final class SecondMessageOfAppointment extends Command
{
    protected $signature = 'appointment:2nd-message-on-appointment-day';

    protected $description = 'Send the day-before reminder SMS to patients with appointments scheduled tomorrow.';

    private const LOG_TYPE = '2nd_sms';

    public function __construct(
        private readonly AppointmentSmsDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = Carbon::now()->toDateString();
        $tomorrow = Carbon::now()->addDay()->toDateString();

        $appointments = Appointments::query()
            ->join('users', 'users.id', '=', 'appointments.patient_id')
            ->where('appointments.scheduled_date', $tomorrow)
            ->where('appointments.base_appointment_status_id', 1)
            ->where('appointments.appointment_status_allow_message', 1)
            ->whereNull('appointments.coming_from')
            ->select(
                'appointments.id',
                'appointments.account_id',
                'appointments.appointment_type_id',
                'appointments.consultancy_type',
                'users.phone',
            )
            ->get();

        Log::info('appointment:2nd-message candidates', [
            'tomorrow' => $tomorrow,
            'count' => $appointments->count(),
        ]);

        $sent = 0;

        foreach ($appointments as $row) {
            if ($this->alreadySentToday($row, $today)) {
                continue;
            }

            try {
                // Hydrate a real Appointments instance so the
                // dispatcher's relationship reads + casts work
                // (e.g. resolving phone via patient relation).
                $appointment = Appointments::query()->find($row->id);
                if (! $appointment) {
                    continue;
                }

                $result = $this->dispatcher->dispatchReminderMessage($appointment);
                if ($result['sent']) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                Log::error('appointment:2nd-message unexpected error', [
                    'appointment_id' => $row->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        $this->info("Reminder SMS dispatched: {$sent} / {$appointments->count()}");

        return self::SUCCESS;
    }

    private function alreadySentToday(object $row, string $today): bool
    {
        return SMSLogs::query()
            ->where('appointment_id', $row->id)
            ->where('log_type', self::LOG_TYPE)
            ->where('to', GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($row->phone)))
            ->whereDate('created_at', $today)
            ->exists();
    }
}
