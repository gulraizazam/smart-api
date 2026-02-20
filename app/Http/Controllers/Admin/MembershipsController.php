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
use App\Models\Locations;
use App\Helpers\ACL;
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
            $locations = Locations::getActiveSorted(ACL::getUserCentres());
            // Get active doctors and FDMs for Sold By filter
            $soldByUsers = User::where('account_id', Auth::User()->account_id)
                ->where('active', 1)
                ->whereIn('user_type_id', [config('constants.doctor_user_id'), config('constants.fdm_user_id')])
                ->orderBy('name')
                ->pluck('name', 'id');
            // Get active filter values from Filters helper
            $activeFilters = [
                'patient_id' => Filters::get(Auth::user()->id, 'memberships', 'patient_id'),
                'code' => Filters::get(Auth::user()->id, 'memberships', 'code'),
                'membership_type_id' => Filters::get(Auth::user()->id, 'memberships', 'membership_type_id'),
                'status' => Filters::get(Auth::user()->id, 'memberships', 'status'),
                'location_id' => Filters::get(Auth::user()->id, 'memberships', 'location_id'),
                'sold_by' => Filters::get(Auth::user()->id, 'memberships', 'sold_by'),
                'assigned_at' => Filters::get(Auth::user()->id, 'memberships', 'assigned_at'),
                'created_by' => Filters::get(Auth::user()->id, 'memberships', 'created_by'),
            ];
            $records['active_filters'] = $activeFilters;
            $records['filter_values'] = [
                'status' => config('constants.status'),
                'users' => $Users,
                'membershipType' => $membershipType,
                'locations' => $locations,
                'soldByUsers' => $soldByUsers
            ];
            if ($memberships->count()) {
                foreach ($memberships as $membership) {
                    $patient = User::whereId($membership->patient_id)->first();
                    $membershipTypeName = $membership->membershipType->name ?? 'N/A';
                    $isStudentMembership = stripos($membershipTypeName, 'student') !== false;
                    
                    $records['data'][] = [
                        'id' => $membership->id,
                        'code' => $membership->code,
                        'active' => $membership->active,
                        'start_date' => $membership->start_date,
                        'end_date' => $membership->end_date,
                        'membership_type_id' => $membershipTypeName,
                        'membership_type_id_raw' => $membership->membership_type_id,
                        'is_student_membership' => $isStudentMembership,
                        'patient' => $patient ? $patient->name : 'N/A',
                        'patient_id' => $patient ? $patient->id : 'N/A',
                        'patient_unique_id' => $patient ? $patient->unique_id : null,
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
                    'view_details' => Gate::allows('memberships_manage'),
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
        // Only fetch parent membership types (not renewals)
        $membershipType = MembershipType::parentsOnly()
            ->where('active', 1)
            ->pluck('name', 'id');
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
        // Only fetch parent membership types (not renewals)
        $membershipType = MembershipType::parentsOnly()->where('active', 1)->pluck('name', 'id');
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

        // Apply patient_id filter (search by unique_id)
        $patientIdFilter = self::getPatientIdFilter($request, $apply_filter);
        if ($patientIdFilter !== null) {
            if (empty($patientIdFilter)) {
                $query->where('memberships.patient_id', '=', -1); // No matching patients
            } else {
                $query->whereIn('memberships.patient_id', $patientIdFilter);
            }
        }

        // Apply location filter - filter by patient's appointment location
        $locationFilter = self::getLocationFilter($request, $apply_filter);
        if ($locationFilter !== null) {
            if (empty($locationFilter)) {
                // No patients at this location, return no results
                $query->where('memberships.patient_id', '=', -1);
            } else {
                // Only show assigned memberships where patient has appointments at this location
                $query->whereIn('memberships.patient_id', $locationFilter);
            }
        }

        // Apply sold_by filter - filter by package_services.sold_by
        $soldByFilter = self::getSoldByFilter($request, $apply_filter);
        if ($soldByFilter !== null) {
            if (empty($soldByFilter)) {
                // No memberships with this sold_by, return no results
                $query->where('memberships.id', '=', -1);
            } else {
                $query->whereIn('memberships.id', $soldByFilter);
            }
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
            $statusFilter = $filters['status'];
            if ($statusFilter == 'active') {
                // Active = assigned (patient_id not null) AND not expired (end_date >= today)
                $where[] = ['memberships.patient_id', '<>', null];
                $where[] = ['memberships.end_date', '>=', now()->format('Y-m-d')];
            } elseif ($statusFilter == 'inactive') {
                // Inactive = not assigned (patient_id is null)
                $where[] = ['memberships.patient_id', '=', null];
            } elseif ($statusFilter == 'expired') {
                // Expired = end_date < today
                $where[] = ['memberships.end_date', '<', now()->format('Y-m-d')];
            }
            Filters::put(Auth::user()->id, 'memberships', 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'memberships', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'memberships', 'status') !== null) {
                    $statusFilter = Filters::get(Auth::user()->id, 'memberships', 'status');
                    if ($statusFilter == 'active') {
                        $where[] = ['memberships.patient_id', '<>', null];
                        $where[] = ['memberships.end_date', '>=', now()->format('Y-m-d')];
                    } elseif ($statusFilter == 'inactive') {
                        $where[] = ['memberships.patient_id', '=', null];
                    } elseif ($statusFilter == 'expired') {
                        $where[] = ['memberships.end_date', '<', now()->format('Y-m-d')];
                    }
                }
            }
        }
        // Location filter is handled separately in getRecords/getTotalRecords
        if (hasFilter($filters, 'location_id')) {
            Filters::put(Auth::user()->id, 'memberships', 'location_id', $filters['location_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'memberships', 'location_id');
            }
        }
        
        // Sold By filter is handled separately in getRecords/getTotalRecords (filters by package_services.sold_by)
        if (hasFilter($filters, 'sold_by')) {
            Filters::put(Auth::user()->id, 'memberships', 'sold_by', $filters['sold_by']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'memberships', 'sold_by');
            }
        }
        
        // Assigned At date range filter
        if (hasFilter($filters, 'assigned_at')) {
            $date_range = explode(' - ', $filters['assigned_at']);
            $start_date_time = date('Y-m-d 00:00:00', strtotime($date_range[0]));
            $end_date_string = new DateTime($date_range[1]);
            $end_date_string->setTime(23, 59, 59);
            $end_date_time = $end_date_string->format('Y-m-d H:i:s');
            $where[] = ['memberships.assigned_at', '>=', $start_date_time];
            $where[] = ['memberships.assigned_at', '<=', $end_date_time];
            Filters::put(Auth::User()->id, 'memberships', 'assigned_at', $filters['assigned_at']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'memberships', 'assigned_at');
            } else {
                $savedAssignedAt = Filters::get(Auth::User()->id, 'memberships', 'assigned_at');
                if ($savedAssignedAt) {
                    $date_range = explode(' - ', $savedAssignedAt);
                    $start_date_time = date('Y-m-d 00:00:00', strtotime($date_range[0]));
                    $end_date_string = new DateTime($date_range[1]);
                    $end_date_string->setTime(23, 59, 59);
                    $end_date_time = $end_date_string->format('Y-m-d H:i:s');
                    $where[] = ['memberships.assigned_at', '>=', $start_date_time];
                    $where[] = ['memberships.assigned_at', '<=', $end_date_time];
                }
            }
        }

        return $where;
    }

    /**
     * Get patient ID filter
     * Returns null if no filter applied, or the patient ID to filter by
     */
    public static function getPatientIdFilter(Request $request, $apply_filter = false)
    {
        $filters = getFilters($request->all());
        $patientId = null;

        if (hasFilter($filters, 'patient_id')) {
            $patientId = $filters['patient_id'];
            Filters::put(Auth::User()->id, 'memberships', 'patient_id', $filters['patient_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'memberships', 'patient_id');
            } else {
                $patientId = Filters::get(Auth::User()->id, 'memberships', 'patient_id');
            }
        }

        if (empty($patientId)) {
            return null; // No filter applied
        }

        // Return as array for whereIn clause
        return [$patientId];
    }

    /**
     * Get location filter - returns patient IDs who have appointments at the selected location
     * Location filter only works for assigned memberships
     */
    public static function getLocationFilter(Request $request, $apply_filter = false)
    {
        $filters = getFilters($request->all());
        $locationId = null;

        if (hasFilter($filters, 'location_id')) {
            $locationId = $filters['location_id'];
        } else {
            if (!$apply_filter) {
                $locationId = Filters::get(Auth::User()->id, 'memberships', 'location_id');
            }
        }

        if (empty($locationId)) {
            return null; // No filter applied
        }

        // Get patient IDs who have appointments at this location
        $patientIds = \App\Models\Appointments::where('location_id', $locationId)
            ->whereNotNull('patient_id')
            ->distinct()
            ->pluck('patient_id')
            ->toArray();
        
        return $patientIds;
    }

    /**
     * Get sold by filter - returns membership IDs that have package_services with the specified sold_by user
     * Relationship: memberships.id -> package_bundles.membership_code_id -> package_services.sold_by
     */
    public static function getSoldByFilter(Request $request, $apply_filter = false)
    {
        $filters = getFilters($request->all());
        $soldBy = null;

        if (hasFilter($filters, 'sold_by')) {
            $soldBy = $filters['sold_by'];
        } else {
            if (!$apply_filter) {
                $soldBy = Filters::get(Auth::User()->id, 'memberships', 'sold_by');
            }
        }

        if (empty($soldBy)) {
            return null; // No filter applied
        }

        // Get membership IDs through package_bundles -> package_services relationship
        $membershipIds = \Illuminate\Support\Facades\DB::table('memberships')
            ->join('package_bundles', 'memberships.id', '=', 'package_bundles.membership_code_id')
            ->join('package_services', 'package_bundles.id', '=', 'package_services.package_bundle_id')
            ->where('package_services.sold_by', $soldBy)
            ->distinct()
            ->pluck('memberships.id')
            ->toArray();
        
        return $membershipIds;
    }

    /**
     * Get sold by users (doctors and FDMs) for a specific location
     */
    public function getSoldByUsers(Request $request)
    {
        try {
            $locationId = $request->location_id;
            
            if (empty($locationId)) {
                // Return all active doctors and FDMs if no location specified
                $users = User::where('account_id', Auth::User()->account_id)
                    ->where('active', 1)
                    ->whereIn('user_type_id', [config('constants.doctor_user_id'), config('constants.fdm_user_id')])
                    ->orderBy('name')
                    ->pluck('name', 'id');
                    
                return response()->json([
                    'success' => true,
                    'data' => ['users' => $users]
                ]);
            }
            
            // Get doctors allocated to this location
            $doctorIds = \App\Models\DoctorHasLocations::where('location_id', $locationId)
                ->where('is_allocated', 1)
                ->pluck('user_id')
                ->toArray();
            
            // Get FDM users from this location
            $locationUserIds = \App\Models\UserHasLocations::where('location_id', $locationId)
                ->pluck('user_id')
                ->toArray();
            
            // Get FDM role
            $fdmRole = \Illuminate\Support\Facades\DB::table('roles')->where('name', 'FDM')->first();
            $fdmUserIds = [];
            if ($fdmRole) {
                $roleHasUsers = \App\Models\RoleHasUsers::where('role_id', $fdmRole->id)
                    ->pluck('user_id')
                    ->toArray();
                // Get users who are both FDM and belong to this location
                $fdmUserIds = array_intersect($locationUserIds, $roleHasUsers);
            }
            
            // Merge doctor and FDM user IDs
            $allUserIds = array_unique(array_merge($doctorIds, $fdmUserIds));
            
            $users = User::whereIn('id', $allUserIds)
                ->where('active', 1)
                ->orderBy('name')
                ->pluck('name', 'id');
            
            return response()->json([
                'success' => true,
                'data' => ['users' => $users]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
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

        // Apply patient_id filter (search by unique_id)
        $patientIdFilter = self::getPatientIdFilter($request, $apply_filter);
        if ($patientIdFilter !== null) {
            if (empty($patientIdFilter)) {
                $query->where('memberships.patient_id', '=', -1); // No matching patients
            } else {
                $query->whereIn('memberships.patient_id', $patientIdFilter);
            }
        }

        // Apply location filter - filter by patient's appointment location
        $locationFilter = self::getLocationFilter($request, $apply_filter);
        if ($locationFilter !== null) {
            if (empty($locationFilter)) {
                // No patients at this location, return no results
                $query->where('memberships.patient_id', '=', -1);
            } else {
                // Only show assigned memberships where patient has appointments at this location
                $query->whereIn('memberships.patient_id', $locationFilter);
            }
        }

        // Apply sold_by filter - filter by package_services.sold_by
        $soldByFilter = self::getSoldByFilter($request, $apply_filter);
        if ($soldByFilter !== null) {
            if (empty($soldByFilter)) {
                // No memberships with this sold_by, return no results
                $query->where('memberships.id', '=', -1);
            } else {
                $query->whereIn('memberships.id', $soldByFilter);
            }
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

    /**
     * Get student verification details for a membership
     */
    public function getStudentVerificationDetails($membershipId)
    {
        try {
            $membership = Membership::with('membershipType')->find($membershipId);
            
            if (!$membership) {
                return ApiHelper::apiResponse($this->error, 'Membership not found', false);
            }

            $patient = User::find($membership->patient_id);
            
            if (!$patient) {
                return ApiHelper::apiResponse($this->error, 'Patient not found', false);
            }

            // Get student verification record
            $studentVerification = \App\Models\StudentVerification::where('membership_id', $membershipId)
                ->orWhere(function($query) use ($membership) {
                    $query->where('patient_id', $membership->patient_id)
                          ->where('membership_type_id', $membership->membership_type_id);
                })
                ->first();

            $documents = [];
            if ($studentVerification && !empty($studentVerification->document_paths)) {
                $documents = $studentVerification->document_paths;
            }

            // Get discount IDs that are linked to this membership type via customer_type_id
            $membershipTypeDiscountIds = \App\Models\Discounts::where('customer_type_id', $membership->membership_type_id)
                ->pluck('id')
                ->toArray();

            // Get services where this membership type's discount was applied to this patient's packages
            // Only show services with discounts applied, not the membership purchase itself
            $usedServices = collect();
            if (!empty($membershipTypeDiscountIds)) {
                $usedServices = \App\Models\PackageBundles::whereIn('discount_id', $membershipTypeDiscountIds)
                    ->whereHas('package', function($q) use ($patient) {
                        $q->where('patient_id', $patient->id);
                    })
                    ->with(['bundle', 'package', 'discount', 'packageservice'])
                    ->get();
            }

            $serviceUsage = [];
            $totalDiscountAmount = 0;
            foreach ($usedServices as $service) {
                // Determine service name based on type
                if ($service->bundle) {
                    $serviceName = $service->bundle->name;
                } else {
                    $serviceName = 'Unknown Service';
                }
                
                $discountSaved = $service->service_price - $service->tax_including_price;
                
                // Get consumption info from package_services
                $packageService = $service->packageservice->first();
                $isConsumed = $packageService ? (bool) $packageService->is_consumed : false;
                $consumedAt = $packageService && $packageService->consumed_at 
                    ? \Carbon\Carbon::parse($packageService->consumed_at)->format('d/m/y') 
                    : null;
                
                $serviceUsage[] = [
                    'service_name' => $serviceName,
                    'service_price' => $service->service_price,
                    'discount_amount' => $service->discount_price ?? 0,
                    'discount_type' => $service->discount_type,
                    'net_amount' => $service->tax_including_price,
                    'plan_id' => $service->package_id,
                    'plan_date' => $service->package ? $service->package->created_at->format('M d, Y') : null,
                    'is_consumed' => $isConsumed,
                    'consumed_at' => $consumedAt,
                ];
                
                if ($discountSaved > 0) {
                    $totalDiscountAmount += $discountSaved;
                }
            }

            return ApiHelper::apiResponse($this->success, 'Details found', true, [
                'membership' => [
                    'id' => $membership->id,
                    'code' => $membership->code,
                    'type' => $membership->membershipType->name ?? 'N/A',
                    'start_date' => $membership->start_date,
                    'end_date' => $membership->end_date,
                    'status' => $membership->active ? 'Active' : 'Expired',
                ],
                'patient' => [
                    'id' => $patient->id,
                    'unique_id' => 'C-' . $patient->id,
                    'name' => $patient->name,
                    'email' => $patient->email,
                    'phone' => $patient->phone,
                ],
                'verification' => $studentVerification ? [
                    'id' => $studentVerification->id,
                    'status' => $studentVerification->status,
                    'submitted_at' => $studentVerification->submitted_at ? $studentVerification->submitted_at->format('M d, Y h:i A') : null,
                    'reviewed_at' => $studentVerification->reviewed_at ? $studentVerification->reviewed_at->format('M d, Y h:i A') : null,
                ] : null,
                'documents' => $documents,
                'service_usage' => [
                    'total_services' => count($serviceUsage),
                    'total_discount_saved' => $totalDiscountAmount,
                    'services' => $serviceUsage,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching student verification details: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, 'Failed to fetch details', false);
        }
    }
}
