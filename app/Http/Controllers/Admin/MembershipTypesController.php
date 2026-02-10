<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\Discounts;
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
                // Get children (renewals) for this membership type
                $children = [];
                if ($membershipType->children && $membershipType->children->count()) {
                    foreach ($membershipType->children as $child) {
                        $children[] = [
                            'id' => $child->id,
                            'name' => $child->name,
                            'period' => $child->period,
                            'amount' => $child->amount,
                            'active' => $child->active,
                            'created_at' => Carbon::parse($child->created_at)->format('F j,Y h:i A'),
                        ];
                    }
                }
                
                $records['data'][] = [
                    'id' => $membershipType->id,
                    'name' => $membershipType->name,
                    'period' => $membershipType->period,
                    'amount' => $membershipType->amount,
                    'active' => $membershipType->active,
                    'created_at' => Carbon::parse($membershipType->created_at)->format('F j,Y h:i A'),
                    'parent_id' => $membershipType->parent_id,
                    'parent_name' => $membershipType->parent ? $membershipType->parent->name : '-',
                    'children' => $children,
                    'has_children' => count($children) > 0,
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
        $data['parent_id'] = !empty($data['parent_id']) ? $data['parent_id'] : null;
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
        $membershipType = MembershipType::with('discounts')->find($id);

        // Get parent membership options (exclude current record to prevent self-reference)
        $parentMemberships = MembershipType::whereNull('parent_id')
            ->where('active', 1)
            ->where('id', '!=', $id)
            ->pluck('name', 'id');

        // Get all active discounts for selection
        $today = Carbon::now()->toDateString();
        $activeDiscounts = Discounts::where('active', 1)
            ->where('discount_type', '!=', 'voucher')
            ->whereDate('start', '<=', $today)
            ->whereDate('end', '>=', $today)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Get currently assigned discount IDs
        $assignedDiscountIds = $membershipType->discounts->pluck('id')->toArray();

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'membershipType' => $membershipType,
            'parentMemberships' => $parentMemberships,
            'activeDiscounts' => $activeDiscounts,
            'assignedDiscountIds' => $assignedDiscountIds,
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
            'amount' => $data['amount'],
            'parent_id' => !empty($data['parent_id']) ? $data['parent_id'] : null,
        ]);

        // Sync discounts
        $discountIds = $request->input('discount_ids', []);
        $record->discounts()->sync($discountIds);

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

        // Count all membership types
        $query = DB::table('membership_types');
        
        if (count($where)) {
            $query->where($where);
        }
        
        if (!\Illuminate\Support\Facades\Gate::allows('view_inactive_centres')) {
            $query->where('membership_types.active', 1);
        }
        
        return $query->count();
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
        
        // Fetch all membership types and eager load parent and children relationships
        $query = MembershipType::with(['parent', 'children']);
        
        if (count($where)) {
            $query->where($where);
        }
        
        if (!\Illuminate\Support\Facades\Gate::allows('view_inactive_machine_types')) {
            $query->where('membership_types.active', 1);
        }
        
        return $query->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->orderby($orderBy, $order)
            ->get();
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

    /**
     * Get active membership types for dropdown
     * If patient_id is provided, check if patient has expired membership to show specific renewal
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActiveTypes(Request $request)
    {
        $patientId = $request->get('patient_id');
        $expiredMembershipTypeId = null;
        
        // Check if patient has an expired membership and get its type
        if ($patientId) {
            $latestMembership = Membership::where('patient_id', $patientId)
                ->orderBy('created_at', 'desc')
                ->first();
            
            // Get the expired membership type ID (only if expired)
            if ($latestMembership && $latestMembership->end_date < now()->format('Y-m-d')) {
                // Get the parent membership type ID (in case the expired one was already a renewal)
                $expiredType = MembershipType::find($latestMembership->membership_type_id);
                if ($expiredType) {
                    // If it's a renewal, get the parent ID; otherwise use its own ID
                    $expiredMembershipTypeId = $expiredType->parent_id ?? $expiredType->id;
                }
            }
        }
        
        // Get all parent membership types (always show these)
        $parentMembershipTypes = MembershipType::where('active', 1)
            ->whereNull('parent_id')
            ->select('id', 'name', 'amount', 'period', 'parent_id')
            ->orderBy('name')
            ->get();

        $membershipTypes = $parentMembershipTypes;

        // If patient has an expired membership, add ONLY the renewal for that specific type
        if ($expiredMembershipTypeId) {
            $renewalMembershipType = MembershipType::where('active', 1)
                ->where('parent_id', $expiredMembershipTypeId)
                ->select('id', 'name', 'amount', 'period', 'parent_id')
                ->first();

            if ($renewalMembershipType) {
                // Merge parent membership types with the specific renewal
                $membershipTypes = $parentMembershipTypes->push($renewalMembershipType)->sortBy('name')->values();
            }
        }

        return ApiHelper::apiResponse($this->success, 'Membership types retrieved successfully.', true, [
            'membership_types' => $membershipTypes,
            'expired_membership_type_id' => $expiredMembershipTypeId
        ]);
    }
}
