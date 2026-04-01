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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class MembershipTypesController extends Controller
{
    public function __construct(
        private readonly MembershipTypeService $membershipTypeService,
    ) {}

    // ── Datatable ───────────────────────────────────────

    public function datatable(MembershipTypeDatatableRequest $request): JsonResponse
    {
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
                    'edit'   => Gate::allows('membershiptypes_edit'),
                    'delete' => Gate::allows('membershiptypes_destroy'),
                    'active' => Gate::allows('membershiptypes_active'),
                    'inactive' => Gate::allows('membershiptypes_inactive'),
                    'create' => Gate::allows('membershiptypes_create'),
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
        if (Gate::denies('membershiptypes_create')) {
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
        if (Gate::denies('membershiptypes_edit')) {
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
        if (Gate::denies('membershiptypes_edit')) {
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
        if (Gate::denies('membershiptypes_destroy')) {
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
        if (Gate::denies('membershiptypes_active')) {
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
        $patientId = $request->integer('patient_id') ?: null;

        $data = $this->membershipTypeService->getActiveTypesForPatient($patientId);

        return $this->success('Membership types retrieved successfully.', $data);
    }

    // ── Response Helpers ────────────────────────────────

    private function success(string $message, mixed $data = null, bool $status = true): JsonResponse
    {
        return response()->json([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ], 200);
    }

    private function error(string $message): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => $message,
            'data'    => null,
        ], 500);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => 'You are not authorized to access this resource.',
            'data'    => null,
        ], 401);
    }
}
