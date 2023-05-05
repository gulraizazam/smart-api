<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointments;
use App\Models\AppointmentTypes;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentsDailyStats;
use App\Models\Locations;
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
        $locations = Locations::whereActive(1)->get()->pluck('id');
        foreach($locations as $location){
            $appointments = Appointments::where(['location_id' => $location, 'scheduled_date' => Carbon::now()->format("Y-m-d"), 'appointment_type_id' => $consultancyslug])->select('id', 'location_id', 'base_appointment_status_id', 'created_by')->get();
            if(!$appointments->isEmpty()){
                foreach ($appointments as $appointment){
                    AppointmentsDailyStats::updateOrCreate(['appointment_id' => $appointment->id, 'created_at' => Carbon::now()->format("Y-m-d")],
                    ['centre_id' => $appointment->location_id,
                    'user_id' => $appointment->created_by,
                    'appointment_id' => $appointment->id,
                    'appointment_status_id' => $appointment->base_appointment_status_id,
                    'cron_current_date' => Carbon::now()]);
                }
            }
        }
        return 0;
    }
}
