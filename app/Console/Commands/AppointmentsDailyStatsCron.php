<?php

namespace App\Console\Commands;

use App\Models\Appointments;
use App\Models\AppointmentsDailyStats;
use App\Models\AppointmentTypes;
use App\Models\Locations;
use Carbon\Carbon;
use Illuminate\Console\Command;

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
        $today = Carbon::now()->format('Y-m-d');
       // $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
        foreach ($locations as $location) {
            $appointments = Appointments::where(function ($query) use ($location,$consultancyslug, $today) {
                $query->where([
                        ['location_id',$location],
                        ['appointment_type_id', $consultancyslug]
                    ])
                    ->where('scheduled_date','2023-07-03');
            })
            ->select('id', 'location_id', 'base_appointment_status_id', 'created_by')
            ->get();
            if(count($appointments) > 0) {
                foreach ($appointments as $appointment) {
                    AppointmentsDailyStats::Create(
                        [
                            'centre_id' =>$appointment->location_id,
                            'user_id' => $appointment->created_by,
                            'appointment_id' => $appointment->id,
                            'appointment_status_id' => $appointment->base_appointment_status_id,
                            'cron_current_date' => Carbon::now(),
                        ]);
                }
            }
        }

        return 0;
    }
}
