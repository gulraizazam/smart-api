<?php

declare(strict_types=1);
namespace App\Http\Controllers\Admin\Patients;

use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\Measurement;
use App\Models\Medical;
use App\Models\User;
use App\Services\Appointment\AppointmentMedicalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class MedicalHistoryController extends Controller
{
    public function __construct(
        private readonly AppointmentMedicalService $appointmentMedicalService,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(int $id): mixed
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
    public function datatable(Request $request, int $id): mixed
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

        return response()->json($records);
    }

    private function getFilters($records): mixed
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
    public function edit(int $id): mixed
    {
        if (! Gate::allows('appointments_medical_edit')) {
            return abort(401);
        }

        $data = $this->appointmentMedicalService->getEditData($id);

        if (! empty($data['error'])) {
            return view('error');
        }

        return $this->successResponse('Record found.', $data);
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
     */
    public function filled_preview(int $id): mixed
    {
        if (! Gate::allows('appointments_medical_form_manage') && ! Gate::allows('patients_customform_manage')) {
            return abort(401);
        }

        $data = $this->appointmentMedicalService->getFilledPreviewData($id);

        if (! empty($data['error'])) {
            return view('error');
        }

        return $this->successResponse('Record found.', $data);
    }
}
