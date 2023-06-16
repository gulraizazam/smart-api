<?php

namespace App\Http\Controllers\Admin\Patients;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Helpers\NodesTree;
use App\Http\Controllers\Controller;
use App\Models\CustomFormFeedbacks;
use App\Models\Measurement;
use App\Models\Medical;
use App\Models\Patients;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class MedicalHistoryController extends Controller
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
        $filters = Filters::all(Auth::User()->id, 'patient_custom_form_feedbacks');
        $patient = User::finduser($id);

        return view('admin.patients.card.medical.index', compact('patient', 'filters'));
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
        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, $filename);

        $records = [];
        $records['data'] = [];

        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $appointmentmeasurements = Measurement::getBulkData_formeasurement($ids);
            if ($appointmentmeasurements) {
                foreach ($appointmentmeasurements as $appointmentmeasurement) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! Measurement::isChildExists($appointmentmeasurement->id, Auth::user()->account_id)) {
                        $appointmentmeasurement->delete();
                    }
                }
            }
            $records['status'] = true; // pass custom message(useful for getting status of group actions)
            $records['message'] = 'Records has been deleted successfully!'; // pass custom message(useful for getting status of group actions)
        }

        // Get Total Records
        $iTotalRecords = Medical::getTotalRecords($request, Auth::User()->account_id, $id, 1);

        [$orderBy, $order] = getSortBy($request, 'created_at', 'desc', 'medicals');

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $appointmentmedicals = Medical::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $id, 1);

        $records = $this->getFilters($records);

        if ($appointmentmedicals->count()) {
            $records['data'] = $appointmentmedicals;

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
            'manage' => Gate::allows('appointments_medical_form_manage'),
        ];

        return ApiHelper::apiDataTable($records);
    }

    private function getFilters($records)
    {

        $records['active_filters'] = Filters::all(Auth::User()->id, 'patient_custom_form_feedbacks');

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

        return ApiHelper::makeResponse([
            'custom_form' => $custom_form_feedback,
            'users' => $users,
            'patient_id' => $patient_id,
            'medicalinformation' => $medicalinformation,
        ], 'admin.patients.card.medical.edit');
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
     */
    public function filled_preview($id)
    {
        if (! Gate::allows('appointments_medical_form_manage') && ! Gate::allows('patients_customform_manage')) {
            return abort(401);
        }
        $medicalinformation = Medical::with('patient', 'appointment.location')->findorFail($id);

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($medicalinformation->custom_form_feedback_id);

        if (! $custom_form_feedback) {
            return view('error');
        }

        $patient_id = $custom_form_feedback->reference_id;

        $users = Patients::getActiveOnly()->toArray();

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::user()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $leadServices = $medicalinformation->service_id;

        return ApiHelper::makeResponse([
            'custom_form' => $custom_form_feedback,
            'patient_id' => $patient_id,
            'medicalinformation' => $medicalinformation,
            'users' => $users,
            'Services' => $Services,
            'leadServices' => $leadServices,
            'thisId' => $id,
        ], 'admin.patients.card.medical.filled_preview');
    }
}
