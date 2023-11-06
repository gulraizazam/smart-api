<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Locations;
use App\Models\Services;
use App\Models\User;
use Illuminate\Http\Request;
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
        ->with(['serviceR', 'centre' , 'patientR','user'])
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
                        $data[$i]['colorClass']= $colorClasses[$i%4];
                        $data[$i]['time']=date('m-d-Y H:i',strtotime($activity->created_at));
                        $data[$i]['message']= '<strong class='. "'" .  $data[$i]['colorClass']."'" . '>' . $activity->user->name.'</strong>  '.$action.' a <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'.$activity->serviceR->name.'</strong> '.$activity->activity_type.' for <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'.$activity->patientR->name. '</strong> in <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'. $activity->centre->name. '</strong> on '. $activity->created_at;
                    }
                    break;
                case 'received':
                    {
                        $data[$i]['colorClass']= $colorClasses[$i%4];
                        $data[$i]['time']=date('m-d-Y H:i',strtotime($activity->created_at));
                        $data[$i]['message']= '<strong class='. "'" .  $data[$i]['colorClass']."'" . '>' . $activity->user->name.'</strong>  '.$action.' a <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'.$activity->serviceR->name.'</strong> '.$activity->activity_type.' for <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'.$activity->patientR->name. '</strong> in <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'. $activity->centre->name. '</strong> on '. $activity->created_at;
                    }
                    break;
                case 'consumed':
                    {
                        $data[$i]['colorClass']= $colorClasses[$i%4];
                        $data[$i]['time']=date('m-d-Y H:i',strtotime($activity->created_at));
                        $data[$i]['message']= '<strong class='. "'" .  $data[$i]['colorClass']."'" . '>' . $activity->user->name.'</strong>  '.$action.' a <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'.$activity->serviceR->name.'</strong> '.$activity->activity_type.' for <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'.$activity->patientR->name. '</strong> in <strong class='. "'" .  $data[$i]['colorClass']."'" . '>'. $activity->centre->name. '</strong> on '. $activity->created_at;
                    }
                    break;
                default:

                    break;

            }
            $i++;

        }

        return view('admin.reports.activity_logs.activities', get_defined_vars());
    }
}
