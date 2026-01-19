<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ExportMembership;
use App\HelperModule\ApiHelper;
use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\Patients;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\User;
use Carbon\Carbon;
use DateTime;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Exports\StudentMembershipPatientsExport;


class MembershipsController extends Controller
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

    public function index()
    {
        if (!Gate::allows('memberships_manage')) {
            return abort(401);
        }
        return view('admin.memberships.index');
    }

    public function datatable(Request $request)
    {
        try {

            $filename = 'memberships';
            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);
            $records = [];
            $records['data'] = [];
            [$orderBy, $order] = getSortBy($request);
            $iTotalRecords = $this->getTotalRecords($request, Auth::User()->account_id, $apply_filter);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);
            $memberships = $this->getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);
            $Users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
            $membershipType = MembershipType::where(['active' => 1])->pluck('name', 'id');
            $records['active_filters'] = $apply_filter;
            $records['filter_values'] = [
                'status' => config('constants.status'),
                'users' => $Users,
                'membershipType' => $membershipType
            ];
            if ($memberships->count()) {
                foreach ($memberships as $membership) {
                    $patient = User::whereId($membership->patient_id)->first();
                    $records['data'][] = [
                        'id' => $membership->id,
                        'code' => $membership->code,
                        'active' => $membership->active,
                        'start_date' => $membership->start_date,
                        'end_date' => $membership->end_date,
                        'membership_type_id' => $membership->membershipType->name ?? 'N/A',
                        'patient' => $patient ? $patient->name : 'N/A',
                        'patient_id' => $patient ? $patient->id : 'N/A',
                        'created_at' => Carbon::parse($membership->created_at)->format('F j,Y h:i A'),
                    ];
                }
                $records['permissions'] = [
                    'edit' => Gate::allows('memberships_edit'),
                    'delete' => Gate::allows('memberships_destroy'),
                    'active' => Gate::allows('memberships_active'),
                    'inactive' => Gate::allows('memberships_inactive'),
                    'create' => Gate::allows('memberships_create'),
                    'sort' => Gate::allows('memberships_sort'),
                ];
                $records['meta'] = [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => $iDisplayLength,
                    'total' => $iTotalRecords,
                    'sort' => $order,
                ];
            }
            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function create()
    {
        if (!Gate::allows('memberships_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $membershipType = MembershipType::where([
            ['active', '=', '1'],
        ])->get()->pluck('name', 'id');
        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'membershipType' => $membershipType,

        ]);
    }
    public function store(Request $request)
    {
        if (!Gate::allows('memberships_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $validator = $this->verifyFields($request);
        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        $data = $request->all();
        $data['account_id'] = Auth::user()->account_id;
        $data['created_by'] = Auth::id();
        $record = Membership::create($data);
        if ($record) {
            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        } else {
            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        }
    }
    protected function verifyFields(Request $request)
    {
        return $validator = \Validator::make($request->all(), [
            'code' => ['required', Rule::unique('memberships', 'code')],
            'membership_type_id' => 'required|exists:membership_types,id',

        ]);
    }
    public function status(Request $request)
    {
        if (!Gate::allows('memberships_active')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        if ($request->status == "0") {
            $response = $this->InactiveRecord($request->id, $request->status);
        } else {
            $response = $this->activeRecord($request->id, $request->status);
        }
        if ($response) {
            return ApiHelper::apiResponse($this->success, 'Status has been changed successfully.');
        }
        return ApiHelper::apiResponse($this->error, 'You can not change status of this membership', false);
    }
    public function cancelMembership(Request $request)
    {
        // Get the membership being cancelled to find its code
        $membership = Membership::where('patient_id', $request->id)->first();

        if (!$membership) {
            return ApiHelper::apiResponse($this->error, 'Membership not found', false);
        }

        // Check if membership is inactive and expired
        $isInactiveAndExpired = ($membership->end_date < now());

        // Only check for applied services if membership is active or not expired
        if (!$isInactiveAndExpired) {
            // Check if patient has packages with Gold Membership Card or Student Membership Card services
            $packages = DB::table('packages')
                ->where('patient_id', $request->id)
                ->whereNull('deleted_at')
                ->get();

            if ($packages->count() > 0) {
                $restrictedServiceNames = ['Gold Membership Card', 'Student Membership Card'];

                foreach ($packages as $package) {
                    // Check if this package has any restricted services
                    $hasRestrictedService = DB::table('package_services')
                        ->join('services', 'package_services.service_id', '=', 'services.id')
                        ->where('package_services.package_id', $package->id)
                        ->whereIn('services.name', $restrictedServiceNames)
                        ->whereNull('services.deleted_at')
                        ->first();

                    if ($hasRestrictedService) {
                        return ApiHelper::apiResponse(
                            $this->error,
                            'Membership applied on services, you can not cancel it',
                            false
                        );
                    }
                }
            }
        }

        $membershipCode = $membership->code;
        $isReferral = $membership->is_referral;
        
        // Get patient and membership type for activity logging before deletion
        $patient = Patients::find($request->id);
        $membershipType = $membership->membershipType;

        // Cancel the membership
        Membership::where('patient_id', $request->id)->delete();

        $cancelledReferrals = 0;

        // Only cancel referrals if this is a parent membership (not a referral itself)
        if (!$isReferral) {
            // Cancel all referrals associated with this parent membership code
            $cancelledReferrals = Membership::where('parent_membership_code', $membershipCode)
                ->where('is_referral', 1)
                ->delete();
        }

        // Log activity
        if ($patient) {
            ActivityLogger::logMembershipCancelled($patient, $membership, $membershipType);
        }

        $message = 'Membership cancelled successfully';
        if ($cancelledReferrals > 0) {
            $message .= ' along with ' . $cancelledReferrals . ' associated referral(s)';
        }

        return ApiHelper::apiResponse($this->success, $message);
    }
    public function edit($id)
    {
        if (!Gate::allows('memberships_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $membership = Membership::find($id);
        $membershipType = MembershipType::where(['active' => 1])->pluck('name', 'id');
        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'membership' => $membership,
            'membershipType' => $membershipType
        ]);
    }
    public function update(Request $request, $id)
    {
        if (!Gate::allows('memberships_edit')) {
            return abort(401);
        }
        $validator = $this->verifyFields($request);
        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first());
        }
        $data = $request->all();
        $data['updated_by'] = Auth::id();

        $record = Membership::find($id);
        if ($record) {
            $record->update($data);
            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
        } else {
            return ApiHelper::apiResponse($this->error, 'Something went wrong, please try again later.', false);
        }
    }
    public function destroy($id)
    {
        if (!Gate::allows('memberships_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $membership = Membership::find($id);

        if ($membership) {
            $membership->delete();
            return ApiHelper::apiResponse($this->error, 'Record has been deleted Successfully');
        }
        return ApiHelper::apiResponse($this->error, 'Membership not found', false);
    }
    public function uploadMemberships(Request $request)
    {


        if (!Gate::allows('memberships_import')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $validator = \Validator::make($request->all(), [
            'memberships_file' => ['required', 'mimes:xls,xlsx'],
        ]);
        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        try {
            $all_codes_list = [];
            $check_memberships = [];
            $file = $request->file('memberships_file');
            $collections = (new FastExcel)->import($file);
            $rows = [];
            foreach ($collections as $collection) {

                $data = [];
                foreach ($collection as $key => $value) {
                    $convertedKey = strtolower(str_replace(' ', '_', trim($key)));
                    $data[$convertedKey] = $value;
                }
                $rows[] = $data;
            }

            foreach ($rows as $row) {
                if (strlen($row['code'])) {
                    $all_codes_list[] = $row['code'];
                }
            }
            if (count($all_codes_list)) {
                $check_memberships = Membership::whereIn('code', $all_codes_list)
                    ->select('code')
                    ->orderBy('id', 'desc')->get()->unique('code')
                    ->pluck('code');
                if ($check_memberships) {
                    $new_codes_list = array_diff($all_codes_list, $check_memberships->toArray());
                    $check_memberships = $check_memberships->toArray();
                }
            }
            foreach ($rows as $row) {
                $membership_type__id = MembershipType::where(['name' => $row['membership_type']])->first()->id ?? null;
                if ($membership_type__id) {
                    $membership_data = [
                        'code' => $row['code'],
                        'membership_type_id' => $membership_type__id,
                        'created_by' => Auth::id(),
                    ];

                    $membership = Membership::orderBy('id', 'desc')->updateOrCreate([
                        'code' => $row['code'],
                    ], $membership_data);
                }
            };
            return ApiHelper::apiResponse($this->success, 'Memberships has been imported');
        } catch (\Exception $e) {
            return ApiHelper::apiResponse($this->success, $e->getMessage(), 'false');
        }
    }
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::membershiptype_filters($request, $account_id, $apply_filter);
        $userCentres = \App\Helpers\ACL::getUserCentres();

        $query = DB::table('memberships');

        if (count($where)) {
            $query->where($where);
        }

        if (!\Illuminate\Support\Facades\Gate::allows('view_inactive_centres')) {
            $query->where('memberships.active', 1);
        }

        // Filter by user's centre access - only show memberships for patients who have appointments at user's centres
        // Non-Super-Admin users can only see assigned memberships
        $isSuperAdmin = Auth::user()->hasRole('Super-Admin');
        
        if (!empty($userCentres)) {
            if ($isSuperAdmin) {
                // Super-Admin can see unassigned memberships too
                $query->where(function ($q) use ($userCentres) {
                    $q->whereNull('memberships.patient_id')
                      ->orWhereExists(function ($subQuery) use ($userCentres) {
                          $subQuery->select(DB::raw(1))
                              ->from('appointments')
                              ->whereColumn('appointments.patient_id', 'memberships.patient_id')
                              ->whereIn('appointments.location_id', $userCentres);
                      });
                });
            } else {
                // Non-Super-Admin can only see assigned memberships with appointments at their centres
                $query->whereNotNull('memberships.patient_id')
                      ->whereExists(function ($subQuery) use ($userCentres) {
                          $subQuery->select(DB::raw(1))
                              ->from('appointments')
                              ->whereColumn('appointments.patient_id', 'memberships.patient_id')
                              ->whereIn('appointments.location_id', $userCentres);
                      });
            }
        } elseif (!$isSuperAdmin) {
            // Non-Super-Admin without centre restrictions still can't see unassigned memberships
            $query->whereNotNull('memberships.patient_id');
        }

        return $query->count();
    }
    public static function membershiptype_filters($request, $account_id, $apply_filter)
    {
        $filters = getFilters($request->all());

        $where = [];

        if (hasFilter($filters, 'code')) {
            $where[] = [
                'memberships.code',
                'like',
                '%' . $filters['code'] . '%',
            ];
            Filters::put(Auth::User()->id, 'memberships', 'code', $filters['code']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'memberships', 'code');
            } else {
                if (Filters::get(Auth::User()->id, 'memberships', 'code')) {
                    $where[] = [
                        'memberships.code',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'memberships', 'code') . '%',
                    ];
                }
            }
        }


        if (hasFilter($filters, 'membership_type_id')) {
            $where[] = [
                'memberships.membership_type_id',
                '=',
                $filters['membership_type_id'],
            ];
            Filters::put(Auth::user()->id, 'memberships', 'membership_type_id', $filters['membership_type_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'memberships', 'membership_type_id');
            } else {
                if (Filters::get(Auth::user()->id, 'memberships', 'membership_type_id')) {
                    if (Filters::get(Auth::user()->id, 'memberships', 'membership_type_id') != null) {
                        $where[] = [
                            'memberships.membership_type_id',
                            '=',
                            Filters::get(Auth::user()->id, 'memberships', 'membership_type_id'),
                        ];
                    }
                }
            }
        }
        if (hasFilter($filters, 'created_by')) {
            $where[] = [
                'memberships.created_by',
                '=',
                $filters['created_by'],
            ];
            Filters::put(Auth::user()->id, 'memberships', 'created_by', $filters['created_by']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'memberships', 'created_by');
            } else {
                if (Filters::get(Auth::user()->id, 'memberships', 'created_by')) {
                    if (Filters::get(Auth::user()->id, 'memberships', 'created_by') != null) {
                        $where[] = [
                            'memberships.created_by',
                            '=',
                            Filters::get(Auth::user()->id, 'memberships', 'created_by'),
                        ];
                    }
                }
            }
        }
        if (hasFilter($filters, 'assigned')) {
            if ($filters['assigned'] == 1) {
                // patient_id is not null
                $where[] = ['memberships.patient_id', '<>', null];
            } elseif ($filters['assigned'] == 0) {
                // patient_id is null
                $where[] = ['memberships.patient_id', '=', null];
            }
            Filters::put(Auth::user()->id, 'memberships', 'assigned', $filters['assigned']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'memberships', 'assigned');
            } else {
                if (Filters::get(Auth::user()->id, 'memberships', 'assigned') !== null) {
                    $assignedFilter = Filters::get(Auth::user()->id, 'memberships', 'assigned');
                    if ($assignedFilter == 1) {
                        $where[] = ['memberships.patient_id', '<>', null];
                    } elseif ($assignedFilter == 0) {
                        $where[] = ['memberships.patient_id', '=', null];
                    }
                }
            }
        }
        if (hasFilter($filters, 'status')) {
            if ($filters['status'] == 1) {
                // patient_id is not null
                $where[] = ['memberships.active', '<>', null];
            } elseif ($filters['status'] == 0) {
                // patient_id is null
                $where[] = ['memberships.active', '=', null];
            }
            Filters::put(Auth::user()->id, 'memberships', 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'memberships', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'memberships', 'status') !== null) {
                    $assignedFilter = Filters::get(Auth::user()->id, 'memberships', 'status');
                    if ($assignedFilter == 1) {
                        $where[] = ['memberships.active', '<>', null];
                    } elseif ($assignedFilter == 0) {
                        $where[] = ['memberships.active', '=', null];
                    }
                }
            }
        }
        if (hasFilter($filters, 'created_at')) {
            $date_range = explode(' - ', $filters['created_at']);
            $start_date_time = date('Y-m-d H:i:s', strtotime($date_range[0]));
            $end_date_string = new DateTime($date_range[1]);
            $end_date_string->setTime(23, 59, 0);
            $end_date_time = $end_date_string->format('Y-m-d H:i:s');
        } else {
            $start_date_time = null;
            $end_date_time = null;
        }
        if (hasFilter($filters, 'created_at')) {
            $where[] = ['memberships.created_at', '>=', $start_date_time];
            $where[] = ['memberships.created_at', '<=', $end_date_time];
            Filters::put(Auth::User()->id, 'memberships', 'created_at', $filters['created_at']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'memberships', 'created_at');
            } else {
                if (Filters::get(Auth::User()->id, 'memberships', 'created_at')) {
                    $where[] = [
                        'memberships.created_at',
                        '>=',
                        Filters::get(Auth::User()->id, 'memberships', 'created_at'),
                    ];
                }
            }
        }

        return $where;
    }
    public function exportPdf(Request $request)
{
    ini_set('memory_limit', '-1');
    set_time_limit(0);

    $query = Membership::with('membershiptype');

    if (!is_null($request->membership_type_id) && $request->membership_type_id !== '') {
        $query->where('membership_type_id', $request->membership_type_id);
    }

    if (!is_null($request->code) && $request->code !== '') {
        $query->where('code', $request->code);
    }

    if (!is_null($request->assigned) && $request->assigned !== '') {
        if ($request->assigned == 1) {
            $query->whereNotNull('memberships.patient_id');
        } elseif ($request->assigned == 0) {
            $query->whereNull('memberships.patient_id');
        }
    }
    if (!is_null($request->status) && $request->status !== '') {
        if ($request->status == 1) {
            $query->where('memberships.active', '==',  1);
        } elseif ($request->status == 0) {
            $query->where('memberships.active', '==',  0);
        }
    }
    $membershipsData = $query->get();

    $customPaper = [0, 0, 720, 1440];
    $pdf = PDF::loadView('admin.memberships.membership-pdf', compact('membershipsData'))
        ->setPaper($customPaper, 'portrait');

    return $pdf->download('memberships.pdf');
}
    public function exportDocs(Request $request)
    {
        
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        return Excel::download(new ExportMembership($request), 'memberships.' . $request->ext);
    }
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false)
    {
        $where = self::membershiptype_filters($request, $account_id, $apply_filter);
        $userCentres = \App\Helpers\ACL::getUserCentres();

        $orderBy = 'created_at';
        $order = 'desc';

        $query = Membership::with('membershiptype');

        if (count($where)) {
            $query->where($where);
        }

        if (!\Illuminate\Support\Facades\Gate::allows('view_inactive_machine_types')) {
            $query->where('memberships.active', 1);
        }

        // Filter by user's centre access - only show memberships for patients who have appointments at user's centres
        // Non-Super-Admin users can only see assigned memberships
        $isSuperAdmin = Auth::user()->hasRole('Super-Admin');
        
        if (!empty($userCentres)) {
            if ($isSuperAdmin) {
                // Super-Admin can see unassigned memberships too
                $query->where(function ($q) use ($userCentres) {
                    $q->whereNull('memberships.patient_id')
                      ->orWhereExists(function ($subQuery) use ($userCentres) {
                          $subQuery->select(DB::raw(1))
                              ->from('appointments')
                              ->whereColumn('appointments.patient_id', 'memberships.patient_id')
                              ->whereIn('appointments.location_id', $userCentres);
                      });
                });
            } else {
                // Non-Super-Admin can only see assigned memberships with appointments at their centres
                $query->whereNotNull('memberships.patient_id')
                      ->whereExists(function ($subQuery) use ($userCentres) {
                          $subQuery->select(DB::raw(1))
                              ->from('appointments')
                              ->whereColumn('appointments.patient_id', 'memberships.patient_id')
                              ->whereIn('appointments.location_id', $userCentres);
                      });
            }
        } elseif (!$isSuperAdmin) {
            // Non-Super-Admin without centre restrictions still can't see unassigned memberships
            $query->whereNotNull('memberships.patient_id');
        }

        return $query->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->orderby($orderBy, $order)
            ->get();
    }
    public static function activeRecord($id, $status)
    {

        $membership = Membership::find($id);
        $checkMembershipType = MembershipType::whereId($membership->membership_type_id)->first();

        if (!$membership) {
            return false;
        }
        if ($checkMembershipType->active == 0) {
            return false;
        }
        $record = $membership->update(['active' => $status]);


        return $record;
    }
    public static function InactiveRecord($id)
    {
        $membership = Membership::find($id);
        if (!$membership) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        $record = $membership->update(['active' => 0]);

        return $record;
    }
    public function downloadStudentMembershipPatients()
    {
        return Excel::download(
            new StudentMembershipPatientsExport, 
            'student_membership_patients_' . date('Y-m-d') . '.xlsx'
        );
    }
}
