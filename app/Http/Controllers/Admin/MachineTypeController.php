<?php

declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\NodesTree;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUpdateMachineTypeRequest;
use App\Models\MachineType;
use App\Models\MachineTypeHasServices;
use App\Models\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class MachineTypeController extends Controller
{


    

    /**
     * Display a listing of the machine type.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\never
     */
    public function index(): \Illuminate\View\View
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
    public function datatable(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if (!Gate::allows('machineType_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $filename = 'machinetypes';

            $filters = getFilters($request->all());

            $apply_filter = checkFilters($filters, $filename);

            $records = [];
            $records['data'] = [];

            if (hasFilter($filters, 'delete')) {
                if (! Gate::allows('machineType_destroy')) {
                    return $this->errorResponse('You are not authorized to delete these records.', 403);
                }
                $ids = explode(',', $filters['delete']);
                $machinetypes = MachineType::getBulkData($ids);
                if ($machinetypes) {
                    foreach ($machinetypes as $machinetype) {
                        // Check if child records exists or not, If exist then disallow to delete it.
                        if (!MachineType::isChildExists($machinetype->id, Auth::user()->account_id)) {
                            $machinetype->delete();
                        }
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            // Get Total Records
            $iTotalRecords = MachineType::getTotalRecords($request, Auth::user()->account_id, $apply_filter);

            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $machinetypes = MachineType::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::user()->account_id, $apply_filter);

            $services = GeneralFunctions::ServicesTreeMachineType();

            $records['data'] = $machinetypes;
            $records['permissions'] = [
                'edit' => Gate::allows('machineType_edit'),
                'delete' => Gate::allows('machineType_destroy'),
                'active' => Gate::allows('machineType_active'),
                'inactive' => Gate::allows('machineType_inactive'),
            ];
            $filters = Filters::all(Auth::user()->id, 'machinetypes');
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

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'MachineTypeController');
        }
    }

    /**
     * Show the form for creating a new machine type.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(): \Illuminate\View\View
    {

        if (!Gate::allows('machineType_create')) {
            return abort(401);
        }
        /*Get Service as we get in resouce create module*/
        $allserviceslug = Services::where('slug', '=', 'all')->first();

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::user()->account_id, true, true);
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
    public function store(StoreUpdateMachineTypeRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            if (!Gate::allows('machineType_create')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }
            if ($machinetype = MachineType::createRecord($request, Auth::user()->account_id)) {
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

                return $this->successResponse('Record has been created successfully.');
            }

            return $this->errorResponse('Something went wrong, please try again later.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'MachineTypeController');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(int $id): void
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            if (!Gate::allows('machineType_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }
            $machine_type = MachineType::getData($id);
            if (!$machine_type) {
                return $this->errorResponse('No Record Found!', 404);
            }
            $service_machine_type = $machine_type->machinetype_has_services()->pluck('service_id')->toArray();
            $services = GeneralFunctions::ServicesTreeMachineType();


            return $this->successResponse('Success', ['machine_type' => $machine_type, 'service_machine_type' => $service_machine_type, 'services' => $services]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'MachineTypeController');
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(StoreUpdateMachineTypeRequest $request, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            if (!Gate::allows('machineType_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            if ($machinetype = MachineType::updateRecord($id, $request, Auth::user()->account_id)) {
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

                return $this->successResponse('Record has been updated successfully.');
            }

            return $this->errorResponse('Something went wrong, please try again later.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'MachineTypeController');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            if (!Gate::allows('machineType_destroy')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }
            $response = MachineType::deleteRecord($id);

            return $this->successResponse($response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return $this->handleException($e, 'MachineTypeController');
        }
    }

    /**
     * Change status of Lead Source
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if ($request->status == 0) {
                if (!Gate::allows('machineType_inactive')) {
                    return $this->errorResponse('You are not authorized to access this resource.', 401);
                }
                $response = MachineType::inactiveRecord((int) $request->id);
            } else {
                if (!Gate::allows('machineType_active')) {
                    return $this->errorResponse('You are not authorized to access this resource.', 401);
                }
                $response = MachineType::activeRecord((int) $request->id);
            }

            return $this->successResponse($response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return $this->handleException($e, 'MachineTypeController');
        }
    }
}
