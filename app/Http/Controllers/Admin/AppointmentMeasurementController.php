<?php

namespace App\Http\Controllers\Admin;

use App\Models\Measurement;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Appointments;
use App\Models\CustomForms;
use Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\Patients;
use App\Helpers\NodesTree;
use App\Models\CustomFormFeedbacks;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Browsershot\Browsershot;
use Barryvdh\DomPDF\Facade as PDF;
use Dompdf\Dompdf;
use App;

class AppointmentMeasurementController extends Controller
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

        $appointment = Appointments::findorfail($id);

        $patients = User::where([
            ['account_id','=',Auth::User()->account_id],
            ['active','=','1'],
            ['user_type_id','=','3']
        ])->pluck('name', 'id');
        $patients->prepend('All', '');

        $users = User::where([
            ['account_id','=',Auth::User()->account_id],
            ['active','=','1'],
            ['user_type_id','!=','3']
        ])->pluck('name', 'id');
        $users->prepend('All', '');


        return view('admin.appointments.measurements.index', compact('appointment','patients','users'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($id)
    {
        if (!Gate::allows('appointments_measurement_create')) {
            return abort(401);
        }
        $where = array();

        if (Auth::User()->account_id) {
            $where[] = array(
                'account_id',
                '=',
                Auth::User()->account_id
            );
        }
        if (Auth::User()->account_id) {
            $where[] = array(
                'custom_form_type',
                '=',
                '1'
            );
        }

        if (count($where)) {
            $CustomForms = CustomForms::where($where)->orderBy('sort_number','asc')->get();
        } else {
            $CustomForms = CustomForms::orderBy('sort_number','asc')->get();
        }

        return view('admin.appointments.measurements.AddNewMeasurements',compact('CustomForms','id'));
    }

    /**
     * Show the form for creating new Measurements.
     *
     * @return \Illuminate\Http\Response
     */
    public function fill_form($form_id,$appointment_id)
    {
        if (!Gate::allows('appointments_measurement_create')) {
            return abort(401);
        }
        $appointmentinformation = Appointments::find($appointment_id);
        $users = Patients::where([
            ['active','=','1'],
            ['id','=',$appointmentinformation->patient_id]
        ])->get();
        foreach ($users as $user){
            $patient_id = $user->id;
        }

        $custom_form = CustomForms::get_all_fields_data($form_id);

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $leadServices = $appointmentinformation->service_id;

        return view("admin.appointments.measurements.create", compact('custom_form', 'users','patient_id','appointmentinformation','Services','leadServices'));
    }

    /**
     * Store the forms for measurement.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function submit_form(Request $request, $id,$appointment_id)
    {
        if (!Gate::allows('appointments_measurement_create')) {
            return abort(401);
        }
        $data['custom_form_type'] = 1;
        $custom_form_feedback = CustomFormFeedbacks::createRecord($request, $id, Auth::User()->account_id, Auth::id(),$data);
        if (!$custom_form_feedback) {
            return response()->json(["message" => "Invalid request", "code" => 402], 402);
        } else {

            $measurement = Measurement::CreateRecord($request,$custom_form_feedback->id,Auth::User()->id);

            if(!$measurement) {
                return response()->json(["message" => "Invalid request", "code" => 402], 402);
            }
        }
        return response()->json(["message" => "your Form is filled successfully", "code" => "200"], 200);
    }

    /**
     * Display a listing of Appointment measurement.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
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
        $iTotalRecords = Measurement::getTotalRecords($request, Auth::User()->account_id,$id);

        $iDisplayLength = intval($request->get('length'));
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->get('start'));
        $sEcho = intval($request->get('draw'));

        $appointmentmeasurements = Measurement::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id,$id);

        if($appointmentmeasurements) {
            foreach($appointmentmeasurements as $appointmentmeasurement) {
                $user = User::find($appointmentmeasurement->user_id);
                $patient = User::find($appointmentmeasurement->patient_id);
                $records["data"][] = array(
                    'name' => $appointmentmeasurement->form_name,
                    'patient_id' => $patient->name,
                    'created_by' => $user->name,
                    'type' => $appointmentmeasurement->type,
                    'created_at' => Carbon::parse($appointmentmeasurement->created_at)->format('F j,Y h:i A'),
                    'actions' => view('admin.appointments.measurements.actions', compact('appointmentmeasurement'))->render(),
                );
            }
        }
        $records["draw"] = $sEcho;
        $records["recordsTotal"] = $iTotalRecords;
        $records["recordsFiltered"] = $iTotalRecords;

        return response()->json($records);
    }

    /**
     * Show the form for editing Measurement.
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

        return view('admin.appointments.measurements.edit', ['custom_form' => $custom_form_feedback,'users'=>$users,'patient_id'=>$patient_id,'measurementinformation' => $measurementinformation,'Services'=>$Services,'leadServices'=>$leadServices]);
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
        if (!Gate::allows('appointments_measurement_manage')) {
            return abort(401);
        }
        $measurementinformation = Measurement::with('appointment.location')->findorFail($id);
        //dd($measurementinformation);dd($measurementinformation->appointment->location->image_src);

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

        return view('admin.appointments.measurements.filled_preview', ['custom_form' => $custom_form_feedback,'patient_id'=>$patient_id,'measurementinformation'=>$measurementinformation,
                                                                             'users' => $users,'Services' => $Services,'leadServices'=> $leadServices, 'thisId' => $id]);
    }
    /**
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function filledPrint($id)
    {
        if (!Gate::allows('appointments_measurement_manage')) {
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

        return view('admin.custom_form_feedbacks.appointment_measurement_filled_print', ['custom_form' => $custom_form_feedback,'patient_id'=>$patient_id,'measurementinformation'=>$measurementinformation,
                                                                       'users' => $users,'Services' => $Services,'leadServices'=> $leadServices, 'thisId' => $id]);
    }

    /**
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function exportPdf($id)
    {
        ini_set('max_execution_time', '0');
        if (!Gate::allows('appointments_measurement_manage')) {
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
        $parentGroups->build(0, Auth::user()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $leadServices = $measurementinformation->service_id;

        //return view('admin.custom_form_feedbacks.filled_export_pdf', ['custom_form' => $custom_form_feedback, 'thisId' => $id]);
        $pdfName = 'measurement_form'.'_'.$id.'_'.date('YmdHis') . ".pdf";
        $custom_form = $custom_form_feedback;
        $thisId = $id;
        $content = view('admin.custom_form_feedbacks.appointment_measurement_filled_export_pdf', ['custom_form' => $custom_form_feedback,'patient_id'=>$patient_id,'measurementinformation'=>$measurementinformation,
        'users' => $users,'Services' => $Services,'leadServices'=> $leadServices, 'thisId' => $id])->render();
        $pdf = App::make('dompdf.wrapper');

        $pdf->loadHTML($content);
        $pdf->setPaper('A4', 'landscape');
        return $pdf->stream('Medical Form Report', 'landscape');
        //return $file->download($pdfName);
        /*
                try {
                    $options = [
                        'orientation'   => 'landscape',
                        'encoding'      => 'UTF-8',
                        //'header-html'   => $page_header_html,
                        //'footer-html'   => $page_footer_html
                        'zoom' => 1,
                        //'margin-bottom' => '10mm'
                    ];
                    $pdf = PDF::loadView('admin.custom_form_feedbacks.filled_export_pdf', ['custom_form' => $custom_form_feedback, 'thisId' => $id])
                        ->setPaper('A4', 'landscape')
                        //->setOption('zoom', 1)
                        //->setOption('margin-top', '40mm')
                        //->setOption('margin-bottom', '10mm');
                        ->setOptions($options);
                    $pdfName = 'custom_form'.'_'.$id.'_'.date('YmdHis') . ".pdf";
                    return $pdf->download($pdfName);
                    //return $pdf->inline($pdfName);
                } catch (Exception $e) {
                    Log::info($e);
                    return redirect()->back()->withError(Lang::get('messages.error.general'));
                }
        */
        //$pdf = PDF::loadView('admin.custom_form_feedbacks.filled_export_pdf', ['custom_form' => $custom_form_feedback, 'thisId' => $id]);
        //$pdf->setPaper('A4', 'landscape');
        //return $pdf->stream('staffReport', 'landscape');
        /*
                $pdfName = 'custom_form'.'_'.$id.'_'.date('YmdHis') . ".pdf";
                $output_file = public_path("assets/pdf_download/".$pdfName);
                $pdf = PDF::loadView('admin.custom_form_feedbacks.filled_export_pdf',['custom_form' => $custom_form_feedback, 'thisId' => $id])->setPaper('A4', 'landscape')->save($output_file);

                $headers = array(
                    'Content-Type: application/pdf',
                );
                return response()->download($output_file, $pdfName, $headers);
        */
    }
}
