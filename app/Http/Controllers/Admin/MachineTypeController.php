<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\NodesTree;
use App\Http\Controllers\Controller;
use App\Models\MachineType;
use App\Models\MachineTypeHasServices;
use App\Models\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class MachineTypeController extends Controller
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
     * Display a listing of the machine type.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\never
     */
    public function index()
    {
        if (!Gate::allows('machineType_manage')) {
            return abort(401);
        }

        return view('admin.machine_types.index');
    }

    /**
     * Display the machinetype in datatable.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {
            if (!Gate::allows('machineType_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $filename = 'machinetypes';

            $filters = getFilters($request->all());

            $apply_filter = checkFilters($filters, $filename);

            $records = [];
            $records['data'] = [];

            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $machinetypes = MachineType::getBulkData($ids);
                if ($machinetypes) {
                    foreach ($machinetypes as $machinetype) {
                        // Check if child records exists or not, If exist then disallow to delete it.
                        if (!MachineType::isChildExists($machinetype->id, Auth::User()->account_id)) {
                            $machinetype->delete();
                        }
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            // Get Total Records
            $iTotalRecords = MachineType::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $machinetypes = MachineType::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

            $services = GeneralFunctions::ServicesTreeMachineType();

            $records['data'] = $machinetypes;
            $records['permissions'] = [
                'edit' => Gate::allows('machineType_edit'),
                'delete' => Gate::allows('machineType_destroy'),
                'active' => Gate::allows('machineType_active'),
                'inactive' => Gate::allows('machineType_inactive'),
            ];
            $filters = Filters::all(Auth::User()->id, 'machinetypes');
            $records['active_filters'] = $filters;
            $records['filter_values'] = [
                'services' => $services,
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
     * Show the form for creating a new machine type.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

        if (!Gate::allows('machineType_create')) {
            return abort('401');
        }
        /*Get Service as we get in resouce create module*/
        $allserviceslug = Services::where('slug', '=', 'all')->first();

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id, true, true);
        $parentGroups->toList($parentGroups, -1);
        $Services = $parentGroups->nodeList;
        foreach ($Services as $key => $ser) {
            if ($key) {
                if (isset($allserviceslug->name)) {
                    if ($ser['name'] == $allserviceslug->name) {
                        unset($Services[$key]);
                    }
                }
            }
        }
        /*end*/
        $ServiceMachinetype = [];

        return view('admin.machinetypes.create', compact('Services', 'ServiceMachinetype'));
    }

    /**
     * Store a newly created machine type in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            if (!Gate::allows('machineType_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            if ($machinetype = MachineType::createRecord($request, Auth::User()->account_id)) {
                $data = $request->all();
                if (isset($data['services']) && count($data['services'])) {
                    $services = $data['services'];
                    $servicesData = [];

                    $childServices = Services::whereIn('parent_id', $services)
                        ->pluck('id')
                        ->toArray();

                    $filteredServices = array_diff($services, $childServices);
                    foreach ($filteredServices as $service) {
                        $servicesData[] = [
                            'machine_type_id' => $machinetype->id,
                            'service_id' => $service,
                        ];
                    }
                    MachineTypeHasServices::createRecord($servicesData, $machinetype);
                }

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
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
            'services' => 'required',
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        try {
            if (!Gate::allows('machineType_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $machine_type = MachineType::getData($id);
            if (!$machine_type) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }
            $service_machine_type = $machine_type->machinetype_has_services()->pluck('service_id')->toArray();
            $services = GeneralFunctions::ServicesTreeMachineType();


            return ApiHelper::apiResponse($this->success, 'Success', true, ['machine_type' => $machine_type, 'service_machine_type' => $service_machine_type, 'services' => $services]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            if (!Gate::allows('machineType_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }

            if ($machinetype = MachineType::updateRecord($id, $request, Auth::User()->account_id)) {
                $machinetype->machinetype_has_services()->delete();
                $data = $request->all();
                if (isset($data['services']) && count($data['services'])) {
                    $services = $data['services'];
                    $servicesData = [];

                    $childServices = Services::whereIn('parent_id', $services)
                        ->pluck('id')
                        ->toArray();

                    $filteredServices = array_diff($services, $childServices);

                    foreach ($filteredServices as $service) {
                        $servicesData[] = [
                            'machine_type_id' => $machinetype->id,
                            'service_id' => $service,
                        ];
                    }
                    MachineTypeHasServices::updateRecord($servicesData, $machinetype);
                }

                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            if (!Gate::allows('machineType_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $response = MachineType::deleteRecord($id);

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Change status of Lead Source
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        try {
            if ($request->status == 0) {
                if (!Gate::allows('machineType_inactive')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
                }
                $response = MachineType::inactiveRecord($request->id);
            } else {
                if (!Gate::allows('machineType_active')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
                }
                $response = MachineType::activeRecord($request->id);
            }

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
