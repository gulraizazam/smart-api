<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\NodesTree;
use App\Http\Controllers\Controller;
use App\Models\Appointments;
use App\Models\CustomFormFeedbacks;
use App\Models\CustomForms;
use App\Models\Measurement;
use App\Models\Medical;
use App\Models\Patients;
use App\Models\User;
use Barryvdh\DomPDF\Facade as PDF;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AppointmentMedicalController extends Controller
{
    public $success;

    public $error;

    public $unauthorized;

    public function __construct()
    {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        if (! Gate::allows('appointments_medical_form_manage')) {
            return abort(401);
        }

        $appointment = Appointments::findorfail($id);

        $patients = User::where([
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1'],
            ['user_type_id', '=', '3'],
        ])->pluck('name', 'id');

        $users = User::where([
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1'],
            ['user_type_id', '!=', '3'],
        ])->pluck('name', 'id');

        return view('admin.appointments.medicals.index', compact('appointment', 'patients', 'users'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create($id)
    {
        if (! Gate::allows('appointments_medical_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $where = [];

        if (Auth::User()->account_id) {
            $where[] = [
                'account_id',
                '=',
                Auth::User()->account_id,
            ];
        }
        if (Auth::User()->account_id) {
            $where[] = [
                'custom_form_type',
                '=',
                '2',
            ];
        }

        if (count($where)) {
            $CustomForms = CustomForms::where($where)->orderBy('sort_number', 'asc')->get();
        } else {
            $CustomForms = CustomForms::orderBy('sort_number', 'asc')->get();
        }

        return ApiHelper::apiResponse($this->success, 'Records found.', true, [
            'CustomForms' => $CustomForms,
            'id' => $id,
        ]);

        // return view('admin.appointments.medicals.AddNewMedical',compact('CustomForms','id'));
    }

    /**
     * Show the form for creating new Medical history form.
     *
     * @return \Illuminate\Http\Response
     */
    public function fill_form($form_id, $appointment_id)
    {
        if (! Gate::allows('appointments_medical_create')) {
            return abort(401);
        }
        $appointmentinformation = Appointments::find($appointment_id);
        $users = Patients::where([
            ['active', '=', '1'],
            ['id', '=', $appointmentinformation->patient_id],
        ])->get();
        foreach ($users as $user) {
            $patient_id = $user->id;
        }

        $custom_form = CustomForms::get_all_fields_data($form_id);

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $leadServices = $appointmentinformation->service_id;

        return view('admin.appointments.medicals.create', compact('custom_form', 'users', 'patient_id', 'appointmentinformation', 'Services', 'leadServices'));
    }

    /**
     * Store the forms for medical history form.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function submit_form(Request $request, $id, $appointment_id)
    {
        if (! Gate::allows('appointments_medical_create')) {
            return abort(401);
        }
        $data['custom_form_type'] = 2;
        $custom_form_feedback = CustomFormFeedbacks::createRecord($request, $id, Auth::User()->account_id, Auth::id(), $data);
        if (! $custom_form_feedback) {
            return response()->json(['message' => 'Invalid request', 'code' => 402], 402);
        } else {
            $medicals = Medical::CreateRecord($request, $custom_form_feedback->id, Auth::User()->id);
            if (! $medicals) {
                return response()->json(['message' => 'Invalid request', 'code' => 402], 402);
            }
        }

        return response()->json(['message' => 'your Form is filled successfully', 'code' => '200'], 200);
    }

    /**
     * Display a listing of Appointment medical history form.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request, $id)
    {

        $records = [];
        $records['data'] = [];

        $filters = getFilters($request->all());

        $records = [];
        $records['data'] = [];

        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $appointmentmeasurements = Measurement::getBulkData_formeasurement($ids);
            if ($appointmentmeasurements) {
                foreach ($appointmentmeasurements as $appointmentmeasurement) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! Measurement::isChildExists($appointmentmeasurement->id, Auth::User()->account_id)) {
                        $appointmentmeasurement->delete();
                    }
                }
            }
            $records['status'] = true; // pass custom message(useful for getting status of group actions)
            $records['message'] = 'Records has been deleted successfully!'; // pass custom message(useful for getting status of group actions)
        }

        // Get Total Records
        $iTotalRecords = Medical::getTotalRecords($request, Auth::User()->account_id, $id);

        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $appointmentmedicals = Medical::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $id);

        if ($appointmentmedicals) {
            foreach ($appointmentmedicals as $appointmentmedicals) {
                $user = User::find($appointmentmedicals->user_id);
                $patient = User::find($appointmentmedicals->patient_id);
                $records['data'][] = [
                    'id' => $appointmentmedicals->id,
                    'name' => $appointmentmedicals->form_name,
                    'patient_id' => $patient->name,
                    'created_by' => $user->name,
                    'created_at' => Carbon::parse($appointmentmedicals->created_at)->format('F j,Y h:i A'),
                    //'actions' => view('admin.appointments.medicals.actions', compact('appointmentmedicals'))->render(),
                ];
            }

            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];
        }

        $records['permissions'] = [
            'edit' => Gate::allows('appointments_medical_edit'),

        ];

        return response()->json($records);
    }

    /**
     * Show the form for editing medical history form.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! Gate::allows('appointments_medical_edit')) {
            return abort(401);
        }

        $medicalinformation = Medical::find($id);

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($medicalinformation->custom_form_feedback_id);

        $patient_id = $custom_form_feedback->reference_id;

        if (! $custom_form_feedback) {
            return view('error');
        }

        $users = Patients::getActiveOnly()->toArray();

        return view('admin.appointments.medicals.edit', ['custom_form' => $custom_form_feedback, 'users' => $users, 'patient_id' => $patient_id, 'medicalinformation' => $medicalinformation]);
    }

    /**
     * Update medical in storage.
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateCustomFormFeedbacksRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update_medical_field(Request $request, $id)
    {
        if (! Gate::allows('appointments_measurement_edit')) {
            return abort(401);
        }

        if (Medical::updateRecord($request, Auth::User()->account_id, Auth::id())) {

            return response()->json(['message' => 'your Feedback is updated successfully', 'code' => '200'], 200);
        } else {
            return response()->json(['message' => 'Invalid request', 'code' => 402], 402);
        }

    }

    /**
     * Show the medical form for preview .
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function filled_preview($id)
    {
        if (! Gate::allows('appointments_medical_form_manage')) {
            return abort(401);
        }
        $medicalinformation = Medical::with('appointment.location')->findorFail($id);

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($medicalinformation->custom_form_feedback_id);

        if (! $custom_form_feedback) {
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

        return view('admin.appointments.medicals.filled_preview', ['custom_form' => $custom_form_feedback, 'patient_id' => $patient_id, 'medicalinformation' => $medicalinformation,
            'users' => $users, 'Services' => $Services, 'leadServices' => $leadServices, 'thisId' => $id]);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function filledPrint($id)
    {
        if (! Gate::allows('appointments_medical_form_manage')) {
            return abort(401);
        }

        $medicalinformation = Medical::with('appointment.location')->findorFail($id);

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($medicalinformation->custom_form_feedback_id);

        if (! $custom_form_feedback) {
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

        return view('admin.custom_form_feedbacks.appointment_medical_filled_print', ['custom_form' => $custom_form_feedback, 'patient_id' => $patient_id, 'medicalinformation' => $medicalinformation,
            'users' => $users, 'Services' => $Services, 'leadServices' => $leadServices, 'thisId' => $id]);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function exportPdf($id)
    {
        if (! Gate::allows('appointments_medical_form_manage')) {
            return abort(401);
        }
        $medicalinformation = Medical::with('appointment.location')->findorFail($id);

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($medicalinformation->custom_form_feedback_id);

        if (! $custom_form_feedback) {
            return view('error');
        }

        //return view('admin.custom_form_feedbacks.appointment_medical', ['custom_form' => $custom_form_feedback, 'thisId' => $id]);
        $patient_id = $custom_form_feedback->reference_id;

        $users = Patients::getActiveOnly()->toArray();

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $leadServices = $medicalinformation->service_id;

        $pdfName = 'appointment_medical_custom_form'.'_'.$id.'_'.date('YmdHis').'.pdf';
        $custom_form = $custom_form_feedback;
        $thisId = $id;

        $dompdf = new Dompdf();
        $content = view('admin.custom_form_feedbacks.appointment_medical_filled_export_pdf', ['custom_form' => $custom_form_feedback, 'patient_id' => $patient_id, 'medicalinformation' => $medicalinformation,
            'users' => $users, 'Services' => $Services, 'leadServices' => $leadServices, 'thisId' => $id]);

        $dompdf->loadHtml($content);
        $dompdf->setPaper('A3');
        $dompdf->render();

        return $dompdf->stream();
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
