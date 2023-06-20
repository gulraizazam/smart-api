<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\Appointments;
use App\Models\Cities;
use App\Models\Doctors;
use App\Models\Locations;
use App\Models\Regions;
use App\Models\ResourceHasRota;
use App\Models\ResourceHasRotaDays;
use App\Models\Resources;
use App\Models\ResourceTypes;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Exception;

class ResourceRotasController extends Controller
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
        if (! Gate::allows('resourcerotas_manage')) {
            return abort('401');
        }

        return view('admin.resourcerotas.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        if (! Gate::allows('resourcerotas_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            $resourcetype = ResourceTypes::getResourceforrota();

            $cities = Cities::getActiveFeaturedOnly(ACL::getUserCities(), Auth::User()->account_id)->get();
            if ($cities) {
                $cities = $cities->pluck('full_name', 'id');
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'resource_types' => $resourcetype,
                'cities' => $cities,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * get the location against city id.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function load_location(Request $request)
    {

        try {

            if ($request->get('city_id')) {
                $locations = Locations::getActiveRecordsByCity($request->get('city_id'), ACL::getUserCentres(), Auth::User()->account_id);

                return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                    'locations' => $locations,
                ]);
            }

            return ApiHelper::apiResponse($this->success, 'Record not found.', false);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * get the doctors and machine against location id.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function load_doctor_and_Machine(Request $request)
    {

        try {

            if ($request->get('location_id')) {
                $doctors = Doctors::getActiveOnly($request->get('location_id'));
                $machine = Resources::getActiveOnly($request->get('location_id'));

                return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                    'doctors' => $doctors,
                    'machine' => $machine,
                ]);

            }

            return ApiHelper::apiResponse($this->success, 'Record not found.', false);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Store a newly created resource rota in storage/Database.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if (! Gate::allows('resourcerotas_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }

        if ($request->is_consultancy == '0' && $request->is_treatment == '0') {
            return ApiHelper::apiResponse($this->error, 'Please check at least one from consultancy and treatment');
        }

        $response = ResourceHasRota::createRecord($request, Auth::User()->account_id);

        if ($response['status']) {
            return ApiHelper::apiResponse($this->success, $response['message']);
        }

        return ApiHelper::apiResponse($this->success, $response['message'], false);

    }

    /**
     * Validate form fields
     *
     * @return \Illuminate\Contracts\Validation\Validator $validator;
     */
    protected function verifyFields(Request $request)
    {
        return Validator::make($request->all(), [
            'start' => 'required',
            'end' => 'required',

        ]);
    }

    /**
     * The function for display resoruce rota in datatable
     *
     * @param  Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {

        try {

            $fileName = 'resourcehasrota';

            $filters = getFilters($request->all());

            $apply_filter = checkFilters($filters, $fileName);

            $records = [];
            $records['data'] = [];

            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $resourcehasrota = ResourceHasRota::getBulkData($ids);
                if ($resourcehasrota) {
                    foreach ($resourcehasrota as $resourcehasrota) {
                        // Check if child records exists or not, If exist then disallow to delete it.
                        if (! ResourceHasRota::isChildExists($resourcehasrota->id, Auth::User()->account_id)) {
                            $resourcehasrota->delete();
                        }
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            [$orderBy, $order] = getSortBy($request, 'created_at', 'DESC');

            $where = [];
            $wherename = [];

            if (hasFilter($filters, 'resource_type_id')) {
                $where[] = [
                    'resource_has_rota.resource_type_id',
                    '=',
                    $filters['resource_type_id'],
                ];
                Filters::put(Auth::User()->id, $fileName, 'resource_type_id', $filters['resource_type_id']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $fileName, 'resource_type_id');
                } else {
                    if (Filters::get(Auth::User()->id, $fileName, 'resource_type_id')) {
                        $where[] = [
                            'resource_has_rota.resource_type_id',
                            '=',
                            Filters::get(Auth::User()->id, $fileName, 'resource_type_id'),
                        ];
                    }
                }
            }

            if (hasFilter($filters, 'region_id')) {
                $where[] = [
                    'resource_has_rota.region_id',
                    '=',
                    $filters['region_id'],
                ];
                Filters::put(Auth::User()->id, $fileName, 'region_id', $filters['region_id']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $fileName, 'region_id');
                } else {
                    if (Filters::get(Auth::User()->id, $fileName, 'region_id')) {
                        $where[] = [
                            'resource_has_rota.region_id',
                            '=',
                            Filters::get(Auth::User()->id, $fileName, 'region_id'),
                        ];
                    }
                }
            }

            if (hasFilter($filters, 'city_id')) {
                $where[] = [
                    'resource_has_rota.city_id',
                    '=',
                    $filters['city_id'],
                ];
                Filters::put(Auth::User()->id, $fileName, 'city_id', $filters['city_id']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $fileName, 'city_id');
                } else {
                    if (Filters::get(Auth::User()->id, $fileName, 'city_id')) {
                        $where[] = [
                            'resource_has_rota.city_id',
                            '=',
                            Filters::get(Auth::User()->id, $fileName, 'city_id'),
                        ];
                    }
                }
            }

            if (hasFilter($filters, 'location_id')) {
                $where[] = [
                    'resource_has_rota.location_id',
                    '=',
                    $filters['location_id'],
                ];
                Filters::put(Auth::User()->id, $fileName, 'location_id', $filters['location_id']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $fileName, 'location_id');
                } else {
                    if (Filters::get(Auth::User()->id, $fileName, 'location_id')) {
                        $where[] = [
                            'resource_has_rota.location_id',
                            '=',
                            Filters::get(Auth::User()->id, $fileName, 'location_id'),
                        ];
                    }
                }
            }

            if (hasFilter($filters, 'account_id')) {
                $where[] = [
                    'resource_has_rota.account_id',
                    '=',
                    Auth::User()->account_id,
                ];
                Filters::put(Auth::User()->id, $fileName, 'account_id', Auth::User()->account_id);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $fileName, 'account_id');
                } else {
                    if (Filters::get(Auth::User()->id, $fileName, 'account_id')) {
                        $where[] = [
                            'resource_has_rota.account_id',
                            '=',
                            Filters::get(Auth::User()->id, $fileName, 'account_id'),
                        ];
                    }
                }
            }

            if (hasFilter($filters, 'resourcename')) {
                $wherename[] = [
                    'resources.name',
                    'like',
                    '%'.$filters['resourcename'].'%',
                ];
                Filters::put(Auth::User()->id, $fileName, 'resourcename', $filters['resourcename']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $fileName, 'resourcename');
                } else {
                    if (Filters::get(Auth::User()->id, $fileName, 'resourcename')) {
                        $wherename[] = [
                            'resources.name',
                            'like',
                            '%'.Filters::get(Auth::User()->id, $fileName, 'resourcename').'%',
                        ];
                    }
                }
            }

            if (hasFilter($filters, 'startdate')) {
                $where[] = [
                    'resource_has_rota.start',
                    '>=',
                    $filters['startdate'],
                ];
                Filters::put(Auth::user()->id, $fileName, 'startdate', $filters['startdate']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $fileName, 'startdate');
                } else {
                    if (Filters::get(Auth::User()->id, $fileName, 'startdate')) {
                        $where[] = [
                            'resource_has_rota.start',
                            '>=',
                            Filters::get(Auth::user()->id, $fileName, 'startdate'),
                        ];
                    }
                }
            }

            if (hasFilter($filters, 'enddate')) {
                $where[] = [
                    'resource_has_rota.end',
                    '<=',
                    $filters['enddate'],
                ];
                Filters::put(Auth::user()->id, $fileName, 'enddate', $filters['enddate']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $fileName, 'enddate');
                } else {
                    if (Filters::get(Auth::User()->id, $fileName, 'enddate')) {
                        $where[] = [
                            'resource_has_rota.end',
                            '<=',
                            Filters::get(Auth::user()->id, $fileName, 'enddate'),
                        ];
                    }
                }
            }

            if (hasFilter($filters, 'created_from')) {
                $where[] = [
                    'resource_has_rota.created_at',
                    '>=',
                    $filters['created_from'].' 00:00:00',
                ];
                Filters::put(Auth::User()->id, $fileName, 'created_from', $filters['created_from'].' 00:00:00');
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $fileName, 'created_from');
                } else {
                    if (Filters::get(Auth::User()->id, $fileName, 'created_from')) {
                        $where[] = [
                            'resource_has_rota.created_at',
                            '>=',
                            Filters::get(Auth::User()->id, $fileName, 'created_from').' 00:00:00',
                        ];
                    }
                }
            }

            if (hasFilter($filters, 'created_to')) {
                $where[] = [
                    'resource_has_rota.created_at',
                    '<=',
                    $filters['created_to'].' 23:59:59',
                ];
                Filters::put(Auth::User()->id, $fileName, 'created_to', $filters['created_to'].' 23:59:59');
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $fileName, 'created_to');
                } else {
                    if (Filters::get(Auth::User()->id, $fileName, 'created_to')) {
                        $where[] = [
                            'resource_has_rota.created_at',
                            '<=',
                            Filters::get(Auth::User()->id, $fileName, 'created_to').' 23:59:59',
                        ];
                    }
                }
            }

            if (hasFilter($filters, 'status')) {
                $where[] = [
                    'resource_has_rota.active',
                    '=',
                    $filters['status'],
                ];
                Filters::put(Auth::user()->id, $fileName, 'status', $filters['status']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::user()->id, $fileName, 'status');
                } else {
                    if (Filters::get(Auth::user()->id, $fileName, 'status') == 0 || Filters::get(Auth::user()->id, 'resourcehasrota', 'status') == 1) {
                        if (Filters::get(Auth::user()->id, $fileName, 'status') != null) {
                            $where[] = [
                                'resource_has_rota.active',
                                '=',
                                Filters::get(Auth::user()->id, $fileName, 'status'),
                            ];
                        }
                    }
                }
            }

            $total_query = Resources::join('resource_has_rota', 'resources.id', '=', 'resource_has_rota.resource_id')
                ->whereNull('resource_has_rota.deleted_at')
                ->where($wherename)->whereIn('resource_has_rota.location_id', ACL::getUserCentres())
                ->select('resource_has_rota.id');
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_rotas')) {
                $total_query->when(count($where) > 0, fn ($q) => $q->where($where));
            } else {
                $total_query->when(count($where) > 0, fn ($q) => $q->where($where)->where('resource_has_rota.active', 1));
            }

            $iTotalRecords = $total_query->count();

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_rotas')) {
                $query = Resources::join('resource_has_rota', 'resources.id', '=', 'resource_has_rota.resource_id')->where($wherename)
                    ->whereNull('resource_has_rota.deleted_at')->whereIn('resource_has_rota.location_id', ACL::getUserCentres())
                    ->select('resource_has_rota.*');
            } else {
                $query = Resources::join('resource_has_rota', 'resources.id', '=', 'resource_has_rota.resource_id')->where($wherename)
                    ->whereNull('resource_has_rota.deleted_at')->where('resource_has_rota.active', 1)->whereIn('resource_has_rota.location_id', ACL::getUserCentres())
                    ->select('resource_has_rota.*');
            }
            $query->when(hasFilter($filters, 'startdate'), fn ($q) => $q->whereDate('resource_has_rota.start', '>=', $filters['startdate'])
            );

            $query->when(hasFilter($filters, 'startdate'), fn ($q) => $q->whereDate('resource_has_rota.end', '<=', $filters['enddate'])
            );

            $query->when(count($where) > 0, fn ($q) => $q->where($where));

            $resourcehasrota = $query->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy, $order)->get();
            $Regions = Regions::getAllRecordsDictionary(Auth::User()->account_id);

            $records = $this->filtersData($records);

            if ($resourcehasrota) {
                foreach ($resourcehasrota as $resourcerota) {
                    $resourcetypeinfo = ResourceTypes::where('id', '=', $resourcerota->resource_type_id)->first();
                    $resourceinfo = Resources::where('id', '=', $resourcerota->resource_id)->first();
                    $city = Cities::where('id', '=', $resourcerota->city_id)->first();
                    $location = Locations::where('id', '=', $resourcerota->location_id)->first();
                    $records['data'][] = [
                        'id' => $resourcerota->id,
                        'name' => $resourceinfo->name,
                        'type' => $resourcetypeinfo->name,
                        'region' => (array_key_exists($resourcerota->region_id, $Regions)) ? $Regions[$resourcerota->region_id]->name : 'N/A',
                        'city' => $city->name,
                        'location' => $location->name,
                        'from' => $resourcerota->start ? \Carbon\Carbon::parse($resourcerota->start)->format('D M, j Y') : null,
                        'to' => $resourcerota->end ? \Carbon\Carbon::parse($resourcerota->end)->format('D M, j Y') : null,
                        'created_at' => Carbon::parse($resourcerota->created_at)->format('F j,Y h:i A'),
                        'active' => $resourcerota->active,
                    ];
                }

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
                'edit' => Gate::allows('resourcerotas_edit'),
                'delete' => Gate::allows('resourcerotas_destroy'),
                'active' => Gate::allows('resourcerotas_active'),
                'inactive' => Gate::allows('resourcerotas_inactive'),
                'create' => Gate::allows('resourcerotas_create'),
                'calender' => Gate::allows('resourcerotas_calender'),
            ];

            return ApiHelper::apiDataTable($records);

        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    private function filtersData($records)
    {

        $filters = Filters::all(Auth::User()->id, 'resourcehasrota');

        $resourcetype = ResourceTypes::getResourceType();

        $location = Locations::getActiveSorted(ACL::getUserCentres());

        $city = Cities::getActiveSortedFeatured(ACL::getUserCities());

        $regions = Regions::getActiveSorted(ACL::getUserRegions());

        $records['filter_values'] = [
            'resource_type' => $resourcetype,
            'city' => $city,
            'regions' => $regions,
            'location' => $location,
            'status' => config('constants.status'),
        ];

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
        if (! Gate::allows('resourcerotas_active')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            if ($request->status == 1) {
                $response = ResourceHasRota::activeRecord($request->id);
            } else {
                $response = ResourceHasRota::inactiveRecord($request->id);
            }

            if ($response['status']) {
                return ApiHelper::apiResponse($this->success, $response['message']);
            }

            return ApiHelper::apiResponse($this->error, $response['message'], false);

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
        if (! Gate::allows('resourcerotas_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $resourceRota = ResourceHasRota::find($id);
        $citi = Cities::with('region')->find($resourceRota->city_id);
        $city = $resourceRota->city->name;
        $location = $resourceRota->location->name;
        $resource_name = Resources::where('id', '=', $resourceRota->resource_id)->first();
        if ($resourceRota->copy_all == '0') {
            $week = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            foreach ($week as $day) {
                if ($resourceRota[$day]) {

                    $tem = explode(',', $resourceRota[$day]);
                    $resourceRota['time_f_'.$day] = Carbon::parse($tem[0])->format('h:i A');
                    $resourceRota['time_to_'.$day] = Carbon::parse($tem[1])->format('h:i A');

                    if ($resourceRota[$day.'_off']) {
                        $break = explode(',', $resourceRota[$day.'_off']);
                        $resourceRota['break_from_'.$day] = Carbon::parse($break[0])->format('h:i A');
                        $resourceRota['break_to_'.$day] = Carbon::parse($break[1])->format('h:i A');
                    } else {
                        $resourceRota['break_from_'.$day] = null;
                        $resourceRota['break_to_'.$day] = null;
                    }

                } else {
                    $resourceRota[$day.'checked'] = '_off';
                }
            }
        }
        if ($resourceRota->copy_all == '1') {
            $week = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            foreach ($week as $day) {
                $tem = explode(',', $resourceRota->monday);
                $resourceRota['time_f_'.$day] = Carbon::parse($tem[0])->format('h:i A');
                $resourceRota['time_to_'.$day] = Carbon::parse($tem[1])->format('h:i A');
                if ($resourceRota[$day.'_off']) {
                    $break = explode(',', $resourceRota->monday_off);
                    $resourceRota['break_from_'.$day] = Carbon::parse($break[0])->format('h:i A');
                    $resourceRota['break_to_'.$day] = Carbon::parse($break[1])->format('h:i A');
                } else {
                    $resourceRota['break_from_'.$day] = null;
                    $resourceRota['break_to_'.$day] = null;
                }
            }
        }

        return ApiHelper::apiResponse($this->success, 'Data found', true, [
            'resourceRota' => $resourceRota,
            'resource_name' => $resource_name,
            'city' => $city,
            'citi' => $citi,
            'location' => $location,
        ]);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return mixed
     */
    public function update(Request $request, $id)
    {
        if (! Gate::allows('resourcerotas_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }

        if ($request->is_consultancy == '0' && $request->is_treatment == '0') {
            return ApiHelper::apiResponse($this->error, 'Please check at least one from consultancy and treatment');
        }

        $resourcerota = ResourceHasRota::find($id);

        if ($resourcerota->end <= $request->end) {

            $response = ResourceHasRota::updateRecord($id, $request, Auth::User()->account_id);

            return ApiHelper::apiResponse($this->success, $response['message'], $response['status']);

        }

        return ApiHelper::apiResponse($this->success, 'Your To date must be equal or greater than your previous To date '.$resourcerota->end, false);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        if (! Gate::allows('resourcerotas_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        try {

            $response = ResourceHasRota::deleteRecord($id);

            if ($response['status']) {
                return ApiHelper::apiResponse($this->success, $response['message']);
            }

            return ApiHelper::apiResponse($this->success, $response['message'], false);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get information for calender view ajax base
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getcalenderinfoevents($id)
    {
        if (! Gate::allows('resourcerotas_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $resourceRota = ResourceHasRota::find($id);

        $resource = Resources::where('id', '=', $resourceRota->resource_id)->first();

        $rotahasDays = ResourceHasRotaDays::where('resource_has_rota_id', '=', $resourceRota->id)->get();

        if ($rotahasDays) {
            $index = 0;
            foreach ($rotahasDays as $days) {

                $start_rotadays = Carbon::parse($days->start_time);

                $end_rotadays = Carbon::parse($days->end_time);

                $date = $days->date;

                $today_date = Carbon::now()->toDateString();
                if ($date < $today_date) {
                    $checked = 0;
                } else {
                    $checked = 1;
                }
                $dayname = strtolower(Carbon::parse($date)->format('l'));

                $difference_rotadays = $start_rotadays->diffInMinutes($end_rotadays);

                $resourcehasrotainfo = ResourceHasRota::where('id', '=', $days->resource_has_rota_id)->first();

                $tem = explode(',', $resourcehasrotainfo[$dayname]);

                if (count($tem) <= '1') {
                    $tem = null;
                }

                $records[$index] = [
                    'id' => $days->id,
                    'date' => $days->date,
                    'start_time' => $days->start_time,
                    'end_time' => $days->end_time,
                    'start_off' => $days->start_off,
                    'end_off' => $days->end_off,
                    'title' => view('admin.resourcerotas.calender_action', compact('days'))->render(),
                    'start' => $days->date,
                    'color' => view('admin.resourcerotas.calender_color', compact('difference_rotadays', 'tem'))->render(),
                    'checked' => $checked,
                ];
                $index++;
            }
        }

        //return ApiHelper::apiResponse($this->success, 'Record found.', true, $records);
        return Response()->json($records);
    }

    /**
     * Get information for calender view
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getcalenderinfo($id)
    {
        if (! Gate::allows('resourcerotas_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $resourcehasrota = ResourceHasRota::getData($id);

        $resource = Resources::where('id', '=', $resourcehasrota->resource_id)->first();

        if ($resourcehasrota == null) {

            return ApiHelper::apiResponse($this->success, 'Resource rota not found.', false);

        }

        return ApiHelper::apiResponse($this->success, 'Resource rota found.', true, [
            'id' => $id,
            'resource' => $resource,
        ]);
    }

    public function viewCalender()
    {

        return view('admin.resourcerotas.calender');
    }

    /**
     * update information of Resource days in resource has rotas days
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function store_calender_edit(Request $request)
    {

        if (! Gate::allows('resourcerotas_manage')) {

            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $rotahasdays = ResourceHasRotaDays::where('id', '=', $request->resource_days_id)->first();

        $resourceid = ResourceHasRota::where('id', '=', $rotahasdays->resource_has_rota_id)->first();

        if ($request->dayElement != 'on') {

            if ($request->start_time && $request->end_time) {
                if ($request->start_time == $request->end_time) {
                    return ApiHelper::apiResponse($this->success, 'Time range must be different, kindly define again.', false);
                }
            } else {
                return ApiHelper::apiResponse($this->success, 'From or To require, kindly define again.', false);
            }

            if ($request->start_off >= $request->end_off) {
                return ApiHelper::apiResponse($this->success, "To break time can't be greater than or equal to from break time.", false);
            }

            if ($request->start_off && $request->end_off) {
                if ($request->start_off == $request->end_off) {
                    return ApiHelper::apiResponse($this->success, 'Time range must be different, kindly define again.', false);
                } else {
                    if (
                        strtotime($request->start_off) >= strtotime($request->start_time) &&
                        strtotime($request->end_off) <= strtotime($request->end_time)
                    ) {

                        $start_off = $request->start_off;
                        $end_off = $request->end_off;

                    } else {
                        return ApiHelper::apiResponse($this->success, 'Break time must be between From and To, Kindly Define again.', false);
                    }
                }
            } else {
                if (! $request->start_off && ! $request->end_off) {
                    $start_off = null;
                    $end_off = null;
                }
                if ($request->start_off || $request->end_off) {
                    return ApiHelper::apiResponse($this->success, 'From or To require, kindly define again.', false);
                }
            }
        }
        if ($request->dayElement == null) {

            $start_timestamp = Carbon::parse($rotahasdays->date.' '.$request->start_time)->format('Y-m-d H:i').':00';
            $end_timestamp = Carbon::parse($rotahasdays->date.' '.$request->end_time)->format('Y-m-d H:i').':00';

            /*First I checked For doctor*/
            if ($resourceid->resource_type_id == 2) {
                $rota_appointments = Appointments::where('resource_has_rota_day_id', '=', $rotahasdays->id)->wheredate('scheduled_date', '=', $rotahasdays->date)->select('id', 'scheduled_date', 'scheduled_time')->get()->toArray();
            }
            /*Second I checked for machine*/
            if ($resourceid->resource_type_id == 1) {
                $rota_appointments = Appointments::where('resource_has_rota_day_id_for_machine', '=', $rotahasdays->id)->wheredate('scheduled_date', '=', $rotahasdays->date)->select('id', 'scheduled_date', 'scheduled_time')->get()->toArray();
            }
            $not_allow = false;
            if (count($rota_appointments)) {
                foreach ($rota_appointments as $rota_appointment) {
                    if ($rota_appointment['scheduled_time'] && $request->start_time && $request->end_time) {
                        if (! ResourceHasRota::checkTime(Carbon::parse($rota_appointment['scheduled_time'])->format('h:i A'), $request->start_time, $request->end_time)) {
                            $not_allow = true;

                            return ApiHelper::apiResponse($this->success, 'Provided rota timings are conflicts with appointments. Unable to update rota.', false);
                            break;
                        }
                        if (ResourceHasRota::checkTime(Carbon::parse($rota_appointment['scheduled_time'])->format('h:i A'), Input::get('start_off'), Input::get('end_off'))) {
                            $not_allow = true;

                            return ApiHelper::apiResponse($this->success, 'Provided rota break timings are conflicts with appointments. Unable to update rota.', false);
                            break;
                        }
                    }
                }
            }
            if ($not_allow) {
                return ApiHelper::apiResponse($this->success, 'Not Allowed', false);
            } else {
                $rotahasdays->update([
                    'start_time' => $request->start_time,
                    'end_time' => $request->end_time,
                    'start_timestamp' => $start_timestamp,
                    'end_timestamp' => $end_timestamp,
                    'start_off' => $start_off,
                    'end_off' => $end_off,
                ]);
            }
        } else {
            /*First I checked For doctor*/
            if ($resourceid->resource_type_id == 2) {
                $appointmentinformation = Appointments::where('resource_has_rota_day_id', '=', $rotahasdays->id)->wheredate('scheduled_date', '=', $rotahasdays->date)->get();
            }
            /*Second I checked for machine*/
            if ($resourceid->resource_type_id == 1) {
                $appointmentinformation = Appointments::where('resource_has_rota_day_id_for_machine', '=', $rotahasdays->id)->wheredate('scheduled_date', '=', $rotahasdays->date)->get();
            }
            /*Now I Checked we can define leave or not*/
            if (count($appointmentinformation) == 0) {
                $rotahasdays->update([
                    'start_time' => null,
                    'end_time' => null,
                    'start_off' => null,
                    'end_off' => null,
                ]);
            } else {
                return ApiHelper::apiResponse($this->success, 'Rota use in appointment, kindly define again.', false);
            }
        }

        return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
    }
}
