<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointments;
use App\Models\AppointmentTypes;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentsDailyStats;
use Carbon\Carbon;

class AppointmentsDailyStatsCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:daily-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Appointment and Treatment daily sate created';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $consultancyslug = AppointmentTypes::where(['slug' => 'consultancy'])->first()->id;
        $treatmentslug = AppointmentTypes::where(['slug' => 'treatment'])->first()->id;
        $appointmentpending = AppointmentStatuses::where(['name' => 'Pending'])->first()->id;
        $appointmentarrived = AppointmentStatuses::where(['name' => 'Arrived'])->first()->id;
        $consultationscheduled = Appointments::where(['scheduled_date' => Carbon::now()->format("Y-m-d"), 'appointment_status_id' => $appointmentpending, 'appointment_type_id' => $consultancyslug])->count();
        $consultationarrived = Appointments::where(['scheduled_date' => Carbon::now()->format("Y-m-d"), 'appointment_status_id' => $appointmentarrived, 'appointment_type_id' => $consultancyslug])->count();
        // Treatment
        $treatmentscheduled = Appointments::where(['scheduled_date' => Carbon::now()->format("Y-m-d"), 'appointment_status_id' => $appointmentpending, 'appointment_type_id' => $treatmentslug])->count();
        $treatmentarrived = Appointments::where(['scheduled_date' => Carbon::now()->format("Y-m-d"), 'appointment_status_id' => $appointmentarrived, 'appointment_type_id' => $treatmentslug])->count();
        $appointmentdailystats = AppointmentsDailyStats::create(['consultation_scheduled_count' => $consultationscheduled, 'consultation_arrived_count' => $consultationarrived, 'treatment_scheduled_count' => $treatmentscheduled, 'treatment_arrived_count' => $treatmentarrived, 'cron_current_date' => Carbon::now()]);
        return 0;
    }
}
