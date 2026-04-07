<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUpdateAppointmentStatusNameRequest;
use App\Services\AppointmentStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppointmentStatusesController extends Controller
{
    public function __construct(
        private readonly AppointmentStatusService $appointmentStatusService,
    ) {}

    public function index(): mixed
    {
        if (! Gate::allows('appointment_statuses_manage')) {
            return abort(401);
        }

        return view('admin.appointment_statuses.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('appointment_statuses_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $filters     = getFilters($request->all());
            $applyFilter = checkFilters($filters, 'appointment_statuses');
            $accountId   = (int) auth()->user()->account_id;

            [$orderBy, $order] = getSortBy($request);

            $records = ['data' => []];

            if (hasFilter($filters, 'delete')) {
                $this->appointmentStatusService->processBulkDelete($filters, $accountId);
                $records['status']  = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            $total                  = $this->appointmentStatusService->getTotalRecords($request, $accountId, $applyFilter);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $total);

            $records['data']          = $this->appointmentStatusService->getDatatableRows($request, $iDisplayStart, $iDisplayLength, $accountId, $applyFilter);
            $records['permissions']   = [
                'edit'     => Gate::allows('appointment_statuses_edit'),
                'delete'   => Gate::allows('appointment_statuses_destroy'),
                'active'   => Gate::allows('appointment_statuses_active'),
                'inactive' => Gate::allows('appointment_statuses_inactive'),
            ];
            $records['active_filters'] = $this->appointmentStatusService->getActiveFilters();
            $records['filter_values']  = $this->appointmentStatusService->getFilterValues($accountId);
            $records['meta']           = [
                'field'   => $orderBy,
                'page'    => $page,
                'pages'   => $pages,
                'perpage' => $iDisplayLength,
                'total'   => $total,
                'sort'    => $order,
            ];

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentStatusesController');
        }
    }

    public function create(): mixed
    {
        if (! Gate::allows('appointment_statuses_create')) {
            return abort(401);
        }

        $appointment_statuse              = new \stdClass();
        $appointment_statuse->is_default  = 0;
        $appointment_statuse->is_arrived  = 0;
        $appointment_statuse->is_cancelled   = 0;
        $appointment_statuse->is_unscheduled = 0;
        $appointment_statuse->is_converted   = 0;

        $parentAppointmentStatuses = $this->appointmentStatusService->getParentRecords((int) auth()->user()->account_id);

        return view('admin.appointment_statuses.create', compact('parentAppointmentStatuses', 'appointment_statuse'));
    }

    public function store(StoreUpdateAppointmentStatusNameRequest $request): JsonResponse
    {
        try {
            if (! Gate::allows('appointment_statuses_create')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            if ($this->appointmentStatusService->create($request, (int) auth()->user()->account_id)) {
                return $this->successResponse('Record has been created successfully.', null, 200);
            }

            return $this->errorResponse('Something went wrong, please try again later.', 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentStatusesController');
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('appointment_statuses_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $data = $this->appointmentStatusService->getForEdit($id, (int) auth()->user()->account_id);

            if (! $data) {
                return $this->errorResponse('No Record Found!', 200);
            }

            return $this->successResponse('Success', $data, 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentStatusesController');
        }
    }

    public function update(StoreUpdateAppointmentStatusNameRequest $request, int $id): JsonResponse
    {
        try {
            if (! Gate::allows('appointment_statuses_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            if ($this->appointmentStatusService->update($id, $request, (int) auth()->user()->account_id)) {
                return $this->successResponse('Record has been updated successfully.', null, 200);
            }

            return $this->errorResponse('Something went wrong, please try again later.', 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentStatusesController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('appointment_statuses_destroy')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $response = $this->appointmentStatusService->delete($id);

            if ($response->get('status')) {
                return $this->successResponse($response->get('message'), null, 200);
            }

            return $this->errorResponse($response->get('message'), 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentStatusesController');
        }
    }

    public function status(Request $request): JsonResponse
    {
        try {
            if ($request->status == 0) {
                if (! Gate::allows('cities_inactive')) {
                    return $this->errorResponse('You are not authorized to access this resource.', 401);
                }
            } else {
                if (! Gate::allows('cities_active')) {
                    return $this->errorResponse('You are not authorized to access this resource.', 401);
                }
            }

            $response = $this->appointmentStatusService->toggleStatus(
                (int) $request->id,
                (int) $request->status,
            );

            if ($response->get('status')) {
                return $this->successResponse($response->get('message'), null, 200);
            }

            return $this->errorResponse($response->get('message'), 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentStatusesController');
        }
    }
}
