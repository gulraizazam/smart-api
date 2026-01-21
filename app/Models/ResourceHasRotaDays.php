<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB as FacadesDB;

class ResourceHasRotaDays extends Model
{
    use SoftDeletes;

    protected $fillable = ['date', 'start_time', 'end_time', 'start_off', 'end_off', 'start_timestamp', 'end_timestamp', 'active', 'resource_has_rota_id', 'created_at', 'updated_at'];

    protected static $_fillable = ['date', 'start_time', 'end_time', 'active', 'resource_has_rota_id'];

    protected $table = 'resource_has_rota_days';

    protected static $_table = 'resource_has_rota_days';

    /*Create Resource has Rota days
     *
     * @param
     *
     * */

    public static function createRotaDaysRecord($request, $resourcerota, $week, $data)
    {

        $parent_id = $resourcerota->id;
        /*
        * Use to Store Data in resource has rota days.
        * */

        $start = Carbon::parse($request->start);
        $end = Carbon::parse($request->end);
        /*
         * It gives difference
         * */
        $diffDays = $end->diffInDays($start);

        $days = [];

        $days[0] = [
            'date' => $start->format('Y-m-d'),
        ];

        if ($resourcerota->copy_all == '1') {
            foreach ($week as $week_day) {
                $data['time_f_'.$week_day] = $data['time_f_monday'];
                $data['time_to_'.$week_day] = $data['time_to_monday'];
                if ($data['break_from_monday'] && $data['break_to_monday']) {
                    $data['break_from_'.$week_day] = $data['break_from_monday'];
                    $data['break_to_'.$week_day] = $data['break_to_monday'];
                } else {
                    $data['break_from_'.$week_day] = null;
                    $data['break_to_'.$week_day] = null;
                }
            }
        }
        foreach ($week as $week_day) {

            if ($week_day == strtolower($start->format('l'))) {

                if ($data[$week_day] != null) {
                    $days[0]['start'] = $data['time_f_'.$week_day];
                    $days[0]['end'] = $data['time_to_'.$week_day];
                    if ($data['break_from_'.$week_day] && $data['break_to_'.$week_day]) {
                        $days[0]['start_off'] = $data['break_from_'.$week_day];
                        $days[0]['end_off'] = $data['break_to_'.$week_day];
                    } else {
                        $days[0]['start_off'] = null;
                        $days[0]['end_off'] = null;
                    }
                    $days[0]['resource_has_rota_id'] = $resourcerota->id;

                } else {
                    $days[0]['start'] = null;
                    $days[0]['end'] = null;
                    $days[0]['start_off'] = null;
                    $days[0]['end_off'] = null;
                    $days[0]['resource_has_rota_id'] = $resourcerota->id;
                }

            }
        }

        for ($day = 1; $day <= $diffDays; $day++) {

            $day_looper = $start;

            $day_looper->addDay(1);

            $days[$day] = [
                'date' => $day_looper->format('Y-m-d'),
            ];

            foreach ($week as $week_day) {
                if ($week_day == strtolower($day_looper->format('l'))) {
                    if ($data[$week_day] != null) {
                        $days[$day]['start'] = $data['time_f_'.$week_day];
                        $days[$day]['end'] = $data['time_to_'.$week_day];
                        if ($data['break_from_'.$week_day] && $data['break_to_'.$week_day]) {
                            $days[$day]['start_off'] = $data['break_from_'.$week_day];
                            $days[$day]['end_off'] = $data['break_to_'.$week_day];
                        } else {
                            $days[$day]['start_off'] = null;
                            $days[$day]['end_off'] = null;
                        }

                        $days[$day]['resource_has_rota_id'] = $resourcerota->id;
                    } else {
                        $days[$day]['start'] = null;
                        $days[$day]['end'] = null;
                        $days[$day]['start_off'] = null;
                        $days[$day]['end_off'] = null;
                        $days[$day]['resource_has_rota_id'] = $resourcerota->id;
                    }
                }
            }
        }
        /*
         * Code for saving resource has rota days
        * */

        foreach ($days as $day) {

            $data_days['date'] = $day['date'];
            $data_days['start_time'] = $day['start'];
            $data_days['end_time'] = $day['end'];
            $data_days['start_off'] = $day['start_off'];
            $data_days['end_off'] = $day['end_off'];
            $data_days['start_timestamp'] = Carbon::parse($day['date'].' '.$day['start'])->format('Y-m-d H:i').':00';
            $data_days['end_timestamp'] = Carbon::parse($day['date'].' '.$day['end'])->format('Y-m-d H:i').':00';
            $data_days['resource_has_rota_id'] = $day['resource_has_rota_id'];
            $record = self::create($data_days);

            AuditTrails::addEventLogger(self::$_table, 'create', $data_days, self::$_fillable, $record, $parent_id);
        }

        return $record;
    }

