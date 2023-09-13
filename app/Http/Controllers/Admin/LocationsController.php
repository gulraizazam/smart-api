<?php

namespace App\Http\Controllers\Admin;

use Validator;
use Carbon\Carbon;
use App\Helpers\ACL;
use App\Models\Cities;
use App\Models\Regions;
use App\Helpers\Filters;
use App\Models\Services;
use App\Models\Locations;
use App\Helpers\NodesTree;
use Illuminate\Http\Request;
use App\HelperModule\ApiHelper;
use App\Models\UserHasLocations;
use App\Helpers\GeneralFunctions;
use App\Models\ServiceHasLocations;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Helpers\Widgets\ServiceWidget;
use App\Helpers\Widgets\LocationsWidget;

class LocationsController extends Controller
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
     * Display a listing of Location.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! Gate::allows('locations_manage')) {
            return abort(401);
        }

        return view('admin.locations.index');
    }

    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request)
    {
        $filename = 'locations';

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, $filename);

        $records = [];
        $records['data'] = [];

        if (count($filters) > 0 && hasFilter($filters, 'delete') != '') {
            $ids = explode(',', $filters['delete']);
            $Locations = Locations::getBulkData($ids);
            if ($Locations) {

                foreach ($Locations as $Location) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! Locations::isChildExists($Location->id, Auth::User()->account_id)) {
                        $Location->delete();
                    }
                }
            }
            $records['status'] = true;
            $records['message'] = 'Records has been deleted successfully!';
        }

        [$orderBy, $order] = getSortBy($request);

        // Get Total Records
        $iTotalRecords = Locations::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $Locations = Locations::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

        $Services = Services::getAllRecordsDictionary(Auth::User()->account_id);
        $Cities = Cities::getAllRecordsDictionary(Auth::User()->account_id);
        $Regions = Regions::getAllRecordsDictionary(Auth::User()->account_id);

        $records = $this->getExtraData($records);

        if ($Locations->count()) {
            foreach ($Locations as $location) {

                // $city = Cities::getData($location->id);

                /*
                 * Record Level Services process start
                 */
                $_services = '';

                $locationServices = ServiceHasLocations::where(['location_id' => $location->id])->get()->pluck('service_id');
                if (! $locationServices->isEmpty() && count($locationServices)) {
                    foreach ($locationServices as $_location) {
                        if (array_key_exists($_location, $Services)) {
                            $_services .= '<span class="label label-sm label-info">'.$Services[$_location]->name.'</span>&nbsp;';
                        }
                    }
                }
                /*
                 * Record Level Services process end
                 */

                $records['data'][] = [
                    'id' => $location->id,
                    'name' => $location->name,
                    'fdo_name' => $location->fdo_name ? $location->fdo_name : 'N/A',
                    'fdo_phone' => $location->fdo_phone ? GeneralFunctions::prepareNumber4CallSMS($location->fdo_phone) : 'N/A',
                    'address' => $location->address,
                    'city' => (array_key_exists($location->city_id, $Cities)) ? $Cities[$location->city_id]->name : 'N/A',
                    'region' => (array_key_exists($location->region_id, $Regions)) ? $Regions[$location->region_id]->name : 'N/A',
                    'service' => ($_services) ? $_services : 'N/A',
                    'active' => $location->active,
                    'created_at' => Carbon::parse($location->created_at)->format('F j,Y h:i A'),
                ];

            }

            $records['permissions'] = [
                'edit' => Gate::allows('locations_edit'),
                'delete' => Gate::allows('locations_destroy'),
                'active' => Gate::allows('locations_active'),
                'inactive' => Gate::allows('locations_inactive'),
                'create' => Gate::allows('locations_create'),
                'sort' => Gate::allows('locations_sort'),
            ];

            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];

        } //end
        return response()->json($records);
    }

    private function getExtraData($records = [])
    {

        $filters = Filters::all(Auth::User()->id, 'locations');

        $cities = Cities::where([
            ['account_id', '=', Auth::User()->account_id],
            ['slug', '=', 'custom'],
            ['active', '=', '1'],
            ['is_featured', '=', '1'],
        ])->get()->pluck('name', 'id');

        $regions = Regions::getActiveSorted(ACL::getUserRegions());

        /* Create Nodes with Parents */
        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $records['filter_values'] = [
            'cities' => $cities,
            'regions' => $regions,
            'services' => $Services,
            'status' => config('constants.status'),
        ];

        $records['active_filters'] = $filters;

        return $records;
    }

    /**
     * Show the form for creating new Location.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        if (! Gate::allows('locations_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        /*Get Service as we get in resource create module*/
        $Services = GeneralFunctions::ServicesTreeList();

        $cities = Cities::where([
            ['account_id', '=', Auth::User()->account_id],
            ['slug', '=', 'custom'],
            ['active', '=', '1'],
            ['is_featured', '=', '1'],
        ])->get()->pluck('full_name', 'id');
        $ServiceLocations = [];

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'services' => $Services,
            'service_location' => $ServiceLocations,
            'cities' => $cities,
        ]);
    }

    /**
     * Store a newly created Location in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if (! Gate::allows('locations_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $validator = $this->verifyFields($request);
        if ($validator->fails()) {

            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        if ($location = Locations::createRecord($request, Auth::User()->account_id)) {
            $locatUser = [];
            $location_slug_all = Locations::where('slug', '=', 'all')->first();
            $user_has_location_data = UserHasLocations::where('location_id', '=', $location_slug_all->id ?? 0)->groupby('user_id')->get();
            if (count($user_has_location_data) > 0) {
                foreach ($user_has_location_data as $user) {
                    $user_has_locations = [
                        'user_id' => $user->user_id,
                        'region_id' => $location->region_id,
                        'location_id' => $location->id,
                    ];
                    // Insert assigned centres to User
                    UserHasLocations::createRecord($user_has_locations, $user->user_id);
                }
            }
            $user_already_have = UserHasLocations::where('location_id', '=', $location->id)->select('user_id')->groupby('user_id')->get();
            $user_already_have_location = [];
            foreach ($user_already_have as $users) {
                $user_already_have_location[] = $users->user_id;
            }
            $head_region = Locations::where([
                ['slug', '=', 'region'],
                ['region_id', '=', $location->region_id],
            ])->first();
            $user_has_location_data = UserHasLocations::where([
                ['location_id', '=', $head_region->id ?? 0],
                ['location_id', '!=', $location->id ?? 0],
            ])->select('user_id')->groupby('user_id')->get();

            foreach ($user_has_location_data as $Need_to_lcoateuser) {
                if (! in_array($Need_to_lcoateuser->user_id, $user_already_have_location)) {
                    $locatUser[] = $Need_to_lcoateuser->user_id;
                }
            }
            if (count($locatUser) > 0) {
                foreach ($locatUser as $user) {
                    $user_has_locations = [
                        'user_id' => $user,
                        'region_id' => $location->region_id,
                        'location_id' => $location->id,
                    ];
                    // Insert assigned centres to User
                    UserHasLocations::createRecord($user_has_locations, $user);
                }
            }
            /*
             * Prepare services data for location
             */
            $data = $request->all();
            /*
             * New Audit Trail Process
             */
            if (isset($data['services']) && count($data['services'])) {
                $services = LocationsWidget::generateservicearray($data['services'], Auth::User()->account_id);
                $servicesData = [];
                foreach ($services as $service) {
                    $servicesData = [
                        'service_id' => $service,
                        'location_id' => $location->id,
                        'account_id' => Auth::User()->account_id,
                    ];
                    ServiceHasLocations::createRecord($servicesData, $location);
                }
            }

            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        } else {
            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        }
    }

    /**
     * Validate form fields
     *
     * @return Validator $validator;
     */
    protected function verifyFields(Request $request)
    {
        return $validator = \Validator::make($request->all(), [
            'name' => 'required',
            'fdo_name' => 'required',
            'fdo_phone' => 'required',
            'address' => 'required',
            'google_map' => 'required',
            'city_id' => 'required',
            'ntn' => 'required',
            'stn' => 'required',
            /*'ntn' => ['required', 'regex:/^([0-9]|\.|\+|\*|\-|\_|\#)*$/'],
            'stn' => ['required', 'regex:/^([0-9]|\.|\+|\*|\-|\_|\#)*$/'],*/
        ]);
    }

    /**
     * Show the form for editing Location.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        if (! Gate::allows('locations_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $location = Locations::getData($id);
        if (! $location) {
            return view('error', compact('lead_statuse'));
        }
        $ServiceLocations = $location->service_has_locations()->pluck('service_id')->toArray();
        $cities = Cities::where([
            ['account_id', '=', Auth::User()->account_id],
            ['slug', '=', 'custom'],
            ['active', '=', '1'],
            ['is_featured', '=', '1'],
        ])->get()->pluck('full_name', 'id');
        $cities->prepend('Select a City', '');
        $Services = GeneralFunctions::ServicesTreeList();

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'location' => $location,
            'services' => $Services,
            'service_location' => $ServiceLocations,
            'cities' => $cities,
        ]);
    }

    /**
     * Update Location in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        if (! Gate::allows('locations_edit')) {
            return abort(401);
        }
        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first());
        }
        if ($location = Locations::updateRecord($id, $request, Auth::User()->account_id)) {

            $location->service_has_locations()->delete();

            $data = $request->all();

            /*
             * Prepare services data for location
             */

            if (isset($data['services']) && count($data['services'])) {
                $servicesData = [];
                $services = LocationsWidget::generateservicearray($data['services'], Auth::User()->account_id);
                foreach ($services as $service) {
                    $servicesData = [
                        'service_id' => $service,
                        'location_id' => $location->id,
                        'account_id' => Auth::User()->account_id,
                    ];
                    ServiceHasLocations::updateRecord($servicesData, $location);
                }
            }

            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');

        } else {

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        }
    }

    /**
     * Remove Location from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        if (! Gate::allows('locations_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $result = Locations::deleteRecord($id);

        if ($result['status']) {
            return ApiHelper::apiResponse($this->success, $result['message']);
        }

        return ApiHelper::apiResponse($this->success, $result['message'], false);
    }

    public function status(Request $request)
    {
        if (! Gate::allows('locations_active')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $response = Locations::activeRecord($request->id, $request->status);

        if ($response) {
            return ApiHelper::apiResponse($this->success, 'Status has been changed successfully.');
        }

        return ApiHelper::apiResponse($this->success, 'Resource not found.', false);

    }

    /**
     * function for index Sort Order.
     */
    public function getSortOrder()
    {
        if (! Gate::allows('locations_sort')) {
            return abort(401);
        }

        return view('admin.locations.Sort');
    }

    public function sortorder()
    {
        if (! Gate::allows('locations_sort')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $locations = Locations::whereNull('deleted_at')->whereSlug('custom')->where(['account_id' => Auth::User()->account_id])->orderby('sort_no', 'ASC')->get();

        return ApiHelper::apiResponse($this->success, 'Success', true, $locations);
    }

    /**
     * function for Sort Order.
     */
    public function sortorder_save(Request $request)
    {
        if (! Gate::allows('locations_sort')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $itemIDs = $request->item_ids;
        if (count($itemIDs)) {
            foreach ($itemIDs as $key => $itemID) {
                $sort = Locations::where('id', '=', $itemID)->update(['sort_no' => $key]);
            }

            return ApiHelper::apiResponse($this->success, 'Records are sorted Successfully!');
        } else {
            return ApiHelper::apiResponse($this->success, 'Data Not Sort', false);
        }
    }

    /**
     * Store a newly created Location and checked attribute exists or not.
     *
     * @return \Illuminate\Http\Response
     */
    public function verify(Request $request)
    {
        if (! Gate::allows('locations_create')) {
            return abort(401);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->all(),
            ]);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Record has been verified successfully.',
        ]);
    }

    /**
     * updated Location and verify edit attribute exists or not
     *
     * @return \Illuminate\Http\Response
     */
    public function verify_edit(Request $request)
    {
        if (! Gate::allows('locations_create')) {
            return abort(401);
        }

        $validator = $this->verifyFields($request);
        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->all(),
            ]);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Record has been verified successfully.',
        ]);
    }
    public function getServices(Request $request)
    {
        $serive = ServiceWidget::generateServiceArrayArray($request, Auth::User()->account_id);

        $myarray = ['services' => $serive, 'locaiton_id_1' => $request->id];

        return ApiHelper::apiResponse($this->success, 'Success', true, $myarray);
    }
}
