<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\NodesTree;
use App\Helpers\Widgets\MachineTypeWidget;
use App\Http\Controllers\Controller;
use App\Models\Locations;
use App\Models\MachineType;
use App\Models\ResourceHasServices;
use App\Models\Resources;
use App\Models\ResourceTypes;
use App\Models\Services;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\Resource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Validator;

class ResourcesController extends Controller
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
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! Gate::allows('resources_manage')) {
            return abort('401');
        }

        return view('admin.resources.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        if (! Gate::allows('resources_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            $resource_types = ResourceTypes::getallresource();

            $locations = Locations::where([
                ['active', '=', '1'],
                ['account_id', '=', Auth::User()->account_id],
                ['slug', '=', 'custom'],
            ])->whereIn('id', ACL::getUserCentres())->get()->pluck('full_address', 'id');

            $machinetypes = MachineType::where([
                ['active', '=', '1'],
                ['account_id', '=', '1'],
            ])->get()->pluck('name', 'id');

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'resource_types' => $resource_types,
                'locations' => $locations,
                'machine_types' => $machinetypes,
            ]);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * get the machine type against location id.
     *
     * @return \Illuminate\Http\Response
     */
    public function get_machinetype(Request $request)
    {
        if (! Gate::allows('resources_create')) {
            return abort('401');
        }
        $locationservice_ids = MachineTypeWidget::loadlocationservice($request->id, Auth::User()->account_id, true);

        $machinetypes = MachineType::where([
            ['active', '=', '1'],
            ['account_id', '=', '1'],
        ])->get();

        $machinetype_ids = [];

        foreach ($machinetypes as $machinetype) {

            $machinetypeservice_ids = MachineTypeWidget::loadmachinetypeservice($machinetype->id, Auth::User()->account_id, true);

            $containsSearch = count(array_intersect($machinetypeservice_ids, $locationservice_ids)) == count($machinetypeservice_ids);

            if ($containsSearch) {
                $machinetype_ids[] = $machinetype->id;
            }
        }
        $machinetypes = MachineType::whereIn('id', $machinetype_ids)->get();

        if (count($machinetypes) > 0) {
            return response()->json([
                'status' => true,
                'd' => view('admin.resources.services', compact('machinetypes'))->render(),
            ]);
        } else {
            return response()->json([
                'status' => false,
            ]);
        }
    }

    /*That Function is not in use that function give the assigned services of center if your select center */
    /**
     * get the service against location id.
     *
     * @return \Illuminate\Http\Response
     */
    public function get_service(Request $request)
    {
        if (! Gate::allows('resources_create')) {
            return abort('401');
        }

        $status_for_all = false;
        $allserviceslug = Services::where('slug', '=', 'all')->first();
        $location_id = $request->id;
        $Services = [];
        $result = [];
        $service_has_lcoation = DB::table('service_has_locations')->where('location_id', '=', $location_id)->get();
        foreach ($service_has_lcoation as $serviceall) {
            if ($serviceall->service_id == $allserviceslug->id) {
                $status_for_all = true;
            }
        }
        if ($status_for_all) {
            $parentGroups = new NodesTree();
            $parentGroups->current_id = -1;
            $parentGroups->build(0, Auth::User()->account_id, true, true);
            $parentGroups->toList($parentGroups, -1);
            $Services = $parentGroups->nodeList;
            foreach ($Services as $key => $ser) {
                if ($key) {
                    if ($ser['name'] == $allserviceslug->name) {
                        unset($Services[$key]);
                    }
                }
            }
        } else {
            foreach ($service_has_lcoation as $service) {
                $service_data = Services::find($service->service_id);
                $parentGroups = new NodesTree();
                $parentGroups->current_id = 1;
                $parentGroups->non_negative_groups = true;
                $parentGroups->build($service_data->id, Auth::User()->account_id, false, true);
                $parentGroups->toList($parentGroups, 0);
                $Services[] = $parentGroups->nodeList;
            }
        }
        if (count($Services) > 0) {
            return response()->json([
                'status' => true,
                'd' => view('admin.resources.services', compact('Services', 'status_for_all'))->render(),
            ]);
        } else {
            return response()->json([
                'status' => false,
            ]);
        }
    }

    /*End*/
    /*
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! Gate::allows('resources_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            $validator = $this->verifyFields($request);

            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
            }
            if ($resource = Resources::createRecord($request, Auth::User()->account_id)) {
                /*For now I comment that code because that not in use*/
                //            $data = $request->all();
                //
                //            if (isset($data['services']) && count($data['services'])) {
                //                $servicesData = array();
                //                foreach ($data['services'] as $service) {
                //                    $servicesData = array(
                //                        'resource_id' => $resource->id,
                //                        'service_id' => $service,
                //                    );
                //                    ResourceHasServices::createRecord($servicesData, $resource);
                //                }
                //            }
                /*End*/

                return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Validate form fields
     *
     * @return Validator $validator;
     */
    protected function verifyFields(Request $request)
    {
        return Validator::make($request->all(), [
            'name' => 'required',
            'resource_type_id' => 'required',
            'location_id' => 'required',
            'machine_type_id' => 'required',
        ]);
    }

    /**
     * Display the resources in datatable.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {
            $filename = 'resources';
            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);

            $records = [];
            $records['data'] = [];

            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $resources = Resources::getBulkData($ids);
                if ($resources) {
                    foreach ($resources as $resource) {
                        // Check if child records exists or not, If exist then disallow to delete it.
                        if (! Resources::isChildExists($resource->id, Auth::User()->account_id)) {
                            $resource->delete();
                        }
                    }
                }

                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }
            // Get Total Records
            $iTotalRecords = Resources::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $resources = Resources::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

            $records = $this->filtersData($records);

            if ($resources) {

                $records['data'] = $resources;

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
                'edit' => Gate::allows('resources_edit'),
                'delete' => Gate::allows('resources_destroy'),
                'active' => Gate::allows('resources_active'),
                'inactive' => Gate::allows('resources_inactive'),
                'create' => Gate::allows('resources_create'),
            ];

            return ApiHelper::apiDataTable($records);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    private function filtersData($records)
    {

        //Here we get all resource except doctor
        $filters = Filters::all(Auth::User()->id, 'resources');

        $resource_types = ResourceTypes::getallresource();

        $locations = Locations::getActiveSorted(ACL::getUserCentres(), 'full_address');

        $machinetypes = MachineType::where([
            ['active', '=', '1'],
            ['account_id', '=', '1'],
        ])->get()->pluck('name', 'id');

        $records['filter_values'] = [
            'machines' => $machinetypes,
            'resource_types' => $resource_types,
            'locations' => $locations,
            'status' => config('constants.status'),
        ];

        if (isset($filters['created_from'])) {
            $filters['created_from'] = date('Y-m-d', strtotime($filters['created_from']));
        }
        if (isset($filters['created_to'])) {
            $filters['created_to'] = date('Y-m-d', strtotime($filters['created_to']));
        }

        $records['active_filters'] = $filters;

        return $records;
    }

    /**
     * Inactive Record from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        if (! Gate::allows('resources_active')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        try {

            if ($request->status == 1) {
                $response = Resources::activeRecord($request->id);
            } else {
                $response = Resources::inactiveRecord($request->id);
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
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        if (! Gate::allows('resources_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $resource = Resources::getData($id);

        $resource_types = ResourceTypes::getallresource();
        $resource_types->prepend('Select a Resource Type', '');

        $locations = Locations::where([
            //            ['active', '=', '1'],
            ['account_id', '=', Auth::User()->account_id],
            ['slug', '=', 'custom'],
        ])->whereIn('id', ACL::getUserCentres())->get()->pluck('full_address', 'id');

        $locationservice_ids = MachineTypeWidget::loadlocationservice($resource->location_id, Auth::User()->account_id, true);

        $machinetypes = MachineType::where([
            ['active', '=', '1'],
            ['account_id', '=', '1'],
        ])->get();

        $machinetype_ids = [];

        foreach ($machinetypes as $machinetype) {

            $machinetypeservice_ids = MachineTypeWidget::loadmachinetypeservice($machinetype->id, Auth::User()->account_id, true);

            $containsSearch = count(array_intersect($machinetypeservice_ids, $locationservice_ids)) == count($machinetypeservice_ids);

            if ($containsSearch) {
                $machinetype_ids[] = $machinetype->id;
            }
        }

        $machinetypes = MachineType::whereIn('id', $machinetype_ids)->get()->pluck('name', 'id');

        if (! $resource) {
            return ApiHelper::apiResponse($this->success, 'Resource not found', false);
        }

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'resource' => $resource,
            'resource_types' => $resource_types,
            'machine_types' => $machinetypes,
            'locations' => $locations,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! Gate::allows('resources_edit')) {
            return abort('401');
        }
        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->all(),
            ]);
        }

        if ($resource = Resources::updateRecord($id, $request, Auth::User()->account_id)) {
            /*For now I comment that code because that not in use*/
            //            $resource->resource_has_services()->delete();
            //
            //            $data = $request->all();
            //
            //            if (isset($data['services']) && count($data['services'])) {
            //                $servicesData = array();
            //                foreach ($data['services'] as $service) {
            //                    $servicesData = array(
            //                        'resource_id' => $resource->id,
            //                        'service_id' => $service,
            //                    );
            //                    ResourceHasServices::updateRecord($servicesData, $resource);
            //                }
            //            }
            /*End*/
            flash('Record has been updated successfully.')->success()->important();

            return response()->json([
                'status' => 1,
                'message' => 'Record has been updated successfully.',
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => 'Something went wrong, please try again later.',
            ]);
        }
    }

    /*
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! Gate::allows('resources_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        try {

            $response = Resources::deleteRecord($id);

            if ($response['status']) {
                return ApiHelper::apiResponse($this->success, $response['message']);
            }

            return ApiHelper::apiResponse($this->success, $response['message'], false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