    /*Create Resource has Rota days
    *
    * @param
    *
    * */

    public static function updateRotaDaysRecord($request, $week, $data, $resourcerota)
    {

        $old_data = '0';
        $parent_id = $resourcerota->id;

        $start = Carbon::parse($request->start);
        $end = Carbon::parse($request->end);
        /*
         * It gives difference
         * */
        $diffDays = $end->diffInDays($start);

        $days = [];

        $days[0] = [
            'date' => $start->format('Y-m-d'),
        ];

        if ($resourcerota->copy_all == '1') {
            foreach ($week as $week_day) {
                $data['time_f_'.$week_day] = $data['time_f_monday'];
                $data['time_to_'.$week_day] = $data['time_to_monday'];

                if ($data['break_from_monday'] && $data['break_to_monday']) {
                    $data['break_from_'.$week_day] = $data['break_from_monday'];
                    $data['break_to_'.$week_day] = $data['break_to_monday'];
                } else {
                    $data['break_from_'.$week_day] = null;
                    $data['break_to_'.$week_day] = null;
                }
            }
        }

        foreach ($week as $week_day) {

            if ($week_day == strtolower($start->format('l'))) {

                if ($data[$week_day] != null) {
                    $days[0]['start'] = $data['time_f_'.$week_day];
                    $days[0]['end'] = $data['time_to_'.$week_day];
                    if ($data['break_from_'.$week_day] && $data['break_to_'.$week_day]) {
                        $days[0]['start_off'] = $data['break_from_'.$week_day];
                        $days[0]['end_off'] = $data['break_to_'.$week_day];
                    } else {
                        $days[0]['start_off'] = null;
                        $days[0]['end_off'] = null;
                    }
                    $days[0]['resource_has_rota_id'] = $resourcerota->id;

                } else {
                    $days[0]['start'] = null;
                    $days[0]['end'] = null;
                    $days[0]['start_off'] = null;
                    $days[0]['end_off'] = null;
                    $days[0]['resource_has_rota_id'] = $resourcerota->id;
                }
            }

        }

        //echo $start->format('l') . '<br/>';
        $last_date = null;

        for ($day = 1; $day <= $diffDays; $day++) {

            $day_looper = $start;

            $day_looper->addDay(1);

            //echo $day_looper->format('l') . '<br/>';

            $days[$day] = [
                'date' => $day_looper->format('Y-m-d'),
            ];

            if ($day == $diffDays) {
                $last_date = $day_looper->format('Y-m-d');
            }

            foreach ($week as $week_day) {
                if ($week_day == strtolower($day_looper->format('l'))) {
                    if ($data[$week_day] != null) {
                        $days[$day]['start'] = $data['time_f_'.$week_day];
                        $days[$day]['end'] = $data['time_to_'.$week_day];
                        if ($data['break_from_'.$week_day] && $data['break_to_'.$week_day]) {
                            $days[$day]['start_off'] = $data['break_from_'.$week_day];
                            $days[$day]['end_off'] = $data['break_to_'.$week_day];
                        } else {
                            $days[$day]['start_off'] = null;
                            $days[$day]['end_off'] = null;
                        }
                        $days[$day]['resource_has_rota_id'] = $resourcerota->id;
                    } else {
                        $days[$day]['start'] = null;
                        $days[$day]['end'] = null;
                        $days[$day]['start_off'] = null;
                        $days[$day]['end_off'] = null;
                        $days[$day]['resource_has_rota_id'] = $resourcerota->id;
                    }
                }
            }
        }

        /*
         * Code for saving resource has rota days
        * */

        foreach ($days as $day) {
            /*first get the */
            $data_days['date'] = $day['date'];
            $data_days['start_time'] = $day['start'];
            $data_days['end_time'] = $day['end'];
            $data_days['start_off'] = $day['start_off'];
            $data_days['end_off'] = $day['end_off'];
            $data_days['start_timestamp'] = Carbon::parse($day['date'].' '.$day['start'])->format('Y-m-d H:i').':00';
            $data_days['end_timestamp'] = Carbon::parse($day['date'].' '.$day['end'])->format('Y-m-d H:i').':00';
            $data_days['resource_has_rota_id'] = $day['resource_has_rota_id'];

            $record = self::where([
                'date' => $day['date'],
                'resource_has_rota_id' => $day['resource_has_rota_id'],
            ])->first();
            if ($record) {
                $record->update($data_days);
            } else {
                $record = self::create($data_days);
            }

            AuditTrails::editEventLogger(self::$_table, 'Edit', $data_days, self::$_fillable, $old_data, $record, $parent_id);
        }

        // forcefully delete those records which are beyond end date
        if ($last_date) {
            self::where('resource_has_rota_id', '=', $resourcerota->id)
                ->where('date', '>', $last_date)
                ->forceDelete();
        }

        return $record;
    }

