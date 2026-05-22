<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\MembershipTypeDatatableRequest;
use App\Http\Requests\Membership\StoreMembershipTypeRequest;
use App\Http\Requests\Membership\ToggleMembershipTypeStatusRequest;
use App\Http\Requests\Membership\UpdateMembershipTypeRequest;
use App\Http\Resources\MembershipTypeResource;
use App\Services\Membership\MembershipTypeService;
use App\Traits\SimpleApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class MembershipTypesController extends Controller
{
    use SimpleApiResponse;

    public function __construct(
        private readonly MembershipTypeService $membershipTypeService,
    ) {}

    // ── Datatable ───────────────────────────────────────

    public function datatable(MembershipTypeDatatableRequest $request): JsonResponse
    {
        if (Gate::denies('membership_types.list.view')) {
            return $this->unauthorized();
        }

        try {
            $filters     = $request->filters();
            $applyFilter = checkFilters($filters, 'membership_types');

            $datatableData = $this->membershipTypeService->getDatatableData($request->all(), $applyFilter);
            $pagination    = $request->paginationParams($datatableData['total']);

            [$orderBy, $order] = getSortBy($request);

            $rows = $this->membershipTypeService->getDatatableRows(
                $datatableData['where'],
                $pagination['start'],
                $pagination['perpage'],
            );

            $records = [
                'data'           => MembershipTypeResource::collection($rows)->resolve(),
                'active_filters' => $applyFilter,
                'filter_values'  => ['status' => config('constants.status')],
            ];

            if ($rows->isNotEmpty()) {
                $records['permissions'] = [
                    'edit'   => Gate::allows('membership_types.edit'),
                    'delete' => Gate::allows('membership_types.destroy'),
                    'active' => Gate::allows('membership_types.activate'),
                    'inactive' => Gate::allows('membership_types.deactivate'),
                    'create' => Gate::allows('membership_types.create'),
                ];

                $records['meta'] = [
                    'field'   => $orderBy,
                    'page'    => $pagination['page'],
                    'pages'   => $pagination['pages'],
                    'perpage' => $pagination['perpage'],
                    'total'   => $datatableData['total'],
                ];
            }

            return response()->json($records);
        } catch (\Throwable $e) {
            Log::error('MembershipType datatable error', ['message' => $e->getMessage()]);

            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Store ───────────────────────────────────────────

    public function store(StoreMembershipTypeRequest $request): JsonResponse
    {
        if (Gate::denies('membership_types.create')) {
            return $this->unauthorized();
        }

        try {
            $this->membershipTypeService->createMembershipType($request->validated());

            return $this->success('Record has been created successfully.');
        } catch (\Throwable $e) {
            Log::error('MembershipType store error', ['message' => $e->getMessage()]);

            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Edit ────────────────────────────────────────────

    public function edit(int $id): JsonResponse
    {
        if (Gate::denies('membership_types.edit')) {
            return $this->unauthorized();
        }

        try {
            $data = $this->membershipTypeService->getEditFormData($id);

            return $this->success('Record found', $data);
        } catch (\Throwable $e) {
            Log::error('MembershipType edit error', ['message' => $e->getMessage()]);

            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Update ──────────────────────────────────────────

    public function update(UpdateMembershipTypeRequest $request, int $id): JsonResponse
    {
        if (Gate::denies('membership_types.edit')) {
            return $this->unauthorized();
        }

        try {
            $this->membershipTypeService->updateMembershipType($id, $request->validated());

            return $this->success('Record has been updated successfully.');
        } catch (\Throwable $e) {
            Log::error('MembershipType update error', ['message' => $e->getMessage()]);

            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Destroy ─────────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        if (Gate::denies('membership_types.destroy')) {
            return $this->unauthorized();
        }

        try {
            $result = $this->membershipTypeService->deleteMembershipType($id);

            $message = match ($result) {
                'deactivated' => 'Record has been deactivated successfully',
                'deleted'     => 'Record has been deleted successfully',
                default       => 'Operation completed',
            };

            return $this->success($message);
        } catch (\Throwable $e) {
            Log::error('MembershipType destroy error', ['message' => $e->getMessage()]);

            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Status Toggle ───────────────────────────────────

    public function status(ToggleMembershipTypeStatusRequest $request): JsonResponse
    {
        // Legacy `membershiptypes_active` covered both directions; new
        // catalog splits activate/deactivate. Pick the perm by status.
        $targetStatus = (int) $request->validated()['status'];
        $needed = $targetStatus === 1 ? 'membership_types.activate' : 'membership_types.deactivate';
        if (Gate::denies($needed)) {
            return $this->unauthorized();
        }

        $success = $this->membershipTypeService->toggleStatus(
            (int) $request->validated()['id'],
            (int) $request->validated()['status'],
        );

        return $success
            ? $this->success('Status has been changed successfully.')
            : $this->success('Resource not found.', status: false);
    }

    // ── Get Active Types ────────────────────────────────

    public function getActiveTypes(Request $request): JsonResponse
    {
        try {
            $patientId = $request->integer('patient_id') ?: null;

            $data = $this->membershipTypeService->getActiveTypesForPatient($patientId);

            return $this->success('Membership types retrieved successfully.', $data);
        } catch (\Throwable $e) {
            Log::error('MembershipType getActiveTypes error', ['message' => $e->getMessage()]);

            return $this->error('Something went wrong, please try again later.');
        }
    }

}
