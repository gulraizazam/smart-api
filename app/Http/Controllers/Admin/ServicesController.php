<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\GroupsTree;
use App\Helpers\NodesTree;
use App\Http\Controllers\Controller;
use App\Models\Appointments;
use App\Models\Services;
use App\Models\TaxTreatmentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Validator;

class ServicesController extends Controller
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
     * Display a listing of Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (! Gate::allows('services_manage')) {
            return abort(401);
        }

        return view('admin.services.index');
    }

    public function datatable(Request $request)
    {
        try {
            $filters = getFilters($request->all());
            $records = [];
            $records['data'] = [];
            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $Locations = Services::getBulkData($ids);
                if ($Locations) {
                    foreach ($Locations as $Location) {
                        // Check if child records exists or not, If exist then disallow to delete it.
                        if (! Services::isChildExists($Location->id, Auth::User()->account_id)) {
                            $Location->delete();
                        }
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }
            [$orderBy, $order] = getSortBy($request);
            // Get Total Records
            $iTotalRecords = Services::getTotalRecords($request, Auth::User()->account_id);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);
            $Services = GeneralFunctions::servicesList($request, $iTotalRecords);
            $records = $this->getExtraData($records);
            if (! empty($Services)) {
                $records['data'] = $Services;
                $records['permissions'] = [
                    'edit' => Gate::allows('services_edit'),
                    'delete' => Gate::allows('services_destroy'),
                    'active' => Gate::allows('services_active'),
                    'inactive' => Gate::allows('services_inactive'),
                    'create' => Gate::allows('services_create'),
                    'sort' => Gate::allows('services_sort'),
                ];
                $records['meta'] = [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => 100,
                    'total' => $iTotalRecords,
                    'sort' => $order,
                ];

            } //end

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    private function getExtraData($records = [])
    {

        $filters = Filters::all(Auth::User()->id, 'services');

        /* Create Nodes with Parents */
        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $records['filter_values'] = [
            'services' => $Services,
            'status' => config('constants.status'),
        ];

        $records['active_filters'] = $filters;

        return $records;
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        if (! Gate::allows('services_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $service = new \stdClass();
        $service->duration = null;
        $service->parent_id = null;

        $tax_treatment_types = TaxTreatmentType::get();

        $select_tax_treatment_type = 1;

        $Services = GeneralFunctions::parentServices();

        $durations = GeneralFunctions::duration();

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'parent_services' => $Services,
            'service' => $service,
            'durations' => $durations,
            'tax_treatment_types' => $tax_treatment_types,
            'select_tax_treatment_type' => $select_tax_treatment_type,
        ]);
    }

    /**
     * Store a newly created Permission in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {

        if (! Gate::allows('services_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {

            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }

        if (Services::createRecord($request, Auth::User()->account_id)) {

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
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
            'parent_id' => 'required',
        ]);
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        if (! Gate::allows('services_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $service = Services::findOrFail($id);

        /*$BaseServices = Services::getGroupsActiveOnly();

        if ($BaseServices) {
            $Services = GroupsTree::buildOptions(GroupsTree::buildTree($BaseServices->toArray(), 0, $service->id), $service->parent_id);
        } else {
            $Services = array();
        }*/

        $tax_treatment_types = TaxTreatmentType::get();

        if ($service->tax_treatment_type_id == 0) {
            $select_tax_treatment_type = 1;
        } else {
            $select_tax_treatment_type = $service->tax_treatment_type_id;
        }

        $Services = GeneralFunctions::parentServices();

        $durations = GeneralFunctions::duration();

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'parent_services' => $Services,
            'service' => $service,
            'durations' => $durations,
            'tax_treatment_types' => $tax_treatment_types,
            'select_tax_treatment_type' => $select_tax_treatment_type,
        ]);
    }

    /**
     * Update Permission in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        if (! Gate::allows('services_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $validator = $this->verifyFields($request);
        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        $service = Services::findOrFail($id);
        if ($service->parent_id > 0 && $request->parent_id == 0) {
            $check_appointment = Appointments::whereServiceId($id)->count();
            if ($check_appointment > 0) {
                return ApiHelper::apiResponse($this->error, 'Service can not be updated due to one or more treatments are associated with it.', false);
            }
        }
        if (
            Services::isChildExists($id, Auth::User()->account_id) &&
            ($service->parent_id != $request->get('parent_id') || $service->end_node != (int) $request->get('end_node'))
        ) {
            return ApiHelper::apiResponse($this->success, 'Parent Service can not be changed due to one or more services are associated with it.', false);
        }

        if (Services::updateRecord($id, $request, Auth::User()->account_id)) {

            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
        } else {
            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.');
        }
    }

    /**
     * Remove Permission from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! Gate::allows('services_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $result = Services::deleteRecord($id);

        if ($result['status']) {
            return ApiHelper::apiResponse($this->success, $result['message']);
        }

        return ApiHelper::apiResponse($this->success, $result['message'], false);
    }

    /**
     * Inactive Record from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        try {

            if (! Gate::allows('services_active') && ! Gate::allows('services_inactive')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            if ($request->status == 1) {
                $response = Services::activeRecord($request->id);
            } else {
                $response = Services::inactiveRecord($request->id);
            }

            if ($response) {
                return ApiHelper::apiResponse($this->success, 'Status has been changed successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }

    }

    public function GetColor(Request $request)
    {
        if ($request->service != 0) {
            $service = Services::where('id', $request->service)->first();

            return response()->json(['color' => $service->color]);
        } else {
            return response()->json(['color' => '#000']);
        }
    }
}
