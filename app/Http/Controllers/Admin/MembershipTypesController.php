<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MembershipTypesController extends Controller
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
    /**
     * Display a listing of memberships types.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Gate::allows('membershiptypes_manage')) {
            return abort(401);
        }

        return view('admin.memberships_types.index');
    }
    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request)
    {

        $filename = 'membership_types';

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, $filename);

        $records = [];
        $records['data'] = [];

        [$orderBy, $order] = getSortBy($request);

        $iTotalRecords = $this->getTotalRecords($request, Auth::User()->account_id, $apply_filter);

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $membershipTypes = $this->getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);
        $records['active_filters'] = $apply_filter;
        $records['filter_values'] = [
            'status' => config('constants.status'),
        ];
        if ($membershipTypes->count()) {
            foreach ($membershipTypes as $membershipType) {
                $records['data'][] = [
                    'id' => $membershipType->id,
                    'name' => $membershipType->name,
                    'period' => $membershipType->period,
                    'amount' => $membershipType->amount,
                    'active' => $membershipType->active,
                    'created_at' => Carbon::parse($membershipType->created_at)->format('F j,Y h:i A'),
                ];
            }

            $records['permissions'] = [
                'edit' => Gate::allows('membershiptypes_edit'),
                'delete' => Gate::allows('membershiptypes_destroy'),
                'active' => Gate::allows('membershiptypes_active'),
                'inactive' => Gate::allows('membershiptypes_inactive'),
                'create' => Gate::allows('membershiptypes_create'),

            ];

            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,

            ];
        } //end

        return response()->json($records);
    }
    public function store(Request $request)
    {

        if (!Gate::allows('membershiptypes_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $validator = $this->verifyFields($request);
        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        $data = $request->all();

        $data['account_id'] = Auth::user()->account_id;
        $data['created_by'] = Auth::id();
        $record = MembershipType::create($data);
        if ($record) {
            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        } else {
            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        }
    }
    protected function verifyFields(Request $request)
    {
        return $validator = \Validator::make($request->all(), [
            'name' => [
                'required',
                Rule::unique('membership_types', 'name')->ignore($request->id)
            ],
            'period' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:1.00'],
        ]);
    }
    public function status(Request $request)
    {

        if (!Gate::allows('membershiptypes_active')) {
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

        return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
    }
    public function edit($id)
    {
        if (!Gate::allows('membershiptypes_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $membershipType = MembershipType::find($id);

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'membershipType' => $membershipType,

        ]);
    }
    public function update(Request $request, $id)
    {
        $validator = \Validator::make($request->all(), [
            'name' => [
                'required',
                Rule::unique('membership_types', 'name')->ignore($id),
            ],
            'period' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);
        if (!Gate::allows('membershiptypes_edit')) {
            return abort(401);
        }


        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first());
        }
        $data = $request->all();
        $data['account_id'] = Auth::user()->account_id;
        $data['updated_by'] = Auth::id();

        $record = MembershipType::where([
            'id' => $id,
        ])->first();

        if (!$record) {
            return null;
        }
        $record->update([
            'name' => $data['name'],
            'period' => $data['period'],
            'amount' => $data['amount']
        ]);
        if ($record) {
            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
        } else {
            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        }
    }
    public function destroy($id)
    {
        if (!Gate::allows('membershiptypes_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $membershipType = MembershipType::find($id);

        if ($membershipType) {

            $find_membership = Membership::where('membership_type_id', $id)->first();
            if ($find_membership) {
                $membershipType->update(['active' => 0]);
                Membership::where('membership_type_id', $id)->update(['active' => 0]);
                return ApiHelper::apiResponse($this->error, 'Record has been deactivated successfully');
            } else {
                $membershipType->delete();
                return ApiHelper::apiResponse($this->error, 'Record has been deleted successfully');
            }
        }
        return ApiHelper::apiResponse($this->success, 'Resource not found', false);
    }
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::membershiptype_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_centres')) {
                return count(DB::table('membership_types')
                    ->where($where)
                    ->get());
            } else {
                return count(DB::table('membership_types')

                    ->where($where)
                    ->where('membership_types.active', 1)

                    ->get());
            }
        } else {
            return MembershipType::count();
        }
    }
    public static function membershiptype_filters($request, $account_id, $apply_filter)
    {
        $filters = getFilters($request->all());
        $where = [];

        if (hasFilter($filters, 'name')) {
            $where[] = [
                'membership_types.name',
                'like',
                '%' . $filters['name'] . '%',
            ];
            Filters::put(Auth::User()->id, 'membership_types', 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'membership_types', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'membership_types', 'name')) {
                    $where[] = [
                        'membership_types.name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'membership_types', 'name') . '%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'status')) {
            $where[] = [
                'membership_types.active',
                '=',
                $filters['status'],
            ];
            Filters::put(Auth::user()->id, 'membership_types', 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'membership_types', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'membership_types', 'status')) {
                    if (Filters::get(Auth::user()->id, 'membership_types', 'status') != null) {
                        $where[] = [
                            'membership_types.active',
                            '=',
                            Filters::get(Auth::user()->id, 'membership_types', 'status'),
                        ];
                    }
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
                return MembershipType::where($where)->limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderby($orderBy, $order)
                    ->get();
            } else {
                return MembershipType::where($where)->where('membership_types.active', 1)
                    ->limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderby($orderBy, $order)
                    ->get();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_machine_types')) {
                return MembershipType::limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderby($orderBy, $order)
                    ->get();
            } else {
                return MembershipType::where('membership_types.active', 1)
                    ->limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderby($orderBy, $order)
                    ->get();
            }
        }
    }
    public static function activeRecord($id, $status)
    {

        $membershipType = MembershipType::find($id);

        if (!$membershipType) {
            return false;
        }
        $record = $membershipType->update(['active' => 1]);
        Membership::where('membership_type_id', $id)->update(['active' => 1]);
        return $record;
    }
    public static function inactiveRecord($id)
    {

        $membershipType = MembershipType::find($id);

        if (!$membershipType) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        $record = $membershipType->update(['active' => 0]);
        Membership::where('membership_type_id', $id)->update(['active' => 0]);
        return $record;
    }
}
