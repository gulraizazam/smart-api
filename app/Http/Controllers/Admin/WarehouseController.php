<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\Cities;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use App\HelperModule\ApiHelper;
use App\Helpers\GeneralFunctions;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class WarehouseController extends Controller
{
    protected $error;

    protected $success;

    protected $unauthorized;

    public function __construct()
    {
        $this->error = config('constants.api_status.error');
        $this->success = config('constants.api_status.success');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Display a listing of brand.
     *
     * @return \never
     */
    public function index()
    {
        if (!Gate::allows('warehouse_manage')) {
            return abort(401);
        }

        return view('admin.warehouse.index');
    }

    /**
     * Display a listing of warehouse
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {
            $records = [];
            $records['data'] = [];
            $filename = 'warehouse';

            $filters = getFilters($request->all());

            $apply_filter = checkFilters($filters, $filename);

            if (isset($filters['delete'])) {
                $ids = explode(',', $filters['delete']);
                $warehouses = Warehouse::getBulkData($ids);

                if (!$warehouses->isEmpty()) {
                    $is_child = false;
                    foreach ($warehouses as $warehouse) {
                        if (!Warehouse::isChildExists($warehouse->id, Auth::User()->account_id)) {
                            $warehouse->delete();
                            $is_child = true;
                        }
                    }
                    if (!$is_child) {
                        $records['status'] = false;
                        $records['message'] = 'Child records exist, unable to delete resource!';
                    } else {
                        $records['status'] = true;
                        $records['message'] = 'Records has been deleted successfully!';
                    }
                }
            }

            // Get Total Records
            $iTotalRecords = Warehouse::getTotalRecords($request, Auth::User()->account_id, $apply_filter);
            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $warehouses = Warehouse::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);
            $cities = Cities::getAllRecordsDictionary(Auth::User()->account_id);

            if ($warehouses->count()) {
                foreach ($warehouses as $warehouse) {
                    /*
                     * Record Level Services process end
                     */
                    $records['data'][] = [
                        'id' => $warehouse->id,
                        'name' => $warehouse->name,
                        'manager_name' => $warehouse->manager_name ? $warehouse->manager_name : 'N/A',
                        'manager_phone' => $warehouse->manager_phone ? GeneralFunctions::prepareNumber4CallSMS($warehouse->manager_phone) : 'N/A',
                        'address' => $warehouse->address,
                        'city' => (array_key_exists($warehouse->city_id, $cities)) ? $cities[$warehouse->city_id]->name : 'N/A',
                        'status' => $warehouse->active,
                        'created_at' => Carbon::parse($warehouse->created_at)->format('F j,Y h:i A'),
                    ];
                }
            } //end
            $records['permissions'] = [
                'manage' => Gate::allows('warehouse_manage'),
                'create' => Gate::allows('warehouse_create'),
                'edit' => Gate::allows('warehouse_edit'),
                'delete' => Gate::allows('warehouse_destroy'),
                'active' => Gate::allows('warehouse_active'),
            ];
            $records['active_filters'] = $filters;
            $records['filter_values'] = [
                'cities' => collect($cities)->pluck('name', 'id'),
                'status' => config('constants.status'),
            ];
            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];
            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!Gate::allows('warehouse_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $cities = Cities::where([
            ['account_id', '=', Auth::User()->account_id],
            ['slug', '=', 'custom'],
            ['active', '=', '1'],
            ['is_featured', '=', '1'],
        ])->get()->pluck('full_name', 'id');

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'cities' => $cities,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!Gate::allows('warehouse_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $validator = $this->verifyFields($request);
        if ($validator->fails()) {

            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        $warehouse = Warehouse::createRecord($request, Auth::User()->account_id);
        if ($warehouse) {
            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        } else {
            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        }
    }

    protected function verifyFields(Request $request)
    {
        return Validator::make($request->all(), [
            'name' => 'required',
           
            'city_id' => 'required',
            /*'ntn' => ['required', 'regex:/^([0-9]|\.|\+|\*|\-|\_|\#)*$/'],
            'stn' => ['required', 'regex:/^([0-9]|\.|\+|\*|\-|\_|\#)*$/'],*/
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!Gate::allows('warehouse_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $warehouse = Warehouse::getData($id);
        if (!$warehouse) {
            return view('error', compact('lead_statuse'));
        }
        $cities = Cities::where([
            ['account_id', '=', Auth::User()->account_id],
            ['slug', '=', 'custom'],
            ['active', '=', '1'],
            ['is_featured', '=', '1'],
        ])->get()->pluck('full_name', 'id');
        $cities->prepend('Select a City', '');

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'warehouse' => $warehouse,
            'cities' => $cities,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (!Gate::allows('warehouse_edit')) {
            return abort(401);
        }
        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first());
        }
        $warehouse = Warehouse::updateRecord($id, $request, Auth::User()->account_id);
        if ($warehouse) {
            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
        } else {
            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!Gate::allows('warehouse_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $result = Warehouse::deleteRecord($id);

        if ($result['status']) {
            return ApiHelper::apiResponse($this->success, $result['message']);
        }

        return ApiHelper::apiResponse($this->success, $result['message'], false);
    }

    public function status(Request $request)
    {
        if (!Gate::allows('warehouse_active')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $response = Warehouse::activeRecord($request->id, $request->status);
        if ($response) {
            return ApiHelper::apiResponse($this->success, 'Status has been changed successfully.');
        }
        return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
    }
}