    /*
     * function to grab the rota Days Appointment
     * */
    public static function grabRotaDaysAppointments($request, $rota_days, $resourcerota)
    {
        FacadesDB::enableQuerylog();
        $ids = [];
        $appointments = [];

        if (count($rota_days)) {
            foreach ($rota_days as $rota_day) {
                $ids[] = $rota_day['id'];
            }

            if ($resourcerota->resource_type_id == 1) {
                $appointments = Appointments::whereNotNull('scheduled_date')
                    ->whereNotNull('scheduled_time')
                    ->whereIn('resource_has_rota_day_id_for_machine', $ids)
                    ->select('id', 'scheduled_date', 'scheduled_time')
                    ->get();
            } else {
                $appointments = Appointments::whereNotNull('scheduled_date')
                    ->whereNotNull('scheduled_time')
                    ->whereIn('resource_has_rota_day_id', $ids)
                    ->select('id', 'scheduled_date', 'scheduled_time')
                    ->get();
            }

            if ($appointments->count()) {
                $appointments = $appointments->toArray();
            } else {
                $appointments = [];
            }

        }

        return $appointments;
    }

    /*
     * function to check the Rota Days Mapping
     * */
    public static function grabRotaDaysMapping($request, $week, $data, $resourcerota)
    {

        $start = Carbon::parse($request->start);
        $end = Carbon::parse($request->end);

        /*
         * It gives difference
         * */
        $diffDays = $end->diffInDays($start);

        $days = [];

        $days[0] = [
            'date' => $start->format('Y-m-d'),
        ];

        if ($resourcerota->copy_all == '1') {

            foreach ($week as $week_day) {
                $data['time_f_'.$week_day] = $data['time_f_monday'];
                $data['time_to_'.$week_day] = $data['time_to_monday'];

                if ($data['break_from_monday'] && $data['break_to_monday']) {
                    $data['break_from_'.$week_day] = $data['break_from_monday'];
                    $data['break_to_'.$week_day] = $data['break_to_monday'];
                } else {
                    $data['break_from_'.$week_day] = null;
                    $data['break_to_'.$week_day] = null;
                }
            }
        }

        foreach ($week as $week_day) {
            if ($week_day == strtolower($start->format('l'))) {

                if ($data[$week_day] != null) {
                    if (isset($data['time_f_'.$week_day]) && isset($data['time_to_'.$week_day])) {
                        $days[0]['start'] = $data['time_f_'.$week_day];
                        $days[0]['end'] = $data['time_to_'.$week_day];
                    } else {
                        $days[0]['start'] = null;
                        $days[0]['end'] = null;
                    }
                    if (isset($data['break_from_'.$week_day]) && isset($data['break_to_'.$week_day])) {
                        $days[0]['start_off'] = $data['break_from_'.$week_day];
                        $days[0]['end_off'] = $data['break_to_'.$week_day];
                    } else {
                        $days[0]['start_off'] = null;
                        $days[0]['end_off'] = null;
                    }
                    $days[0]['resource_has_rota_id'] = $resourcerota->id;
                } else {
                    $days[0]['start'] = null;
                    $days[0]['end'] = null;
                    $days[0]['start_off'] = null;
                    $days[0]['end_off'] = null;
                    $days[0]['resource_has_rota_id'] = $resourcerota->id;
                }
            }
        }

        for ($day = 1; $day <= $diffDays; $day++) {

            $day_looper = $start;
            $day_looper->addDay(1);

            $days[$day] = [
                'date' => $day_looper->format('Y-m-d'),
            ];

            foreach ($week as $week_day) {
                if ($week_day == strtolower($day_looper->format('l'))) {
                    if ($data[$week_day] != null) {
                        if (isset($data['time_f_'.$week_day]) && isset($data['time_to_'.$week_day])) {
                            $days[$day]['start'] = $data['time_f_'.$week_day];
                            $days[$day]['end'] = $data['time_to_'.$week_day];
                        } else {
                            $days[$day]['start'] = null;
                            $days[$day]['end'] = null;
                        }
                        if (isset($data['break_from_'.$week_day]) && isset($data['break_to_'.$week_day])) {
                            $days[$day]['start_off'] = $data['break_from_'.$week_day];
                            $days[$day]['end_off'] = $data['break_to_'.$week_day];
                        } else {
                            $days[$day]['start_off'] = null;
                            $days[$day]['end_off'] = null;
                        }
                        $days[$day]['resource_has_rota_id'] = $resourcerota->id;
                    } else {
                        $days[$day]['start'] = null;
                        $days[$day]['end'] = null;
                        $days[$day]['start_off'] = null;
                        $days[$day]['end_off'] = null;
                        $days[$day]['resource_has_rota_id'] = $resourcerota->id;
                    }
                }
            }
        }

        $rota_days_array = [];
        $dates = [];

        foreach ($days as $day) {

            $dates[] = $day['date'];

            $data_days['date'] = $day['date'];
            $data_days['start_time'] = $day['start'];
            $data_days['end_time'] = $day['end'];
            $data_days['start_off'] = $day['start_off'];
            $data_days['end_off'] = $day['end_off'];
            $data_days['start_timestamp'] = Carbon::parse($day['date'].' '.$day['start'])->format('Y-m-d H:i').':00';
            $data_days['end_timestamp'] = Carbon::parse($day['date'].' '.$day['end'])->format('Y-m-d H:i').':00';
            $data_days['resource_has_rota_id'] = $day['resource_has_rota_id'];

            $rota_days_array[] = $data_days;
        }

        $rota_days_records = ResourceHasRotaDays::whereIn('date', $dates)
            ->where('resource_has_rota_id', $resourcerota->id)
            ->select('id', 'date', 'start_time', 'end_time', 'start_off', 'end_off')
            ->get();

        if ($rota_days_records->count()) {
            $rota_days_records = $rota_days_records->toArray();
        } else {
            $rota_days_records = [];
        }

        return [
            'rota_days_array' => $rota_days_array,
            'rota_days_records' => $rota_days_records,
        ];
    }

