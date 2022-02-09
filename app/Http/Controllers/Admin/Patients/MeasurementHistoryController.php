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
use App\Models\Measurement;
use App\Models\Patients;
use App\User;
use Carbon\Carbon;
use Spatie\Browsershot\Browsershot;
use App\Helpers\NodesTree;

class MeasurementHistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        if (!Gate::allows('appointments_measurement_manage')) {
            return abort(401);
        }
        $filters = Filters::all(Auth::User()->id, 'patient_custom_form_feedbacks');
        $patient = User::finduser($id);
        return view('admin.patients.card.measurement.index',compact('patient','filters'));
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
        $iTotalRecords = Measurement::getTotalRecords($request, Auth::User()->account_id,$id,1);
        $iDisplayLength = intval($request->get('length'));
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->get('start'));
        $sEcho = intval($request->get('draw'));

        $appointmentmeasurements = Measurement::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id,$id,1);

        if($appointmentmeasurements) {
            foreach($appointmentmeasurements as $appointmentmeasurements) {
                $user = User::find($appointmentmeasurements->user_id);
                $patient = User::find($appointmentmeasurements->patient_id);
                $records["data"][] = array(
                    'form_name' => $appointmentmeasurements->form_name,
                    'patient_name' => $patient->name,
                    'created_at' => Carbon::parse($appointmentmeasurements->created_at)->format('F j,Y h:i A'),
                    'actions' => view('admin.patients.card.measurement.actions', compact('appointmentmeasurements'))->render(),
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
        if (!Gate::allows('appointments_measurement_edit')) {
            return abort(401);
        }

        $measurementinformation = Measurement::find($id);

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($measurementinformation->custom_form_feedback_id);

        $patient_id = $custom_form_feedback->reference_id;

        if (!$custom_form_feedback) {
            return view('error');
        }

        $users = Patients::getActiveOnly()->toArray();

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $leadServices = $measurementinformation->service_id;

        return view('admin.patients.card.measurement.edit', ['custom_form' => $custom_form_feedback,'users'=>$users,'patient_id'=>$patient_id,'measurementinformation' => $measurementinformation,'Services'=>$Services,'leadServices'=>$leadServices]);
    }

    /**
     * Update measurement in storage.
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateCustomFormFeedbacksRequest $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update_measurement_field(Request $request, $id)
    {
        if (!Gate::allows('appointments_measurement_edit')) {
            return abort(401);
        }

        if (Measurement::updateRecord($request, Auth::User()->account_id, Auth::id())) {

            return response()->json(["message" => "your Feedback is updated successfully", "code" => "200"], 200);
        } else {
            return response()->json(["message" => "Invalid request", "code" => 402], 402);
        }

    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function filled_preview($id)
    {
        if (!Gate::allows('appointments_measurement_manage') && !Gate::allows('patients_customform_manage')) {
            return abort(401);
        }
        $measurementinformation = Measurement::with('appointment.location')->findorFail($id);

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($measurementinformation->custom_form_feedback_id);

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

        $leadServices = $measurementinformation->service_id;
        return view('admin.patients.card.measurement.filled_preview', ['custom_form' => $custom_form_feedback,'patient_id'=>$patient_id,'measurementinformation'=>$measurementinformation,
        'users' => $users,'Services' => $Services,'leadServices'=>$leadServices, 'thisId' => $id]);
    }


}
