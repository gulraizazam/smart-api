<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Helpers\NodesTree;
use App\Models\MachineType;
use App\Models\MachineTypeHasServices;
use App\Models\Services;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Gate::allows('machineType_manage')) {
            return abort(401);
        }
        return view('admin.machinetypes.index');
    }

    /**
     * Display the machinetype in datatable.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request)
    {
        try {
            if (!Gate::allows('machineType_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $apply_filter = false;
            $filters = getFilters($request->all());
            if ($request->get('action')) {
                $action = $request->get('action');
                if (isset($action[0]) && $action[0] == 'filter_cancel') {
                    Filters::flush(Auth::User()->id, 'machinetypes');
                } else if ($action == 'filter') {
                    $apply_filter = true;
                }
            }
            $records = array();
            $records["data"] = array();

            if (count($filters) > 0 && hasFilter($filters, 'delete')) {
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

            list($orderBy, $order) = getSortBy($request);
            list($iDisplayLength, $iDisplayStart, $pages, $page) = getPaginationElement($request, $iTotalRecords);

            $machinetypes = MachineType::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

            $services = Services::getAllRecordsDictionary(Auth::User()->account_id);

            $records["data"] = $machinetypes;
            $records["permissions"] = [
                'edit' => Gate::allows('machineType_edit'),
                'delete' => Gate::allows('machineType_destroy'),
                'active' => Gate::allows('machineType_active'),
                'inactive' => Gate::allows('machineType_inactive'),
            ];
            $records['active_filters'] = $filters;
            $records['filter_values'] = [
                'services' => $services,
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

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiResponse($this->success, $e->getMessage(), false);
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
        $ServiceMachinetype = array();

        return view('admin.machinetypes.create', compact('Services', 'ServiceMachinetype'));
    }

    /**
     * Store a newly created machine type in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!Gate::allows('machineType_create')) {
            return abort('401');
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->messages()->all(),
            ));
        }
        if ($machinetype = MachineType::createRecord($request, Auth::User()->account_id)) {

            $data = $request->all();
            if (isset($data['services']) && count($data['services'])) {
                $servicesData = array();
                foreach ($data['services'] as $service) {
                    $servicesData = array(
                        'machine_type_id' => $machinetype->id,
                        'service_id' => $service,
                    );
                    MachineTypeHasServices::createRecord($servicesData, $machinetype);
                }
            }
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
     * @param Request $request
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
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!Gate::allows('machineType_edit')) {
            return abort('401');
        }

        $machinetype = MachineType::getData($id);
        $ServiceMachinetype = $machinetype->machinetype_has_services()->pluck('service_id')->toArray();
        $allserviceslug = Services::where('slug', '=', 'all')->first();

        $Services = [];
        $result = array();

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
        if (!$machinetype) {
            return view('error', compact('machinetype'));
        }
        return view('admin.machinetypes.edit', compact('machinetype', 'ServiceMachinetype', 'Services'));
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
        if (!Gate::allows('machineType_edit')) {
            return abort('401');
        }
        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->messages()->all(),
            ));
        }

        if ($machinetype = MachineType::updateRecord($id, $request, Auth::User()->account_id)) {

            $machinetype->machinetype_has_services()->delete();

            $data = $request->all();

            if (isset($data['services']) && count($data['services'])) {
                $servicesData = array();
                foreach ($data['services'] as $service) {
                    $servicesData = array(
                        'machine_type_id' => $machinetype->id,
                        'service_id' => $service,
                    );
                    MachineTypeHasServices::updateRecord($servicesData, $machinetype);
                }
            }
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

    /**
     * Inactive Record from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function inactive($id)
    {
        if (!Gate::allows('machineType_inactive')) {
            return abort(401);
        }
        MachineType::inactiveRecord($id);
        return redirect()->route('admin.machinetypes.index');
    }

    /**
     * Inactive Record from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function active($id)
    {
        if (!Gate::allows('machineType_active')) {
            return abort(401);
        }
        MachineType::activeRecord($id);
        return redirect()->route('admin.machinetypes.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!Gate::allows('machineType_destroy')) {
            return abort(401);
        }
        MachineType::deleteRecord($id);

        return redirect()->route('admin.machinetypes.index');
    }
}
