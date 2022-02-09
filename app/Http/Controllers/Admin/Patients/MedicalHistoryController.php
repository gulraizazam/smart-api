<?php

namespace App\Http\Controllers\Admin\Patients;

use App\Helpers\Filters;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Input;
use App\Models\CustomFormFeedbackDetails;
use App\Models\CustomFormFeedbacks;
use App\Models\CustomForms;
use App\Models\Medical;
use App\Models\Patients;
use App\User;
use Carbon\Carbon;
use Spatie\Browsershot\Browsershot;
use App\Helpers\NodesTree;

class MedicalHistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        if (!Gate::allows('appointments_medical_form_manage')) {
            return abort(401);
        }
        $filters = Filters::all(Auth::User()->id, 'patient_custom_form_feedbacks');
        $patient = User::finduser($id);
        return view('admin.patients.card.medical.index',compact('patient','filters'));
    }
    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     * @throws \Throwable
     */
    public function datatable(Request $request,$id){

        $records = array();
        $records["data"] = array();

        if ($request->get('customActionType') && $request->get('customActionType') == "group_action") {
            $appointmentmeasurements = Measurement::getBulkData_formeasurement($request->get('id'));
            if($appointmentmeasurements) {
                foreach($appointmentmeasurements as $appointmentmeasurement) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if(!Measurement::isChildExists($appointmentmeasurement->id, Auth::User()->account_id)) {
                        $appointmentmeasurement->delete();
                    }
                }
            }
            $records["customActionStatus"] = "OK"; // pass custom message(useful for getting status of group actions)
            $records["customActionMessage"] = "Records has been deleted successfully!"; // pass custom message(useful for getting status of group actions)
        }

        // Get Total Records
        $iTotalRecords = Medical::getTotalRecords($request, Auth::User()->account_id,$id,1);
        $iDisplayLength = intval($request->get('length'));
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->get('start'));
        $sEcho = intval($request->get('draw'));

        $appointmentmedicals = Medical::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id,$id,1);

        if($appointmentmedicals) {
            foreach($appointmentmedicals as $appointmentmedicals) {
                $user = User::find($appointmentmedicals->user_id);
                $patient = User::find($appointmentmedicals->patient_id);
                $records["data"][] = array(
                    'form_name' => $appointmentmedicals->form_name,
                    'patient_name' => $patient->name,
                    'created_at' => Carbon::parse($appointmentmedicals->created_at)->format('F j,Y h:i A'),
                    'actions' => view('admin.patients.card.medical.actions', compact('appointmentmedicals'))->render(),
                );
            }
        }
        $records["draw"] = $sEcho;
        $records["recordsTotal"] = $iTotalRecords;
        $records["recordsFiltered"] = $iTotalRecords;

        return response()->json($records);
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!Gate::allows('appointments_medical_edit')) {
            return abort(401);
        }

        $medicalinformation = Medical::find($id);
        

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($medicalinformation->custom_form_feedback_id);
        $patient_id = $custom_form_feedback->reference_id;

        if (!$custom_form_feedback) {
            return view('error');
        }

        $users = Patients::getActiveOnly()->toArray();

        return view('admin.patients.card.medical.edit', ['custom_form' => $custom_form_feedback,'users'=>$users,'patient_id'=>$patient_id,'medicalinformation' => $medicalinformation]);
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function filled_preview($id)
    {
        if (!Gate::allows('appointments_medical_form_manage') && !Gate::allows('patients_customform_manage')) {
            return abort(401);
        }
        $medicalinformation = Medical::with('appointment.location')->findorFail($id);

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($medicalinformation->custom_form_feedback_id);

        if (!$custom_form_feedback) {
            return view('error');
        }

        $patient_id = $custom_form_feedback->reference_id;

        $users = Patients::getActiveOnly()->toArray();

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $leadServices = $medicalinformation->service_id;
        return view('admin.patients.card.medical.filled_preview', ['custom_form' => $custom_form_feedback,'patient_id'=>$patient_id,'medicalinformation'=>$medicalinformation,
        'users' => $users,'Services' => $Services,'leadServices'=>$leadServices, 'thisId' => $id]);
    }


}
