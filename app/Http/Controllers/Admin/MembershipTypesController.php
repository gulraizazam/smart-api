<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMembershipTypeRequest;
use App\Http\Requests\Admin\UpdateMembershipTypeRequest;
use App\Services\Membership\MembershipTypeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MembershipTypesController extends Controller
{
    public function __construct(
        private readonly MembershipTypeService $membershipTypeService,
    ) {}

    public function index(): \Illuminate\View\View
    {
        if (! Gate::allows('membership_types.list.view')) {
            return abort(403);
        }

        return view('admin.memberships_types.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        if (! Gate::allows('membership_types.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $filters     = getFilters($request->all());
        $applyFilter = checkFilters($filters, 'membership_types');

        $records = ['data' => []];

        [$orderBy, $order] = getSortBy($request);

        $datatableData = $this->membershipTypeService->getDatatableData($request->all(), $applyFilter);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $datatableData['total']);

        $membershipTypes = $this->membershipTypeService->getDatatableRows(
            $datatableData['where'],
            $iDisplayStart,
            $iDisplayLength,
        );

        $records['active_filters'] = $applyFilter;
        $records['filter_values']  = ['status' => config('constants.status')];

        if ($membershipTypes->count()) {
            foreach ($membershipTypes as $membershipType) {
                $children = [];
                if ($membershipType->children && $membershipType->children->count()) {
                    foreach ($membershipType->children as $child) {
                        $children[] = [
                            'id'         => $child->id,
                            'name'       => $child->name,
                            'period'     => $child->period,
                            'amount'     => $child->amount,
                            'active'     => $child->active,
                            'created_at' => Carbon::parse($child->created_at)->format('F j,Y h:i A'),
                        ];
                    }
                }

                $records['data'][] = [
                    'id'           => $membershipType->id,
                    'name'         => $membershipType->name,
                    'period'       => $membershipType->period,
                    'amount'       => $membershipType->amount,
                    'active'       => $membershipType->active,
                    'created_at'   => Carbon::parse($membershipType->created_at)->format('F j,Y h:i A'),
                    'parent_id'    => $membershipType->parent_id,
                    'parent_name'  => $membershipType->parent?->name ?? '-',
                    'children'     => $children,
                    'has_children' => !empty($children),
                ];
            }

            $records['permissions'] = [
                'edit'     => Gate::allows('membership_types.edit'),
                'delete'   => Gate::allows('membership_types.destroy'),
                'active'   => Gate::allows('membership_types.activate'),
                'inactive' => Gate::allows('membership_types.deactivate'),
                'create'   => Gate::allows('membership_types.create'),
            ];

            $records['meta'] = [
                'field'   => $orderBy,
                'page'    => $page,
                'pages'   => $pages,
                'perpage' => $iDisplayLength,
                'total'   => $datatableData['total'],
            ];
        }

        return response()->json($records);
    }

    public function store(StoreMembershipTypeRequest $request): JsonResponse
    {
        if (! Gate::allows('membership_types.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $record = $this->membershipTypeService->createMembershipType($request->validated());

        if ($record) {
            return $this->successResponse('Record has been created successfully.', null, 200);
        }

        return $this->errorResponse('Something went wrong, please try again later.', 200);
    }

    public function edit(int $id): JsonResponse
    {
        if (! Gate::allows('membership_types.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $data = $this->membershipTypeService->getEditFormData($id);

        return $this->successResponse('Record found', $data, 200);
    }

    public function update(UpdateMembershipTypeRequest $request, int $id): JsonResponse
    {
        if (! Gate::allows('membership_types.edit')) {
            return abort(403);
        }

        $this->membershipTypeService->updateMembershipType($id, $request->all());

        return $this->successResponse('Record has been updated successfully.', null, 200);
    }

    public function destroy(int $id): JsonResponse
    {
        if (! Gate::allows('membership_types.destroy')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $result = $this->membershipTypeService->deleteMembershipType($id);

        if ($result === 'deactivated') {
            return $this->successResponse('Record has been deactivated successfully', null, 500);
        }

        if ($result === 'deleted') {
            return $this->successResponse('Record has been deleted successfully', null, 500);
        }

        return $this->errorResponse('Resource not found', 200);
    }

    public function status(Request $request): JsonResponse
    {
        // Legacy `membershiptypes_active` covered both directions; new
        // catalog splits activate/deactivate. Pick the perm by status.
        $targetStatus = (int) $request->input('status');
        $needed = $targetStatus === 1 ? 'membership_types.activate' : 'membership_types.deactivate';
        if (! Gate::allows($needed)) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $success = $this->membershipTypeService->toggleStatus(
            (int) $request->id,
            (int) $request->status,
        );

        if ($success) {
            return $this->successResponse('Status has been changed successfully.', null, 200);
        }

        return $this->errorResponse('Resource not found.', 200);
    }

    public function getActiveTypes(Request $request): JsonResponse
    {
        $patientId = $request->get('patient_id');

        $data = $this->membershipTypeService->getActiveTypesForPatient($patientId ? (int) $patientId : null);

        return $this->successResponse('Membership types retrieved successfully.', $data, 200);
    }
}
