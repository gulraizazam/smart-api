<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Filters;
use App\Models\MachineType;
use App\Models\ResourceHasServices;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\Resource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use App\Models\Resources;
use App\Models\Locations;
use App\Models\Services;
use App\Helpers\NodesTree;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Validator;
use App\Models\ResourceTypes;
use Carbon\Carbon;
use App\Helpers\ACL;
use App\Helpers\Widgets\MachineTypeWidget;

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
        if (!Gate::allows('resources_manage')) {
            return abort('401');
        }

        return view('admin.resources.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!Gate::allows('resources_create')) {
            return abort('401');
        }

        $resource_types = ResourceTypes::getallresource();
        $resource_types->prepend('Select a Resource Type', '');

        $locations = Locations::where([
            ['active', '=', '1'],
            ['account_id', '=', Auth::User()->account_id],
            ['slug', '=', 'custom']
        ])->whereIn('id', ACL::getUserCentres())->get()->pluck('full_address', 'id');

        return view('admin.resources.create', compact('resource_types', 'locations'));
    }

    /**
     * get the machine type against location id.
     *
     * @return \Illuminate\Http\Response
     */
    public function get_machinetype(Request $request)
    {
        if (!Gate::allows('resources_create')) {
            return abort('401');
        }
        $locationservice_ids = MachineTypeWidget::loadlocationservice($request->id, Auth::User()->account_id, true);

        $machinetypes = MachineType::where([
            ['active', '=', '1'],
            ['account_id', '=', '1']
        ])->get();

        $machinetype_ids = array();

        foreach ($machinetypes as $machinetype) {

            $machinetypeservice_ids = MachineTypeWidget::loadmachinetypeservice($machinetype->id, Auth::User()->account_id, true);

            $containsSearch = count(array_intersect($machinetypeservice_ids, $locationservice_ids)) == count($machinetypeservice_ids);

            if ($containsSearch) {
                $machinetype_ids[] = $machinetype->id;
            }
        }
        $machinetypes = MachineType::whereIn('id', $machinetype_ids)->get();

        if (count($machinetypes) > 0) {
            return response()->json(array(
                'status' => true,
                'd' => view('admin.resources.services', compact('machinetypes'))->render(),
            ));
        } else {
            return response()->json(array(
                'status' => false,
            ));
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
        if (!Gate::allows('resources_create')) {
            return abort('401');
        }

        $status_for_all = false;
        $allserviceslug = Services::where('slug', '=', 'all')->first();
        $location_id = $request->id;
        $Services = [];
        $result = array();
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
            return response()->json(array(
                'status' => true,
                'd' => view('admin.resources.services', compact('Services', 'status_for_all'))->render(),
            ));
        } else {
            return response()->json(array(
                'status' => false,
            ));
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
        if (!Gate::allows('resources_create')) {
            return abort('401');
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->messages()->all(),
            ));
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

            flash('Record has been created successfully.')->success()->important();

            return response()->json(array(
                'status' => 1,
                'message' => 'Record has been created successfully.',
            ));
        } else {
            return response()->json(array(
                'status' => 0,
                'message' => 'Something went wrong, please try again later.',
            ));
        }
    }

    /**
     * Validate form fields
     *
     * @param \Illuminate\Http\Request $request
     * @return Validator $validator;
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
            'resource_type_id' => 'required',
            'location_id' => 'required',
            'machine_type_id' => 'required',
        ]);
    }

    /**
     * Display the resources in datatable.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request)
    {

        $filename = 'resources';

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, $filename);

        $records = array();
        $records["data"] = array();

        if(count($filters) > 0 && hasFilter($filters, 'delete')  != '') {
            $ids = explode(',', $filters['delete']);
            $resources = Resources::getBulkData($ids);
            if ($resources) {
                foreach ($resources as $resource) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (!Resources::isChildExists($resource->id, Auth::User()->account_id)) {
                        $resource->delete();
                    }
                }
            }

            $records["status"] = true;
            $records["message"] = "Records has been deleted successfully!";
        }
        // Get Total Records
        $iTotalRecords = Resources::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

        list($orderBy, $order) = getSortBy($request);
        list( $iDisplayLength, $iDisplayStart, $pages, $page) = getPaginationElement($request, $iTotalRecords);

        $resources = Resources::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);



        $records = $this->filtersData($records);

        if ($resources) {
            foreach ($resources as $resource) {
                $resourcetype = ResourceTypes::where('id', '=', $resource->resource_type_id)->first();
                $location = Locations::where('id', '=', $resource->location_id)->first();
                $records["data"][] = array(
                    'id' => $resource->id,
                    'name' => $resource->name,
                    'resource_types' => $resourcetype->name,
                    'location' => $location->city->name . ' - ' . $location->name,
                    'machine_type' => $resource->machine_type_id ? $resource->MachineType->name : 'N/A',
                    'created_at' => Carbon::parse($resource->created_at)->format('F j,Y h:i A'),
                    'status' => view('admin.resources.status', compact('resource'))->render(),
                    'actions' => view('admin.resources.actions', compact('resource'))->render(),
                );
            }

            $records["meta"] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];
        }

        return response()->json($records);
    }


    private function filtersData($records) {

        //Here we get all resource except doctor
        $filters = Filters::all(Auth::User()->id, 'resources');

        $resource_types = ResourceTypes::getallresource();
        $resource_types->prepend('All', '');

        $locations = Locations::getActiveSorted(ACL::getUserCentres(), 'full_address');
        $locations->prepend('All', '');

        $machinetypes = MachineType::where([
            ['active', '=', '1'],
            ['account_id', '=', '1']
        ])->get()->pluck('name', 'id');
        $machinetypes->prepend('All', '');

        $records['filter_values'] = [
            'services' => $machinetypes,
            'resource_types' => $resource_types,
            'locations' => $locations,
            'filters' => $filters,
        ];

        $records['active_filters'] = $filters;

        return $records;
    }

    /**
     * Inactive Record from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function inactive($id)
    {
        if (!Gate::allows('resources_inactive')) {
            return abort(401);
        }
        Resources::inactiveRecord($id);
        return redirect()->route('admin.resources.index');
    }

    /**
     * Inactive Record from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function active($id)
    {
        if (!Gate::allows('resources_active')) {
            return abort(401);
        }
        Resources::activeRecord($id);

        return redirect()->route('admin.resources.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!Gate::allows('resources_edit')) {
            return abort('401');
        }

        $resource = Resources::getData($id);

        $resource_types = ResourceTypes::getallresource();
        $resource_types->prepend('Select a Resource Type', '');

        $locations = Locations::where([
//            ['active', '=', '1'],
            ['account_id', '=', Auth::User()->account_id],
            ['slug', '=', 'custom']
        ])->whereIn('id', ACL::getUserCentres())->get()->pluck('full_address', 'id');

        $locationservice_ids = MachineTypeWidget::loadlocationservice($resource->location_id, Auth::User()->account_id, true);

        $machinetypes = MachineType::where([
            ['active', '=', '1'],
            ['account_id', '=', '1']
        ])->get();

        $machinetype_ids = array();

        foreach ($machinetypes as $machinetype) {

            $machinetypeservice_ids = MachineTypeWidget::loadmachinetypeservice($machinetype->id, Auth::User()->account_id, true);

            $containsSearch = count(array_intersect($machinetypeservice_ids, $locationservice_ids)) == count($machinetypeservice_ids);

            if ($containsSearch) {
                $machinetype_ids[] = $machinetype->id;
            }
        }

        $machinetypes = MachineType::whereIn('id', $machinetype_ids)->get()->pluck('name', 'id');

        if (!$resource) {
            return view('error', compact('resource'));
        }
        return view('admin.resources.edit', compact('resource', 'resource_types', 'machinetypes', 'locations'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (!Gate::allows('resources_edit')) {
            return abort('401');
        }
        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->messages()->all(),
            ));
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

            return response()->json(array(
                'status' => 1,
                'message' => 'Record has been updated successfully.',
            ));
        } else {
            return response()->json(array(
                'status' => 0,
                'message' => 'Something went wrong, please try again later.',
            ));
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
        if (!Gate::allows('resources_destroy')) {
            return abort(401);
        }

        Resources::deleteRecord($id);

        return redirect()->route('admin.resources.index');
    }
}
