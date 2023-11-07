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
        $isServicePresent = false;
        $isUserPresent = false;
        $isLocationPresent = false;
        $isActivityTypePresent = false;
        $startDate = $request->startDate;
        $endDate = $request->endDate;
        if ($request->has('service_id') && $request->service_id !== 'all') {
            $isServicePresent = true;
        }
        if ($request->has('user_id')  && $request->user_id) {
            $isUserPresent = true;
        }

        if ($request->has('location_id')  && $request->location_id) {
            $isLocationPresent = true;
        }
        if ($request->has('activity_type')  && $request->activity_type!== 'all') {
            $isActivityTypePresent = true;
        }
        $activities = Activity::whereHas('serviceR')
        ->whereHas('centre')
        ->whereHas('patientR')
        ->whereHas('user')
        
        ->with(['serviceR', 'centre' , 'patientR','user','rescheduleBy','deleteBy'])
        ->whereBetween('created_at', [$request->startDate. ' 00:00:00', $request->endDate.' 23:59:00'])
        ->when($isServicePresent,function($query) use ($request){
            $query->where('service_id',$request->service_id);
        })
        ->when( $isUserPresent,function($query) use ($request){
            $query->where('user_id',$request->user_id);
        })
        ->when( $isLocationPresent,function($query) use ($request){
            $query->where('centre_id',$request->location_id);
        })
        ->when($isActivityTypePresent,function($query) use ($request){
            $query->where('activity_type',$request->activity_type);
        })
        ->latest()->get();

        $data=[];
        $i = 0;
        foreach($activities as $activity)
        {
            $action = $activity->action;
           
            switch ($action) {
                case 'booked':
                    {
                        $createdBy = $activity->user->name;
                        $data[$i]['colorClass']= $colorClasses[$i%4];
                        $data[$i]['time']=date('m-d-Y H:i',strtotime($activity->created_at));
                        $data[$i]['message']= '<strong class='. "'" .  $data[$i]['colorClass']."'" . '>' .$createdBy.'</strong>  '.$action.' a <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'.$activity->serviceR->name.'</strong> '.$activity->activity_type.' for <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'.$activity->patientR->name. '</strong> in <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'. $activity->centre->name. '</strong> on '. $activity->schedule_date;
                    }
                    break;
                case 'received':
                    {
                        $receivedBy = $activity->user->name;
                        $data[$i]['colorClass']= $colorClasses[$i%4];
                        $data[$i]['time']=date('m-d-Y H:i',strtotime($activity->created_at));
                        $data[$i]['message']= '<strong class='. "'" .  $data[$i]['colorClass']."'" . '>' .$receivedBy.'</strong>  '.$action.' a <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'.$activity->serviceR->name.'</strong> '.$activity->activity_type.' for <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'.$activity->patientR->name. '</strong> in <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'. $activity->centre->name. '</strong> on '. $activity->schedule_date;
                    }
                    break;
                case 'consumed':
                    {
                        $consumedBy = $activity->user->name;
                        $data[$i]['colorClass']= $colorClasses[$i%4];
                        $data[$i]['time']=date('m-d-Y H:i',strtotime($activity->created_at));
                        $data[$i]['message']= '<strong class='. "'" .  $data[$i]['colorClass']."'" . '>' .  $consumedBy.'</strong>  '.$action.' a <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'.$activity->serviceR->name.'</strong> '.$activity->activity_type.' for <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'.$activity->patientR->name. '</strong> in <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'. $activity->centre->name. '</strong> on '. $activity->schedule_date;
                    }
                    break;
                case 'rescheduled':
                    {
                        $rescheduledBy =$activity->rescheduleBy ?$activity->rescheduleBy->name : 'NA' ;
                        $data[$i]['colorClass']= $colorClasses[$i%4];
                        $data[$i]['time']=date('m-d-Y H:i',strtotime($activity->created_at));
                        $data[$i]['message']= '<strong class='. "'" .  $data[$i]['colorClass']."'" . '>' .$rescheduledBy .'</strong>  '.$action.' a <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'.$activity->serviceR->name.'</strong> '.$activity->activity_type.' for <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'.$activity->patientR->name. '</strong> in <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'. $activity->centre->name. '</strong> on '. $activity->schedule_date;
                    }
                    break;
                case 'deleted':
                    {
                        $deletedBy = $activity->deleteBy ?$activity->deleteBy->name : 'NA' ;
                        
                        $data[$i]['colorClass']= $colorClasses[$i%4];
                        $data[$i]['time']=date('m-d-Y H:i',strtotime($activity->created_at));
                        $data[$i]['message']= '<strong class='. "'" .  $data[$i]['colorClass']."'" . '>' . $deletedBy.'</strong>  '.$action.' a <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'.$activity->serviceR->name.'</strong> '.$activity->activity_type.' for <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'.$activity->patientR->name. '</strong> in <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'. $activity->centre->name. '</strong> on '. $activity->deleted_date;
                    }
                    break;
                default:

                    break;

            }
            $i++;

        }

        return view('admin.reports.activity_logs.activities', get_defined_vars());
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
