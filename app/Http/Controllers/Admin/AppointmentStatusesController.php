<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\AppointmentStatuses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class AppointmentStatusesController extends Controller
{
    /**
     * Display a listing of Appointment_statuse.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\never
     */
    public function index()
    {
        if (! Gate::allows('appointment_statuses_manage')) {
            return abort(401);
        }

        return view('admin.appointment_statuses.index');
    }

    /**
     * Display a listing of Appointment_statuse.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {
            if (! Gate::allows('appointment_statuses_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $filename = 'appointment_statuses';

            $filters = getFilters($request->all());

            $apply_filter = checkFilters($filters, $filename);

            $records = [];
            $records['data'] = [];

            [$orderBy, $order] = getSortBy($request);
            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $AppointmentStatuses = AppointmentStatuses::getBulkData($ids);
                if ($AppointmentStatuses) {
                    foreach ($AppointmentStatuses as $appointment) {
                        // Check if child records exists or not, If exist then disallow to delete it.
                        if (! AppointmentStatuses::isChildExists($appointment->id, Auth::User()->account_id)) {
                            $appointment->delete();
                        }
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            // Get Total Records
            $iTotalRecords = AppointmentStatuses::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $allAppointmentStatuses = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);

            $AppointmentStatuses = AppointmentStatuses::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

            if ($AppointmentStatuses) {

                foreach ($AppointmentStatuses as $appointment_statuse) {
                    $appointment_statuse->parent_id = ($appointment_statuse->parent_id && array_key_exists($appointment_statuse->parent_id, $allAppointmentStatuses)) ? $allAppointmentStatuses[$appointment_statuse->parent_id]->name : '-';
                    $appointment_statuse->is_comment = ($appointment_statuse->is_comment) ? 'Yes' : 'No';
                    $appointment_statuse->allow_message = ($appointment_statuse->parent_id != 0) ? (($appointment_statuse->allow_message) ? 'Yes' : 'No') : '-';
                    $appointment_statuse->is_default = ($appointment_statuse->parent_id != 0) ? ($appointment_statuse->is_default) ? 'Yes' : 'No' : '-';
                    $appointment_statuse->is_arrived = ($appointment_statuse->is_arrived) ? 'Yes' : 'No';
                    $appointment_statuse->is_cancelled = ($appointment_statuse->is_cancelled) ? 'Yes' : 'No';
                    $appointment_statuse->is_unscheduled = ($appointment_statuse->is_unscheduled) ? 'Yes' : 'No';
                }
            }

            $records['data'] = $AppointmentStatuses;
            $records['permissions'] = [
                'edit' => Gate::allows('appointment_statuses_edit'),
                'delete' => Gate::allows('appointment_statuses_destroy'),
                'active' => Gate::allows('appointment_statuses_active'),
                'inactive' => Gate::allows('appointment_statuses_inactive'),
            ];

            $filters = Filters::all(Auth::user()->id, 'appointment_statuses');
            $records['active_filters'] = $filters;
            $parentAppointmentStatuses = AppointmentStatuses::getParentRecords(false, Auth::User()->account_id, [], true);
            $records['filter_values'] = [
                'parents' => $parentAppointmentStatuses,
                'status' => config('constants.status'),
            ];
            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentStatusesController');
        }
    }

    /**
     * Show the form for creating new Appointment_statuse.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! Gate::allows('appointment_statuses_create')) {
            return abort(401);
        }

        $appointment_statuse = new \stdClass();
        $appointment_statuse->is_default = 0;
        $appointment_statuse->is_arrived = 0;
        $appointment_statuse->is_cancelled = 0;
        $appointment_statuse->is_unscheduled = 0;
        $appointment_statuse->is_converted = 0;

        $parentAppointmentStatuses = AppointmentStatuses::getParentRecords('Parent Group', Auth::User()->account_id, false, true);

        return view('admin.appointment_statuses.create', compact('parentAppointmentStatuses', 'appointment_statuse'));
    }

    /**
     * Store a newly created Appointment_statuse in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            if (! Gate::allows('appointment_statuses_create')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), 200);
            }
            if (AppointmentStatuses::createRecord($request, Auth::User()->account_id)) {
                return $this->successResponse('Record has been created successfully.', null, 200);
            }

            return $this->errorResponse('Something went wrong, please try again later.', 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentStatusesController');
        }
    }

    /**
     * Validate form fields
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);
    }

    /**
     * Get data for editing Appointment_statuse.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        try {
            if (! Gate::allows('appointment_statuses_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }
            $appointment_statuse = AppointmentStatuses::getData($id);
            if (! $appointment_statuse) {
                return $this->errorResponse('No Record Found!', 200);
            }
            $parentAppointmentStatuses = AppointmentStatuses::getParentRecords(false, Auth::User()->account_id, $appointment_statuse->id, true);
            $appointment_statuse->parent_options = $parentAppointmentStatuses;

            return $this->successResponse('Success', $appointment_statuse, 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentStatusesController');
        }
    }

    /**
     * Update Appointment_statuse in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            if (! Gate::allows('appointment_statuses_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), 200);
            }

            if (AppointmentStatuses::updateRecord($id, $request, Auth::User()->account_id)) {
                return $this->successResponse('Record has been updated successfully.', null, 200);
            }

            return $this->errorResponse('Something went wrong, please try again later.', 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentStatusesController');
        }
    }

    /**
     * Remove Appointment_statuse from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            if (! Gate::allows('appointment_statuses_destroy')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }
            $response = AppointmentStatuses::deleteRecord($id);

            if ($response->get('status')) {
                return $this->successResponse($response->get('message'), null, 200);
            }
            return $this->errorResponse($response->get('message'), 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentStatusesController');
        }
    }

    /**
     * Change status of Appointment Statuses
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        try {
            if ($request->status == 0) {
                if (! Gate::allows('cities_inactive')) {
                    return $this->errorResponse('You are not authorized to access this resource.', 401);
                }
                $response = AppointmentStatuses::inactiveRecord($request->id);
            } else {
                if (! Gate::allows('cities_active')) {
                    return $this->errorResponse('You are not authorized to access this resource.', 401);
                }
                $response = AppointmentStatuses::activeRecord($request->id);
            }

            if ($response->get('status')) {
                return $this->successResponse($response->get('message'), null, 200);
            }
            return $this->errorResponse($response->get('message'), 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentStatusesController');
        }
    }
}
