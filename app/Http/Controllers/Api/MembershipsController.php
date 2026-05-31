<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exports\ExportMembership;
use App\Exports\StudentMembershipPatientsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\CancelMembershipRequest;
use App\Http\Requests\Membership\MembershipDatatableRequest;
use App\Http\Requests\Membership\StoreMembershipRequest;
use App\Http\Requests\Membership\ToggleMembershipStatusRequest;
use App\Http\Requests\Membership\UpdateMembershipRequest;
use App\Http\Requests\Membership\UploadMembershipsRequest;
use App\Http\Resources\MembershipResource;
use App\Services\Membership\MembershipService;
use App\Traits\SimpleApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class MembershipsController extends Controller
{
    use SimpleApiResponse;

    public function __construct(
        private readonly MembershipService $membershipService,
    ) {}

    // ── Datatable ───────────────────────────────────────

    public function datatable(MembershipDatatableRequest $request): JsonResponse
    {
        if (Gate::denies('memberships.list.view')) {
            return $this->unauthorized();
        }

        try {
            $filters     = $request->filters();
            $applyFilter = checkFilters($filters, 'memberships');

            $datatableData = $this->membershipService->getDatatableData($request->all(), $applyFilter);
            $pagination    = $request->paginationParams($datatableData['total']);

            [$orderBy, $order] = getSortBy($request);

            $rows = $this->membershipService->getDatatableRows(
                $datatableData['where'],
                $pagination['start'],
                $pagination['perpage'],
                $datatableData['filters'],
                $applyFilter,
                $datatableData['userCentres'],
            );

            $records = [
                'data'           => MembershipResource::collection($rows)->resolve(),
                'active_filters' => $this->membershipService->getActiveFilterValues(),
                'filter_values'  => $this->membershipService->getDatatableFilterValues(),
            ];

            if ($rows->isNotEmpty()) {
                $records['permissions'] = [
                    'edit'         => Gate::allows('memberships.edit'),
                    'delete'       => Gate::allows('memberships.destroy'),
                    'active'       => Gate::allows('memberships.activate'),
                    'inactive'     => Gate::allows('memberships.deactivate'),
                    'create'       => Gate::allows('memberships.create'),
                    'sort'         => Gate::allows('memberships.sort'),
                    'view_details' => Gate::allows('memberships.detail.view'),
                ];

                $records['meta'] = [
                    'field'   => $orderBy,
                    'page'    => $pagination['page'],
                    'pages'   => $pagination['pages'],
                    'perpage' => $pagination['perpage'],
                    'total'   => $datatableData['total'],
                    'sort'    => $order,
                ];
            }

            return response()->json($records);
        } catch (\Throwable $e) {
            Log::error('Membership datatable error', ['message' => $e->getMessage()]);

            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Create Form Data ────────────────────────────────

    public function create(): JsonResponse
    {
        if (Gate::denies('memberships.create')) {
            return $this->unauthorized();
        }

        $data = $this->membershipService->getCreateFormData();

        return $this->success('Record found.', $data);
    }

    // ── Store ───────────────────────────────────────────

    public function store(StoreMembershipRequest $request): JsonResponse
    {
        if (Gate::denies('memberships.create')) {
            return $this->unauthorized();
        }

        try {
            $this->membershipService->createMembership($request->validated());

            return $this->success('Record has been created successfully.');
        } catch (\Throwable $e) {
            Log::error('Membership store error', ['message' => $e->getMessage()]);

            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Edit ────────────────────────────────────────────

    public function edit(int $id): JsonResponse
    {
        if (Gate::denies('memberships.edit')) {
            return $this->unauthorized();
        }

        try {
            $data = $this->membershipService->getEditFormData($id);

            return $this->success('Record found', $data);
        } catch (\Throwable $e) {
            Log::error('Membership edit error', ['message' => $e->getMessage()]);

            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Update ──────────────────────────────────────────

    public function update(UpdateMembershipRequest $request, int $id): JsonResponse
    {
        if (Gate::denies('memberships.edit')) {
            return $this->unauthorized();
        }

        try {
            $this->membershipService->updateMembership($id, $request->validated());

            return $this->success('Record has been updated successfully.');
        } catch (\Throwable $e) {
            Log::error('Membership update error', ['message' => $e->getMessage()]);

            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Destroy ─────────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        if (Gate::denies('memberships.destroy')) {
            return $this->unauthorized();
        }

        try {
            $this->membershipService->deleteMembership($id);

            return $this->success('Record has been deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('Membership destroy error', ['message' => $e->getMessage()]);

            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Status Toggle ───────────────────────────────────

    public function status(ToggleMembershipStatusRequest $request): JsonResponse
    {
        // Legacy `memberships_active` covered both directions; new
        // catalog splits activate/deactivate. Pick the perm by status.
        $targetStatus = (int) $request->validated()['status'];
        $needed = $targetStatus === 1 ? 'memberships.activate' : 'memberships.deactivate';
        if (Gate::denies($needed)) {
            return $this->unauthorized();
        }

        $success = $this->membershipService->toggleStatus(
            (int) $request->validated()['id'],
            (int) $request->validated()['status'],
        );

        return $success
            ? $this->success('Status has been changed successfully.')
            : $this->success('You can not change status of this membership', status: false);
    }

    // ── Cancel Membership ───────────────────────────────

    public function cancelMembership(CancelMembershipRequest $request): JsonResponse
    {
        // Cancel-membership has historically been ungated at the controller
        // level; the action belongs to whoever can edit a membership.
        // The patient-card variant is gated separately on
        // `patients.membership.cancel` (see Patients audit).
        if (Gate::denies('memberships.edit')) {
            return $this->unauthorized();
        }

        try {
            $result = $this->membershipService->cancelMembership($request->patientId());

            return $this->success($result['message']);
        } catch (\Throwable $e) {
            Log::error('Membership cancel error', ['message' => $e->getMessage()]);

            return $this->success('An error occurred. Please try again.', status: false);
        }
    }

    // ── Sold By Users ───────────────────────────────────

    public function getSoldByUsers(Request $request): JsonResponse
    {
        try {
            $users = $this->membershipService->getSoldByUsers($request->integer('location_id') ?: null);

            return $this->success('Users retrieved successfully.', ['users' => $users]);
        } catch (\Throwable $e) {
            Log::error('Membership getSoldByUsers error', ['message' => $e->getMessage()]);

            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Student Verification Details ────────────────────

    public function getStudentVerificationDetails(int $id): JsonResponse
    {
        try {
            $data = $this->membershipService->getStudentVerificationDetails($id);

            return $this->success('Details found', $data);
        } catch (\Throwable $e) {
            Log::error('Student verification details error', ['message' => $e->getMessage()]);

            return $this->error('Failed to fetch details');
        }
    }

    // ── Export PDF ──────────────────────────────────────

    public function exportPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        if (Gate::denies('memberships.export')) {
            abort(403, 'You are not authorized to export memberships.');
        }

        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $query = Membership::with('membershipType');

        if ($request->filled('membership_type_id')) {
            $query->where('membership_type_id', $request->input('membership_type_id'));
        }

        if ($request->filled('code')) {
            $query->where('code', $request->input('code'));
        }

        if ($request->filled('assigned')) {
            $request->input('assigned') == 1
                ? $query->assigned()
                : $query->unassigned();
        }

        if ($request->filled('status')) {
            $query->where('memberships.active', (int) $request->input('status'));
        }

        $membershipsData = $query->get();

        $customPaper = [0, 0, 720, 1440];
        $pdf = Pdf::loadView('admin.memberships.membership-pdf', compact('membershipsData'))
            ->setPaper($customPaper, 'portrait');

        return $pdf->download('memberships.pdf');
    }

    // ── Export Excel ────────────────────────────────────

    public function exportDocs(Request $request): BinaryFileResponse
    {
        if (Gate::denies('memberships.export')) {
            abort(403, 'You are not authorized to export memberships.');
        }

        set_time_limit(0);
        ini_set('memory_limit', '-1');

        return Excel::download(
            new ExportMembership($request),
            'memberships.' . $request->input('ext', 'xlsx'),
        );
    }

}
