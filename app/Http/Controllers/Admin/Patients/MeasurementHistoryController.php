<?php

namespace App\Http\Controllers\Admin\Patients;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Helpers\NodesTree;
use App\Http\Controllers\Controller;
use App\Models\CustomFormFeedbacks;
use App\Models\Measurement;
use App\Models\Patients;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class MeasurementHistoryController extends Controller
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
        if (! Gate::allows('appointments_measurement_manage')) {
            return abort(401);
        }

        return view('admin.patients.card.measurement.index');
    }

    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
    public function datatable(Request $request, $id)
    {

        $filename = 'patient_custom_form_feedbacks';
        $records = [];
        $records['data'] = [];

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, $filename);

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
        $iTotalRecords = Measurement::getTotalRecords($request, Auth::user()->account_id, $id, 1);

        [$orderBy, $order] = getSortBy($request, 'created_at', 'desc', 'custom_form_feedbacks');

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $appointmentmeasurements = Measurement::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $id, 1);

        $records = $this->getFiltersData($records, $filename);

        if ($appointmentmeasurements->count()) {
            $records['data'] = $appointmentmeasurements;

            /*foreach($appointmentmeasurements as $appointmentmeasurements) {
                $patient = User::find($appointmentmeasurements->patient_id);
                $records["data"][] = array(
                    'form_name' => $appointmentmeasurements->form_name,
                    'patient_name' => $patient->name,
                    'created_at' => Carbon::parse($appointmentmeasurements->created_at)->format('F j,Y h:i A'),
                    'actions' => view('admin.patients.card.measurement.actions', compact('appointmentmeasurements'))->render(),
                );
            }*/

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
            'edit' => Gate::allows('appointments_measurement_edit'),
            'manage' => Gate::allows('appointments_measurement_manage'),
        ];

        return ApiHelper::apiDataTable($records);
    }

    private function getFiltersData($records, $filename)
    {

        $records['filter_values'] = [
            'name' => Filters::get(Auth::user()->id, $filename, 'name'),
            'created_from' => Filters::get(Auth::user()->id, $filename, 'created_from') ? Carbon::parse(Filters::get(Auth::user()->id, $filename, 'created_from'))->format('Y-m-d') : '',
            'created_to' => Filters::get(Auth::user()->id, $filename, 'created_to') ? Carbon::parse(Filters::get(Auth::user()->id, $filename, 'created_to'))->format('Y-m-d') : '',
        ];

        $records['active_filters'] = Filters::all(Auth::User()->id, $filename);

        return $records;
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        if (! Gate::allows('appointments_measurement_edit')) {
            return abort(401);
        }

        $measurementinformation = Measurement::find($id);

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($measurementinformation->custom_form_feedback_id);

        $patient_id = $custom_form_feedback->reference_id;

        if (! $custom_form_feedback) {
            return view('error');
        }

        $users = Patients::getActiveOnly()->toArray();

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $leadServices = $measurementinformation->service_id;

        return ApiHelper::makeResponse([
            'custom_form' => $custom_form_feedback,
            'users' => $users,
            'patient_id' => $patient_id,
            'measurementinformation' => $measurementinformation,
            'Services' => $Services,
            'leadServices' => $leadServices,
        ], 'admin.patients.card.measurement.edit');

    }

    /**
     * Update measurement in storage.
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateCustomFormFeedbacksRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update_measurement_field(Request $request, $id)
    {
        if (! Gate::allows('appointments_measurement_edit')) {
            return abort(401);
        }

        if (Measurement::updateRecord($request, Auth::User()->account_id, Auth::id())) {

            return response()->json(['message' => 'your Feedback is updated successfully', 'code' => '200'], 200);
        } else {
            return response()->json(['message' => 'Invalid request', 'code' => 402], 402);
        }

    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
     */
    public function filled_preview($id)
    {
        if (! Gate::allows('appointments_measurement_manage') && ! Gate::allows('patients_customform_manage')) {
            return abort(401);
        }
        $measurementinformation = Measurement::with('appointment.location')->findorFail($id);

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($measurementinformation->custom_form_feedback_id);

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

        $leadServices = $measurementinformation->service_id;

        return ApiHelper::makeResponse([
            'custom_form' => $custom_form_feedback,
            'patient_id' => $patient_id,
            'measurementinformation' => $measurementinformation,
            'users' => $users,
            'Services' => $Services,
            'leadServices' => $leadServices,
            'thisId' => $id,
        ], 'admin.patients.card.measurement.filled_preview');

    }
}
