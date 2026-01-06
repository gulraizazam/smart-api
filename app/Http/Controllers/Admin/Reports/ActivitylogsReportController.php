<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Appointments;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\Services;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivitylogsReportController extends Controller
{
    public function index(){
        $services = Services::where(['parent_id' => 0])->where('slug', '!=', 'all')->pluck('id', 'name');
        $employees = User::getAllActiveEmployeeRecords(Auth::User()->account_id, ACL::getUserCentres())->pluck('name', 'id');
        $select_All = ['' => 'All'];
        $operators = ($select_All + $employees->toArray() );
        $locations = Locations::getActiveSorted(ACL::getUserCentres());
        if(!Auth::user()->hasRole('FDM')){
            $locations->prepend('All', '');

        }
        $locations_com = Locations::getActiveSorted(ACL::getUserCentres());


        return view('admin.reports.activity_logs.index', get_defined_vars());

    }
    public function fetchActivityReport(Request $request)
    {
        $colorClasses=['text-warning', 'text-success','text-primary','text-danger'];
        
        // Build filters for ActivityLogService
        $filters = [
            'start_date' => $request->startDate,
            'end_date' => $request->endDate,
        ];
        
        if ($request->has('service_id') && $request->service_id !== 'all') {
            $filters['service_id'] = $request->service_id;
        }
        if ($request->has('user_id') && $request->user_id) {
            $filters['user_id'] = $request->user_id;
        }
        if ($request->has('location_id') && $request->location_id) {
            $filters['location_id'] = $request->location_id;
        }
        if ($request->has('activity_type') && $request->activity_type !== 'all') {
            $filters['activity_type'] = $request->activity_type;
        }

        // Use shared ActivityLogService
        $activities = \App\Services\ActivityLogService::getActivityLogs($filters);

        // Format for view
        $data = [];
        $i = 0;
        foreach ($activities as $activity) {
            $data[$i]['colorClass'] = $colorClasses[$i % 4];
            $data[$i]['time'] = $activity['time_short'];
            $data[$i]['message'] = $activity['description'];
            $i++;
        }

        return view('admin.reports.activity_logs.activities', compact('data'));
    }
    public function InsertLogs()
    {
        $startDate = '2023-11-01 00:00:00';
        $endDate = '2023-11-05 23:59:59';

        $appointments = Appointments::select('id','location_id', 'service_id', 'patient_id', 'scheduled_date','created_at','updated_at','first_scheduled_date','created_by')
            ->where('appointment_type_id', 1)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['location', 'service', 'patient'])
            ->get();


        $action = 'booked';
        $activityType = 'Consultancy';

        $activities = [];
        foreach ($appointments as $appointment) {

            if($appointment->first_scheduled_date == $appointment->scheduled_date){
                $action = 'booked';
            }else{
                $action = 'rescheduled';
            }
            $location = $appointment->location;
            $service = $appointment->service;
            $patient = $appointment->patient;

            $activity = [
                'created_by' => $appointment->created_by,
                'user_id' =>$appointment->created_by,
                'action' => $action,
                'appointment_type' => $activityType,
                'appointment_id' => $appointment->id,
                'activity_type' => $activityType,
                'location' => $location ? $location->name : '',
                'centre_id' => $location ? $location->id : null,
                'service_id' => $service ? $service->id : null,
                'service' => $service ? $service->name : null,
                'patient_id' => $patient ? $patient->id : null,
                'patient' => $patient ? $patient->name : null,
                'schedule_date' => $appointment->scheduled_date,
                'created_at' => $appointment->created_at,
                'updated_at' => $appointment->updated_at,
            ];

            $activities[] = $activity;
        }

        Activity::insert($activities);
        return true;
    }

}
