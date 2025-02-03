<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
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
use Rap2hpoutre\FastExcel\FastExcel;

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
        Membership::where('patient_id', $request->id)->delete();
        return ApiHelper::apiResponse($this->success, 'Membership cancelled Successfully');
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

        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_centres')) {
                return count(DB::table('memberships')
                    ->where($where)
                    ->get());
            } else {
                return count(DB::table('memberships')

                    ->where($where)
                    ->where('memberships.active', 1)

                    ->get());
            }
        } else {
            return Membership::count();
        }
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
                if (Filters::get(Auth::user()->id, 'membershipss', 'membership_type_id')) {
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
                if (Filters::get(Auth::user()->id, 'membershipss', 'created_by')) {
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
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false)
    {
        $where = self::membershiptype_filters($request, $account_id, $apply_filter);

        $orderBy = 'created_at';
        $order = 'desc';
        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_machine_types')) {
                return Membership::with('membershiptype')->where($where)->limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderby($orderBy, $order)
                    ->get();
            } else {
                return Membership::with('membershiptype')->where($where)->where('membership_types.active', 1)
                    ->limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderby($orderBy, $order)
                    ->get();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_machine_types')) {
                return Membership::with('membershiptype')->limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderby($orderBy, $order)
                    ->get();
            } else {
                return Membership::with('membershiptype')->where('memberships.active', 1)
                    ->limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderby($orderBy, $order)
                    ->get();
            }
        }
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
}