    /**
     * Relationship: ResourceHasRotaDays belongs to ResourceHasRota
     */
    public function resource_rota()
    {
        return $this->belongsTo(\App\Models\ResourceHasRota::class, 'resource_has_rota_id');
    }

    /*
     * Get Resource Singe Day Rota with Date and ID
     *
     * @param: $resource_id
     * @param: $date
     *
     * @return: (mixed)
     */
    public static function getSingleDayRotaWithResourceID($resource_id, $date, $account_id, $location_id = false)
    {

        if ($location_id) {
            $resource_has_rota = ResourceHasRota::where('location_id', '=', $location_id)
                ->where('account_id', '=', $account_id)
                ->where('resource_id', '=', $resource_id)
                ->whereDate('start', '<=', $date)
                ->whereDate('end', '>=', $date)
                ->where('active', '=', 1)
                ->first();
        } else {
            $resource_has_rota = ResourceHasRota::where('account_id', '=', $account_id)
                ->where('resource_id', '=', $resource_id)
                ->whereDate('start', '<=', $date)
                ->whereDate('end', '>=', $date)
                ->where('active', '=', 1)
                ->first();
        }

        if ($resource_has_rota) {
            $resource_has_rota_day = self::where('resource_has_rota_id', '=', $resource_has_rota->id)
                ->whereDate('date', '=', $date)
                ->where('active', '=', 1)
                ->first();

            if ($resource_has_rota_day) {
                return $resource_has_rota_day->toArray();
            }
        }

        return [];
    }
}
