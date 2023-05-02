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
        $consultationscheduled = Appointments::where(['scheduled_date' => Carbon::now()->format("Y-m-d"), 'base_appointment_status_id' => 1, 'appointment_type_id' => $consultancyslug])->count();
        $consultationarrived = Appointments::where(['scheduled_date' => Carbon::now()->format("Y-m-d"), 'base_appointment_status_id' => 2, 'appointment_type_id' => $consultancyslug])->count();
        $appointmentdailystats = AppointmentsDailyStats::create(['consultation_scheduled_count' => $consultationscheduled, 'consultation_arrived_count' => $consultationarrived, 'cron_current_date' => Carbon::now()]);
        return 0;
    }
}
