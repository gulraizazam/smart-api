<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\ACL;
use App\Models\Cities;
use App\Helpers\Filters;
use App\Models\Regions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CitiesController extends Controller
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
     * Display a listing of Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Gate::allows('cities_manage')) {
            return abort(401);
        }
        $filters = Filters::all(Auth::User()->id, 'cities');

        $regions = Regions::getActiveSorted(ACL::getUserRegions());
        $regions->prepend('Select a Region', '');

        return view('admin.cities.index', compact('regions', 'filters'));
    }


    /**
     * Display a listing of cities
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {
            if (!Gate::allows('cities_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $apply_filter = false;
            $filters = getFilters($request->all());
            if (hasFilter($filters, 'filter')) {
                if (isset($filters['filter']) && $filters['filter'] == 'filter_cancel') {
                    Filters::flush(Auth::User()->id, 'cities');
                } else if ($filters['filter'] == 'filter') {
                    $apply_filter = true;
                }
            }

            $records = array();
            $records["data"] = array();

            list($orderBy, $order) = getSortBy($request);
            if (count($filters) > 0 && hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $Cities = Cities::getBulkData($ids);
                if ($Cities) {
                    foreach ($Cities as $city) {
                        // Check if child records exists or not, If exist then disallow to delete it.
                        if (!Cities::isChildExists($city->id, Auth::User()->account_id)) {
                            $city->delete();
                        }
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            // Get Total Records
            $iTotalRecords = Cities::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

            list($iDisplayLength, $iDisplayStart, $pages, $page) = getPaginationElement($request, $iTotalRecords);

            $Cities = Cities::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

            $Regions = Regions::getAllRecordsDictionary(Auth::User()->account_id);

            if ($Cities) {
                foreach ($Cities as $citie) {
                    $citie->is_featured = $citie->is_featured == 1 ? 'Yes' : 'No';
                    $citie->region_id = (array_key_exists($citie->region_id, $Regions)) ? $Regions[$citie->region_id]->name : 'N/A';
                }
            }
            $records['data'] = $Cities;
            $records["permissions"] = [
                'edit' => Gate::allows('cities_edit'),
                'delete' => Gate::allows('cities_destroy'),
                'active' => Gate::allows('cities_active'),
                'inactive' => Gate::allows('cities_inactive'),
            ];
            $all_regions = array();
            foreach ($Regions as $region) {
                $all_regions[$region->id] = $region->name;
            }

            $filters = Filters::all(Auth::User()->id, 'cities');
            $records['active_filters'] = $filters;
            $records['filter_values'] = [
                'regions' => $all_regions,
                'is_featured' => [1 => 'Yes', 0 => 'No'],
                'status' => config('constants.status')
            ];
            $records["meta"] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];

            return response()->json($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!Gate::allows('cities_create')) {
            return abort(401);
        }

        $regions = Regions::getActiveSorted(ACL::getUserRegions());
        $regions->prepend('Select a Region', '');

        return view('admin.cities.create', compact('regions'));
    }


    public function sortOrderSave(Request $request)
    {
        try {
            if (!Gate::allows('cities_sort')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $itemIDs = $request->item_ids;
            if (count($itemIDs)) {
                foreach ($itemIDs as $key => $itemID) {
                    Cities::where('id', '=', $itemID)->update(array('sort_number' => $key));
                }
                return ApiHelper::apiResponse($this->success, 'Records are sorted Successfully!');
            }
            return ApiHelper::apiResponse($this->success, 'Something went Wrong! Records are not sorted', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function sortOrder()
    {
        if (!Gate::allows('cities_sort')) {
            return abort(401);
        }
        return view('admin.cities.sort');
    }

    /**
     * get records for sorting Cities
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sortOrderGet()
    {
        try {
            if (!Gate::allows('cities_sort')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $cities = Cities::where(['account_id' => Auth::User()->account_id])->orderby('sort_number', 'ASC')->get();
            return ApiHelper::apiResponse($this->success, 'Success', true, $cities);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }


    /**
     * Store a newly created City.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            if (!Gate::allows('cities_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            if (Cities::createRecord($request, Auth::User()->account_id)) {
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
     * @param Request $request
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);
    }


    /**
     * Get Data for editing city
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        try {
            if (!Gate::allows('cities_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $city = Cities::getData($id);
            if (!$city) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }
            $regions = Regions::getActiveSorted(ACL::getUserRegions());
            $regions->prepend('Select a Region', '');
            return ApiHelper::apiResponse($this->success, 'Success', true, $city);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }


    /**
     * Update City
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            if (!Gate::allows('cities_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            if (Cities::updateRecord($id, $request, Auth::User()->account_id)) {
                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }
            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }


    /**
     * Remove City.
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            if (!Gate::allows('cities_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $response = Cities::DeleteRecord($id);
            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Change status of City
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        try {
            if ($request->status == 0) {
                if (!Gate::allows('cities_inactive')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
                }
                $response = Cities::inactiveRecord($request->id);
            } else {
                if (!Gate::allows('cities_active')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
                }
                $response = Cities::activeRecord($request->id);
            }
            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
