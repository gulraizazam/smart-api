<?php

namespace App\Http\Controllers\Admin;

use PHPUnit\Exception;
use App\Helpers\Filters;
use App\Models\Discounts;
use App\Models\Locations;
use App\Helpers\NodesTree;
use Illuminate\Http\Request;
use App\HelperModule\ApiHelper;
use App\Models\GetDiscountService;
use App\Models\BaseDiscountService;
use App\Http\Controllers\Controller;
use App\Models\DiscountHasLocations;
use App\Models\Services;
use App\Models\MembershipType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Helpers\Widgets\ServiceWidget;
use Illuminate\Support\Facades\Config;
use App\Helpers\Widgets\LocationsWidget;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class DiscountsController extends Controller
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
     * Display a listing of the discount in datatable.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Gate::allows('discounts_manage')) {
            return abort(401);
        }

        return view('admin.discounts.index');
    }

    /**
     * Show the form for creating a new Discount.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {

        if (!Gate::allows('discounts_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $roles = Role::pluck('name', 'id')->toArray();
            $locations = LocationsWidget::generateDropDownArray(Auth::User()->account_id);
            $customerTypes = MembershipType::parentsOnly()->where('active', 1)->pluck('name', 'id')->toArray();
            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'discount_types' => config('constants.discount_types'),
                'discount_groups' => config('constants.discount_groups'),
                'amount_types' => config('constants.amount_types'),
                'roles'=>$roles,
                'locations'=>$locations,
                'customer_types'=>$customerTypes
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Store a newly created discount in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {

        if (!Gate::allows('discounts_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            if ($request->type == "Configurable") {
                $validator = $this->verifyConfigurableFields($request);
            } else {
                $validator = $this->verifyFields($request);
            }

            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
            }
            $data = $request->all();
            $data['account_id'] = Auth::User()->account_id;
            if ($request->slug == 'custom' || $request->slug == 'default') {
                $data['pre_days'] = 0;
                $data['post_days'] = 0;
            }

            if ($request->active == null) {
                $data['active'] = '0';
            }

            if ($request->start <= $request->end) {
                if ($request->type == "Configurable") {
                    if (Discounts::createConfigurableDiscount($data)) {
                        return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
                    }
                }
                if (Discounts::createDiscount($data)) {

                    return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
                }

                return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Date range invalid, Kindly define again', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Validate form fields
     *
     * @return \Illuminate\Contracts\Validation\Validator $validator;
     */
    protected function verifyFields(Request $request)
    {
        return Validator::make($request->all(), [
            'name' => 'required',
            'discount_type' => 'required',
            'start' => 'required',
            'end' => 'required',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
        ]);
    }
    protected function verifyConfigurableFields(Request $request)
    {
        $rules = [];
        $sessions = $request->input('sessions');
        foreach ($sessions as $key => $value) {
            $rules["sessions.{$key}"] = 'required';
            // services_name is not required when same_service is checked for this row
            $sameService = $request->input("same_service.{$key}");
            if (!$sameService) {
                $rules["services_name.{$key}"] = 'required';
            }
            $rules["disc_type.{$key}"] = 'required';
        }

        return Validator::make($request->all(), [
            'name' => 'required',
            'type' => 'required',
            'start' => 'required',
            'end' => 'required',
            'sessions_buy' => 'required',
            'base_service' => 'required',
        ] + $rules);
    }
    /**
     * Display the discount in datatable form.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {

        try {

            $records = [];
            $records['data'] = [];

            $filename = 'discounts';
            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);

            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $Discounts = Discounts::whereIn('id', $ids);
                if ($Discounts) {
                    $Discounts->delete();
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            $where = $this->applyFilters($filters, $apply_filter, $filename);

            $total_query = Discounts::select('id')->where('discount_type',"!=",'voucher');
            if (count($where)) {
                if (\Illuminate\Support\Facades\Gate::allows('view_inactive_discounts')) {
                    $total_query->where($where);
                } else {
                    $total_query->where($where)->where('active', 1);
                }
            }
            $iTotalRecords = $total_query->count();

            [$orderBy, $order] = getSortBy($request);

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $query = Discounts::select('*')->where('discount_type',"!=",'voucher');
            if ($request->get('startdate') && $request->get('startdate') != '') {
                $query->whereDate('start', '>=', $request->get('startdate'));
            }
            if ($request->get('enddate') && $request->get('enddate') != '') {
                $query->whereDate('end', '<=', $request->get('enddate'));
            }

            if (count($where)) {
                if (\Illuminate\Support\Facades\Gate::allows('view_inactive_discounts')) {
                    $query->where($where);
                } else {
                    $query->where($where)->where('active', 1);
                }
            }

            $Discounts = $query->limit($iDisplayLength)->offset($iDisplayStart)->orderby('created_at', 'desc')->get();

            $records = $this->getFiltersData($records, $filename);

            if ($Discounts) {

                $records['data'] = $Discounts;

                $records['meta'] = [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => $iDisplayLength,
                    'total' => $iTotalRecords,
                    'sort' => $order,
                ];
            }

            $records['permissions'] = [
                'edit' => Gate::allows('discounts_edit'),
                'delete' => Gate::allows('discounts_destroy'),
                'active' => Gate::allows('discounts_active'),
                'inactive' => Gate::allows('discounts_inactive'),
                'create' => Gate::allows('discounts_create'),
                'allocate' => Gate::allows('discounts_allocate'),
            ];

            return ApiHelper::apiDataTable($records);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    private function applyFilters($filters, $apply_filter, $filename = 'discounts')
    {

        $where = [];

        if (Auth::user()->account_id && Auth::user()->account_id != '') {
            $where[] = [
                'account_id',
                '=',
                Auth::user()->account_id,
            ];
            Filters::put(Auth::User()->id, $filename, 'account_id', Auth::user()->account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'account_id')) {
                    $where[] = [
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'account_id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'name')) {
            $where[] = [
                'name',
                'like',
                '%' . $filters['name'] . '%',
            ];
            Filters::put(Auth::User()->id, $filename, 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'name');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'name')) {
                    $where[] = [
                        'name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, $filename, 'name') . '%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'type')) {
            $where[] = [
                'type',
                'like',
                '%' . $filters['type'] . '%',
            ];
            Filters::put(Auth::User()->id, $filename, 'type', $filters['type']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'type');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'type')) {
                    $where[] = [
                        'type',
                        'like',
                        '%' . Filters::get(Auth::User()->id, $filename, 'type') . '%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'amount')) {
            $where[] = [
                'amount',
                'like',
                '%' . $filters['amount'] . '%',
            ];
            Filters::put(Auth::User()->id, $filename, 'amount', $filters['amount']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'amount');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'amount')) {
                    $where[] = [
                        'amount',
                        'like',
                        '%' . Filters::get(Auth::User()->id, $filename, 'amount') . '%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'discount_type')) {
            $where[] = [
                'discount_type',
                '=',
                $filters['discount_type'],
            ];
            Filters::put(Auth::User()->id, $filename, 'discount_type', $filters['discount_type']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'discount_type');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'discount_type')) {
                    $where[] = [
                        'discount_type',
                        '=',
                        Filters::get(Auth::user()->id, $filename, 'discount_type'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'created_from')) {
            $where[] = [
                'created_at',
                '>=',
                $filters['created_from'] . ' 00:00:00',
            ];
            Filters::put(Auth::User()->id, $filename, 'created_from', $filters['created_from'] . ' 00:00:00');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_from');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_from')) {
                    $where[] = [
                        'created_at',
                        '>=',
                        Filters::get(Auth::User()->id, $filename, 'created_from'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'created_to')) {
            $where[] = [
                'created_at',
                '<=',
                $filters['created_to'] . ' 23:59:59',
            ];
            Filters::put(Auth::User()->id, $filename, 'created_to', $filters['created_to'] . ' 23:59:59');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_to');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_to')) {
                    $where[] = [
                        'created_at',
                        '<=',
                        Filters::get(Auth::User()->id, $filename, 'created_to'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'startdate')) {
            $where[] = [
                'start',
                '>=',
                $filters['startdate'],
            ];
            Filters::put(Auth::user()->id, $filename, 'startdate', $filters['startdate']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'startdate');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'startdate')) {
                    $where[] = [
                        'start',
                        '>=',
                        Filters::get(Auth::user()->id, $filename, 'startdate'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'enddate')) {
            $where[] = [
                'end',
                '<=',
                $filters['enddate'],
            ];
            Filters::put(Auth::user()->id, $filename, 'enddate', $filters['enddate']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'enddate');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'enddate')) {
                    $where[] = [
                        'end',
                        '<=',
                        Filters::get(Auth::user()->id, $filename, 'enddate'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'status')) {
            $where[] = [
                'active',
                '=',
                $filters['status'],
            ];
            Filters::put(Auth::user()->id, $filename, 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'status');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'status') == 0 || Filters::get(Auth::user()->id, $filename, 'status') == 1) {
                    if (Filters::get(Auth::user()->id, $filename, 'status') != null) {
                        $where[] = [
                            'active',
                            '=',
                            Filters::get(Auth::user()->id, $filename, 'status'),
                        ];
                    }
                }
            }
        }

        return $where;
    }

    private function getFiltersData($records, $filename)
    {

        $locations = Locations::getlocation();

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $records['active_filters'] = Filters::all(Auth::User()->id, $filename);

        $records['filter_values'] = [
            'services' => $Services,
            'locations' => $locations,
            'status' => config('constants.status'),
        ];

        return $records;
    }

    /**
     * Show the form for editing the specified discount information.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        if (!Gate::allows('discounts_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            $discount = Discounts::getData($id);

            if ($discount == null) {

                return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
            } else {

                $discountServices = explode(',', $discount->service_id);

                if (!$discountServices) {

                    $discountServices = [];
                }
                /* Create Nodes with Parents */
                $Services = ServiceWidget::generateServiceArrayDiscount($id, Auth::User()->account_id);

                $locations = Locations::getActiveSorted();

                if ($discount) {
                    $Discount = $discount->toArray();

                    if ($Discount['start']) {
                        $Discount['start'] = $discount->dateFormat($Discount['start'], 'Y-m-d');
                    }
                    if ($Discount['end']) {
                        $Discount['end'] = $discount->dateFormat($Discount['end'], 'Y-m-d');
                    }
                }
                $base_discount_services = BaseDiscountService::where(['discount_id' => $id])->get();
                $get_discount_services = GetDiscountService::where(['discount_id' => $id])->get();
                $roles = Role::pluck('name', 'id')->toArray();
                $customerTypes = MembershipType::parentsOnly()->where('active', 1)->pluck('name', 'id')->toArray();

                // 🔹 Get selected role ids for this discount
                $selected_role_ids = $discount->roles()->pluck('roles.id')->toArray();
                return ApiHelper::apiResponse($this->success, 'Record found', true, [
                    'discount' => $Discount ?? $discount,
                    'locations' => $locations,
                    'services' => $Services,
                    'base_discount_services' => $base_discount_services,
                    'get_discount_services' => $get_discount_services,
                    'roles' => $roles,
                    'selected_roles' => $selected_role_ids,
                    'customer_types' => $customerTypes,
                ]);
            }
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update the specified discount in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {

        if (!Gate::allows('discounts_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }

        $data = $request->all();

        if ($request->slug == 'custom' || $request->slug == 'default') {
            $data['pre_days'] = 0;
            $data['post_days'] = 0;
        }

        if ($request->active == null) {
            $data['active'] = '0';
        }

        if ($request->start <= $request->end) {
            if ($request->type == "Configurable") {
                if (Discounts::updateConfigurableDiscount($data, $id)) {
                    return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
                }
            }
            if (Discounts::updateDiscount($data, $id)) {

                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        }

        return ApiHelper::apiResponse($this->success, 'Date range invalid, Kindly define again', false);
    }

    /**
     * Active discount record from storage or database.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        if (!Gate::allows('discounts_active')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            if ($request->status == 1) {
                $response = Discounts::activeRecord($request->id);
            } else {
                $response = Discounts::inactiveRecord($request->id);
            }

            if ($response) {
                return ApiHelper::apiResponse($this->success, 'Status has been changed successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        if (!Gate::allows('discounts_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            $record = Discounts::deleteRecord($id);
            if ($record) {
                return ApiHelper::apiResponse($this->success, $record);
            } else {
                return ApiHelper::apiResponse($this->success, $record);
            }
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Display lcoation to add service for doctor.
     *
     * @param  int  $id
     */
    public function displayDlocation($id)
    {
        if (!Gate::allows('discounts_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            $discount = Discounts::find($id);

            $location = LocationsWidget::generateDropDownArray(Auth::User()->account_id);

            $discount_has_location = DiscountHasLocations::with(['service', 'location.city'])->where('discount_id', '=', $discount->id)->get();

            $responseData = [
                'discount' => $discount,
                'location' => $location,
                'discount_has_location' => $discount_has_location,
            ];

            // For configurable discounts, include the defined services
            // Count records per service since each session is stored as a separate record
            if ($discount->type === 'Configurable') {
                $baseServices = BaseDiscountService::join('services', 'services.id', 'base_discount_services.service_id')
                    ->where('discount_id', $id)
                    ->select('services.id', 'services.name', 'base_discount_services.is_category', \DB::raw('COUNT(*) as sessions'))
                    ->groupBy('services.id', 'services.name', 'base_discount_services.is_category')
                    ->get();
                
                $getServices = GetDiscountService::join('services', 'services.id', 'get_discount_services.service_id')
                    ->where('discount_id', $id)
                    ->select('services.id', 'services.name', 'get_discount_services.discount_type', 'get_discount_services.discount_amount', 'get_discount_services.same_service', \DB::raw('COUNT(*) as sessions'))
                    ->groupBy('services.id', 'services.name', 'get_discount_services.discount_type', 'get_discount_services.discount_amount', 'get_discount_services.same_service')
                    ->get();

                $responseData['configurable_services'] = [
                    'base_services' => $baseServices,
                    'get_services' => $getServices,
                ];
            }

            return ApiHelper::apiResponse($this->success, 'Service Allocated', true, $responseData);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * display services against location id.
     *
     * @param  request
     */
    public function getDservices(Request $request)
    {
        if (!Gate::allows('discounts_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $discount_info = Discounts::find($request->discount_id);
        if ($discount_info->discount_type == Config::get('constants.Service')) {
            $serive = ServiceWidget::generateServiceArrayArray($request, Auth::User()->account_id);
        } else {
            $serive = ServiceWidget::generateServiceArrayConsultancy($request, Auth::User()->account_id);
        }
        if ($discount_info->type == "Configurable") {
            $serive = BaseDiscountService::join('services', 'services.id', 'base_discount_services.service_id')
                ->select('services.name', 'services.id')->where('discount_id', $request->discount_id)->take(1)->get()->toArray();
        }

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'services' => $serive,
            'locaiton_id_1' => $request->id,
        ]);
    }
    public function getDiscountServices(Request $request)
    {
        if (!Gate::allows('discounts_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $serive = ServiceWidget::generateServiceArrayDiscount($request, Auth::User()->account_id);
        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'services' => $serive,
            'locaiton_id_1' => $request->id,
        ]);
    }

    /**
     * Get services for configurable discount creation/editing
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServicesForConfigurable()
    {
        if (!Gate::allows('discounts_create') && !Gate::allows('discounts_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $services = ServiceWidget::generateServiceArrayDiscount(null, Auth::User()->account_id);
            return ApiHelper::apiResponse($this->success, 'Services loaded', true, [
                'services' => $services,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Allocate configurable discount to a centre
     * For configurable discounts, we only need to specify the centre - services are already defined in the discount
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function allocateConfigurable(Request $request)
    {
        if (!Gate::allows('discounts_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $validator = Validator::make($request->all(), [
                'discount_id' => 'required|exists:discounts,id',
                'location_id' => 'required|exists:locations,id',
            ]);

            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
            }

            $discount = Discounts::find($request->discount_id);
            
            // Verify this is a configurable discount
            if ($discount->type !== 'Configurable') {
                return ApiHelper::apiResponse($this->success, 'This method is only for configurable discounts.', false);
            }

            $location = Locations::find($request->location_id);

            // Check if already allocated to this centre
            $existingAllocation = DiscountHasLocations::where([
                'discount_id' => $request->discount_id,
                'location_id' => $request->location_id,
            ])->first();

            if ($existingAllocation) {
                return ApiHelper::apiResponse($this->success, 'This discount is already allocated to this centre.', false);
            }

            // Get the base service from the configurable discount
            $baseService = BaseDiscountService::where('discount_id', $request->discount_id)->first();
            $serviceId = $baseService ? $baseService->service_id : null;

            // If no base service found, use "All Services"
            if (!$serviceId) {
                $allServices = Services::where('slug', 'all')->first();
                $serviceId = $allServices ? $allServices->id : null;
            }

            // Create allocation record
            $record = DiscountHasLocations::create([
                'discount_id' => $request->discount_id,
                'location_id' => $request->location_id,
                'service_id' => $serviceId,
                'type' => null, // Not applicable for configurable
                'amount' => null, // Not applicable for configurable
                'slug' => 'configurable',
            ]);

            // Build services display string for configurable discount
            // Count records per service since each session is stored as a separate record
            $baseServices = BaseDiscountService::join('services', 'services.id', 'base_discount_services.service_id')
                ->where('discount_id', $request->discount_id)
                ->select('services.name', 'base_discount_services.service_id', \DB::raw('COUNT(*) as session_count'))
                ->groupBy('services.name', 'base_discount_services.service_id')
                ->get();
            
            $getServices = GetDiscountService::join('services', 'services.id', 'get_discount_services.service_id')
                ->where('discount_id', $request->discount_id)
                ->select('services.name', 'get_discount_services.service_id', 'get_discount_services.discount_type', 'get_discount_services.discount_amount', \DB::raw('COUNT(*) as session_count'))
                ->groupBy('services.name', 'get_discount_services.service_id', 'get_discount_services.discount_type', 'get_discount_services.discount_amount')
                ->get();

            $buyParts = [];
            foreach ($baseServices as $svc) {
                $buyParts[] = 'Buy ' . $svc->session_count . ' ' . $svc->name;
            }
            
            $getParts = [];
            foreach ($getServices as $svc) {
                $discountText = $svc->discount_type === 'complimentory' ? 'Free' : $svc->discount_amount . '% Off';
                $getParts[] = 'Get ' . $svc->session_count . ' ' . $svc->name . ' (' . $discountText . ')';
            }
            
            $servicesDisplay = implode(', ', $buyParts) . ' → ' . implode(', ', $getParts);

            return ApiHelper::apiResponse($this->success, 'Discount allocated to centre successfully.', true, [
                'record' => [
                    'id' => $record->id,
                    'location_name' => $location->city->name . ' - ' . $location->name,
                    'service_name' => $servicesDisplay,
                ]
            ]);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * save services against location id.
     */
    public function saveDservices(Request $request)
    {
        if (!Gate::allows('discounts_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        // Validate required fields for allocation
        $validator = Validator::make($request->all(), [
            'allocation_type' => 'required|in:Fixed,Percentage',
            'allocation_amount' => 'required|numeric|min:0',
            'location_id' => 'required',
            'service_ids' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }

        $location_id = $request->location_id;
        $service_ids = $request->service_ids;
        $discount_id = $request->discount_id;

        // Check if "All Centres" + "All Services" is already allocated for this discount
        $allCentresId = Locations::where('slug', 'all')->value('id');
        $allServicesIdCheck = Services::where('slug', 'all')->value('id');
        if ($allCentresId && $allServicesIdCheck) {
            $allCentresAllServicesExists = DiscountHasLocations::where([
                ['location_id', '=', $allCentresId],
                ['service_id', '=', $allServicesIdCheck],
                ['discount_id', '=', $discount_id],
            ])->exists();

            if ($allCentresAllServicesExists) {
                return ApiHelper::apiResponse($this->success, 'Cannot add more allocations. "All Centres" with "All Services" is already allocated for this discount.', false);
            }
        }

        // Filter out children if their parent is also selected
        // Get parent_ids of all selected services
        $selectedParentIds = Services::whereIn('id', $service_ids)->whereNotNull('parent_id')->pluck('parent_id')->toArray();
        
        // Find which selected services are parents of other selected services
        $parentsInSelection = array_intersect($service_ids, $selectedParentIds);
        
        // If a parent is selected, remove its children from the list
        if (!empty($parentsInSelection)) {
            $childrenToRemove = Services::whereIn('parent_id', $parentsInSelection)
                ->whereIn('id', $service_ids)
                ->pluck('id')
                ->toArray();
            $service_ids = array_diff($service_ids, $childrenToRemove);
        }

        // Get "All Services" service ID (slug = 'all')
        $allServicesId = Services::where('slug', 'all')->value('id');
        
        // Check if "All Services" is in the selected services
        $isAllServices = in_array($allServicesId, $service_ids);
        
        // Check if "All Services" already exists for this location and discount
        $allServicesExists = DiscountHasLocations::where([
            ['location_id', '=', $location_id],
            ['service_id', '=', $allServicesId],
            ['discount_id', '=', $discount_id],
        ])->exists();
        
        // If "All Services" exists and trying to add individual service, block it
        if ($allServicesExists && !$isAllServices) {
            return ApiHelper::apiResponse($this->success, 'Cannot add individual service. "All Services" is already allocated for this location.', false);
        }
        
        // Check if any selected child service has its parent already allocated for this location
        $childServices = Services::whereIn('id', $service_ids)->whereNotNull('parent_id')->get();
        if ($childServices->isNotEmpty()) {
            $parentIds = $childServices->pluck('parent_id')->unique()->toArray();
            
            // Check if any of these parents are already allocated
            $existingParentAllocations = DiscountHasLocations::where('location_id', $location_id)
                ->where('discount_id', $discount_id)
                ->whereIn('service_id', $parentIds)
                ->with('service')
                ->get();
            
            if ($existingParentAllocations->isNotEmpty()) {
                $parentNames = $existingParentAllocations->map(function($alloc) {
                    return $alloc->service->name;
                })->implode(', ');
                return ApiHelper::apiResponse($this->success, 'Cannot add child service. Parent category "' . $parentNames . '" is already allocated for this location.', false);
            }
        }
        
        // If adding "All Services", remove existing individual services for this location
        $removedIds = [];
        if ($isAllServices) {
            $existingAllocations = DiscountHasLocations::where([
                ['location_id', '=', $location_id],
                ['discount_id', '=', $discount_id],
                ['service_id', '!=', $allServicesId],
            ])->get();
            
            foreach ($existingAllocations as $allocation) {
                $removedIds[] = $allocation->id;
            }
            
            // Delete existing individual services
            DiscountHasLocations::where([
                ['location_id', '=', $location_id],
                ['discount_id', '=', $discount_id],
                ['service_id', '!=', $allServicesId],
            ])->delete();
        }

        $createdRecords = [];
        $duplicateCount = 0;

        foreach ($service_ids as $service_id) {
            // Check if record already exists
            $exists = DiscountHasLocations::where([
                ['location_id', '=', $location_id],
                ['service_id', '=', $service_id],
                ['discount_id', '=', $discount_id],
            ])->exists();

            if (!$exists) {
                $data = [
                    'discount_id' => $discount_id,
                    'location_id' => $location_id,
                    'service_id' => $service_id,
                    'type' => $request->allocation_type,
                    'amount' => $request->allocation_amount,
                    'slug' => $request->allocation_slug ?? 'default',
                ];

                $record = DiscountHasLocations::create($data);

                $createdRecords[] = [
                    'id' => $record->id,
                    'location_name' => $record->location->city->name . '-' . $record->location->name,
                    'service_name' => $record->service->name,
                    'type' => $record->type,
                    'amount' => $record->amount,
                    'slug' => $record->slug,
                ];
            } else {
                $duplicateCount++;
            }
        }

        if (count($createdRecords) > 0) {
            $message = count($createdRecords) . ' record(s) saved successfully.';
            if ($duplicateCount > 0) {
                $message .= ' ' . $duplicateCount . ' duplicate(s) skipped.';
            }
            return ApiHelper::apiResponse($this->success, $message, true, [
                'records' => $createdRecords,
                'removed_ids' => $removedIds,
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'All selected services already exist for this location.', false);
    }

    /**
     * delete serive
     *
     * @param  request
     */
    public function deleteDservice(Request $request)
    {

        if (!Gate::allows('discounts_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        DiscountHasLocations::find($request->id)->delete();

        return ApiHelper::apiResponse($this->success, 'Row deleted', true, [
            'id' => $request->id,
        ]);
    }

    /**
     * Delete multiple service allocations (group delete)
     *
     * @param  request
     */
    public function deleteDserviceGroup(Request $request)
    {
        if (!Gate::allows('discounts_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $ids = explode(',', $request->ids);
        
        if (empty($ids)) {
            return ApiHelper::apiResponse($this->error, 'No IDs provided', false);
        }

        $deletedCount = DiscountHasLocations::whereIn('id', $ids)->delete();

        return ApiHelper::apiResponse($this->success, $deletedCount . ' allocation(s) deleted successfully.', true, [
            'deleted_ids' => $ids,
        ]);
    }
}
