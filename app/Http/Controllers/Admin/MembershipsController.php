<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\ExportMembership;
use App\Exports\StudentMembershipPatientsExport;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportMembershipsRequest;
use App\Http\Requests\Admin\StoreMembershipRequest;
use App\Models\Locations;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\User;
use App\Services\Membership\AdminMembershipService;
use App\Helpers\ACL;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;

class MembershipsController extends Controller
{
    public function __construct(
        private readonly AdminMembershipService $adminMembershipService,
    ) {}

    public function index(): \Illuminate\View\View
    {
        if (! Gate::allows('memberships.list.view')) {
            return abort(403);
        }

        return view('admin.memberships.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        if (! Gate::allows('memberships.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $filters     = getFilters($request->all());
            $applyFilter = checkFilters($filters, 'memberships');
            $accountId   = Auth::user()->account_id;

            [$orderBy, $order] = getSortBy($request);

            $total = $this->adminMembershipService->getTotalRecords($request, $accountId, $applyFilter);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $total);

            $memberships = $this->adminMembershipService->getRecords($request, $iDisplayStart, $iDisplayLength, $accountId, $applyFilter);

            $Users          = User::getAllRecords($accountId)->pluck('name', 'id');
            $membershipType = MembershipType::where(['active' => 1])->pluck('name', 'id');
            $locations      = Locations::getActiveSorted(ACL::getUserCentres());
            $soldByUsers    = User::where('account_id', $accountId)
                ->where('active', 1)
                ->whereIn('user_type_id', [config('constants.doctor_user_id'), config('constants.fdm_user_id')])
                ->orderBy('name')
                ->pluck('name', 'id');

            $records = [
                'data'           => [],
                'active_filters' => [
                    'patient_id'          => Filters::get(Auth::user()->id, 'memberships', 'patient_id'),
                    'code'                => Filters::get(Auth::user()->id, 'memberships', 'code'),
                    'membership_type_id'  => Filters::get(Auth::user()->id, 'memberships', 'membership_type_id'),
                    'status'              => Filters::get(Auth::user()->id, 'memberships', 'status'),
                    'location_id'         => Filters::get(Auth::user()->id, 'memberships', 'location_id'),
                    'sold_by'             => Filters::get(Auth::user()->id, 'memberships', 'sold_by'),
                    'assigned_at'         => Filters::get(Auth::user()->id, 'memberships', 'assigned_at'),
                    'created_by'          => Filters::get(Auth::user()->id, 'memberships', 'created_by'),
                ],
                'filter_values' => [
                    'status'        => config('constants.status'),
                    'users'         => $Users,
                    'membershipType' => $membershipType,
                    'locations'     => $locations,
                    'soldByUsers'   => $soldByUsers,
                ],
            ];

            if ($memberships->count()) {
                // Batch-load patients to avoid N+1
                $patientIds = $memberships->pluck('patient_id')->unique()->filter()->all();
                $patientsMap = User::whereIn('id', $patientIds)->get()->keyBy('id');

                foreach ($memberships as $membership) {
                    $patient              = $patientsMap->get($membership->patient_id);
                    $membershipTypeName   = $membership->membershipType->name ?? 'N/A';
                    $isStudentMembership  = str_contains(strtolower($membershipTypeName), 'student');

                    $records['data'][] = [
                        'id'                      => $membership->id,
                        'code'                    => $membership->code,
                        'active'                  => $membership->active,
                        'start_date'              => $membership->start_date,
                        'end_date'                => $membership->end_date,
                        'membership_type_id'      => $membershipTypeName,
                        'membership_type_id_raw'  => $membership->membership_type_id,
                        'is_student_membership'   => $isStudentMembership,
                        'patient'                 => $patient?->name ?? 'N/A',
                        'patient_id'              => $patient?->id ?? 'N/A',
                        'patient_unique_id'       => $patient?->unique_id,
                        'created_at'              => Carbon::parse($membership->created_at)->format('F j,Y h:i A'),
                    ];
                }

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
                    'page'    => $page,
                    'pages'   => $pages,
                    'perpage' => $iDisplayLength,
                    'total'   => $total,
                    'sort'    => $order,
                ];
            }

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'MembershipsController');
        }
    }

    public function create(): JsonResponse
    {
        if (! Gate::allows('memberships.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $membershipType = MembershipType::parentsOnly()
            ->where('active', 1)
            ->pluck('name', 'id');

        return $this->successResponse('Record found.', ['membershipType' => $membershipType], 200);
    }

    public function store(StoreMembershipRequest $request): JsonResponse
    {
        if (! Gate::allows('memberships.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $data               = $request->all();
        $data['account_id'] = Auth::user()->account_id;
        $data['created_by'] = Auth::id();

        $record = Membership::create($data);

        if ($record) {
            return $this->successResponse('Record has been created successfully.', null, 200);
        }

        return $this->errorResponse('Something went wrong, please try again later.', 200);
    }

    public function status(Request $request): JsonResponse
    {
        // Legacy `memberships_active` covered both directions; new
        // catalog splits activate/deactivate. Pick the perm by status.
        $targetStatus = (int) $request->input('status');
        $needed = $targetStatus === 1 ? 'memberships.activate' : 'memberships.deactivate';
        if (! Gate::allows($needed)) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        if ($request->status == '0') {
            $response = $this->adminMembershipService->deactivate((int) $request->id);
        } else {
            $response = $this->adminMembershipService->activate((int) $request->id, (int) $request->status);
        }

        if ($response) {
            return $this->successResponse('Status has been changed successfully.', null, 200);
        }

        return $this->errorResponse('You can not change status of this membership', 500);
    }

    public function cancelMembership(Request $request): JsonResponse
    {
        $result = $this->adminMembershipService->cancelMembership((int) $request->id);

        if (! $result['success']) {
            return $this->errorResponse($result['message'], 500);
        }

        return $this->successResponse($result['message'], null, 200);
    }

    public function edit(int $id): JsonResponse
    {
        if (! Gate::allows('memberships.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $membership     = Membership::find($id);
        $membershipType = MembershipType::parentsOnly()->where('active', 1)->pluck('name', 'id');

        return $this->successResponse('Record found', [
            'membership'     => $membership,
            'membershipType' => $membershipType,
        ], 200);
    }

    public function update(StoreMembershipRequest $request, int $id): JsonResponse
    {
        if (! Gate::allows('memberships.edit')) {
            return abort(403);
        }

        $data               = $request->all();
        $data['updated_by'] = Auth::id();

        $record = Membership::find($id);

        if ($record) {
            $record->update($data);

            return $this->successResponse('Record has been updated successfully.', null, 200);
        }

        return $this->errorResponse('Something went wrong, please try again later.', 500);
    }

    public function destroy(int $id): JsonResponse
    {
        if (! Gate::allows('memberships.destroy')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $membership = Membership::find($id);

        if ($membership) {
            $membership->delete();

            return $this->successResponse('Record has been deleted Successfully', null, 500);
        }

        return $this->errorResponse('Membership not found', 500);
    }

    public function uploadMemberships(ImportMembershipsRequest $request): JsonResponse
    {
        if (! Gate::allows('memberships.import')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $allCodesList      = [];
            $checkMemberships  = [];
            $file              = $request->file('memberships_file');
            $collections       = (new FastExcel)->import($file);
            $rows              = [];

            foreach ($collections as $collection) {
                $data = [];
                foreach ($collection as $key => $value) {
                    $convertedKey          = strtolower(str_replace(' ', '_', trim($key)));
                    $data[$convertedKey]   = $value;
                }
                $rows[] = $data;
            }

            foreach ($rows as $row) {
                if (strlen($row['code'])) {
                    $allCodesList[] = $row['code'];
                }
            }

            if (count($allCodesList)) {
                $checkMemberships = Membership::whereIn('code', $allCodesList)
                    ->select('code')
                    ->orderBy('id', 'desc')
                    ->get()
                    ->unique('code')
                    ->pluck('code');

                if ($checkMemberships) {
                    $checkMemberships = $checkMemberships->toArray();
                }
            }

            foreach ($rows as $row) {
                $membershipTypeId = MembershipType::where(['name' => $row['membership_type']])->first()->id ?? null;

                if ($membershipTypeId) {
                    Membership::orderBy('id', 'desc')->updateOrCreate(['code' => $row['code']], [
                        'code'               => $row['code'],
                        'membership_type_id' => $membershipTypeId,
                        'created_by'         => Auth::id(),
                    ]);
                }
            }

            return $this->successResponse('Memberships has been imported', null, 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return $this->errorResponse('An error occurred. Please try again.', 200);
        }
    }

    public function getSoldByUsers(Request $request): JsonResponse
    {
        try {
            $users = $this->adminMembershipService->getSoldByUsers(
                $request->filled('location_id') ? (int) $request->location_id : null,
            );

            return response()->json(['success' => true, 'data' => ['users' => $users]]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    public function exportPdf(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $query = Membership::with('membershiptype');

        if ($request->membership_type_id !== null && $request->membership_type_id !== '') {
            $query->where('membership_type_id', $request->membership_type_id);
        }

        if ($request->code !== null && $request->code !== '') {
            $query->where('code', $request->code);
        }

        if ($request->assigned !== null && $request->assigned !== '') {
            if ($request->assigned == 1) {
                $query->whereNotNull('memberships.patient_id');
            } elseif ($request->assigned == 0) {
                $query->whereNull('memberships.patient_id');
            }
        }

        if ($request->status !== null && $request->status !== '') {
            if ($request->status == 1) {
                $query->where('memberships.active', '==', 1);
            } elseif ($request->status == 0) {
                $query->where('memberships.active', '==', 0);
            }
        }

        $membershipsData = $query->get();
        $customPaper     = [0, 0, 720, 1440];

        $pdf = Pdf::loadView('admin.memberships.membership-pdf', compact('membershipsData'))
            ->setPaper($customPaper, 'portrait');

        return $pdf->download('memberships.pdf');
    }

    public function exportDocs(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        return Excel::download(new ExportMembership($request), 'memberships.' . $request->ext);
    }

    public function downloadStudentMembershipPatients(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download(
            new StudentMembershipPatientsExport(),
            'student_membership_patients_' . date('Y-m-d') . '.xlsx',
        );
    }

    public function getStudentVerificationDetails(int $membershipId): JsonResponse
    {
        try {
            $data = $this->adminMembershipService->getStudentVerificationDetails($membershipId);

            return $this->successResponse('Details found', $data, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching student verification details: ' . $e->getMessage());

            return $this->errorResponse('Failed to fetch details', 500);
        }
    }
}
