<?php

declare(strict_types=1);

namespace App\Services\Resource;

use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\NodesTree;
use App\Helpers\Widgets\MachineTypeWidget;
use App\Models\Locations;
use App\Models\MachineType;
use App\Models\Resources;
use App\Models\ResourceTypes;
use App\Models\Services;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ResourceService
{
    /**
     * Get data needed for the create form.
     */
    public function getCreateData(): array
    {
        $resource_types = ResourceTypes::getallresource();

        $locations = Locations::where([
            ['active', '=', '1'],
            ['account_id', '=', Auth::user()->account_id],
            ['slug', '=', 'custom'],
        ])->whereIn('id', ACL::getUserCentres())->pluck('full_address', 'id');

        $machinetypes = MachineType::where([
            ['active', '=', '1'],
            ['account_id', '=', '1'],
        ])->pluck('name', 'id');

        return [
            'resource_types' => $resource_types,
            'locations' => $locations,
            'machine_types' => $machinetypes,
        ];
    }

    /**
     * Get machine types filtered by location services.
     */
    public function getMachineTypesByLocation(int $locationId): array
    {
        $locationservice_ids = MachineTypeWidget::loadlocationservice($locationId, Auth::user()->account_id, true);

        $machinetypes = MachineType::where([
            ['active', '=', '1'],
            ['account_id', '=', '1'],
        ])->get();

        $machinetype_ids = [];

        foreach ($machinetypes as $machinetype) {

            $machinetypeservice_ids = MachineTypeWidget::loadmachinetypeservice($machinetype->id, Auth::user()->account_id, true);

            $containsSearch = count(array_intersect($machinetypeservice_ids, $locationservice_ids)) == count($machinetypeservice_ids);

            if ($containsSearch) {
                $machinetype_ids[] = $machinetype->id;
            }
        }
        $machinetypes = MachineType::whereIn('id', $machinetype_ids)->get();

        return [
            'status' => !empty($machinetypes),
            'machinetypes' => $machinetypes,
        ];
    }

    /**
     * Get services by location id.
     */
    public function getServicesByLocation(int $locationId): array
    {
        $status_for_all = false;
        $allserviceslug = Services::where('slug', '=', 'all')->first();
        $location_id = $locationId;
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
            $parentGroups->build(0, Auth::user()->account_id, true, true);
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
                $parentGroups->build($service_data->id, Auth::user()->account_id, false, true);
                $parentGroups->toList($parentGroups, 0);
                $Services[] = $parentGroups->nodeList;
            }
        }

        return [
            'status' => !empty($Services),
            'Services' => $Services,
            'status_for_all' => $status_for_all,
        ];
    }

    /**
     * Store a new resource.
     */
    public function store(array $data, int $accountId): bool
    {
        $request = new \Illuminate\Http\Request($data);

        $resource = Resources::createRecord($request, $accountId);

        return (bool) $resource;
    }

    /**
     * Get datatable data including bulk delete, pagination, filters.
     */
    public function getDatatableData(array $requestData, int $accountId): array
    {
        $filename = 'resources';
        $filters = getFilters($requestData);
        $apply_filter = checkFilters($filters, $filename);

        $records = [];
        $records['data'] = [];

        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $resources = Resources::getBulkData($ids);
            if ($resources) {
                foreach ($resources as $resource) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! Resources::isChildExists($resource->id, $accountId)) {
                        $resource->delete();
                    }
                }
            }

            $records['status'] = true;
            $records['message'] = 'Records has been deleted successfully!';
        }
        // Get Total Records
        $request = new \Illuminate\Http\Request($requestData);
        $iTotalRecords = Resources::getTotalRecords($request, $accountId, $apply_filter);

        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $resources = Resources::getRecords($request, $iDisplayStart, $iDisplayLength, $accountId, $apply_filter);

        $records = $this->getFiltersData($records);

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

        return $records;
    }

    /**
     * Get filter values and active filters for datatable.
     */
    private function getFiltersData(array $records): array
    {
        //Here we get all resource except doctor
        $filters = Filters::all(Auth::user()->id, 'resources');

        $resource_types = ResourceTypes::getallresource();

        $locations = Locations::getActiveSorted(ACL::getUserCentres(), 'full_address');

        $machinetypes = MachineType::where([
            ['active', '=', '1'],
            ['account_id', '=', '1'],
        ])->pluck('name', 'id');

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
     * Change resource status (active/inactive).
     */
    public function changeStatus(int $id, int $status): bool
    {
        if ($status == 1) {
            $response = Resources::activeRecord($id);
        } else {
            $response = Resources::inactiveRecord($id);
        }

        return (bool) $response;
    }

    /**
     * Get data needed for the edit form.
     */
    public function getEditData(int $id): array
    {
        $resource = Resources::getData($id);

        $resource_types = ResourceTypes::getallresource();
        $resource_types->prepend('Select a Resource Type', '');

        $locations = Locations::where([
            //            ['active', '=', '1'],
            ['account_id', '=', Auth::user()->account_id],
            ['slug', '=', 'custom'],
        ])->whereIn('id', ACL::getUserCentres())->pluck('full_address', 'id');

        $locationservice_ids = MachineTypeWidget::loadlocationservice($resource->location_id, Auth::user()->account_id, true);

        $machinetypes = MachineType::where([
            ['active', '=', '1'],
            ['account_id', '=', '1'],
        ])->get();

        $machinetype_ids = [];

        foreach ($machinetypes as $machinetype) {

            $machinetypeservice_ids = MachineTypeWidget::loadmachinetypeservice($machinetype->id, Auth::user()->account_id, true);

            $containsSearch = count(array_intersect($machinetypeservice_ids, $locationservice_ids)) == count($machinetypeservice_ids);

            if ($containsSearch) {
                $machinetype_ids[] = $machinetype->id;
            }
        }

        $machinetypes = MachineType::whereIn('id', $machinetype_ids)->pluck('name', 'id');

        return [
            'resource' => $resource,
            'resource_types' => $resource_types,
            'machine_types' => $machinetypes,
            'locations' => $locations,
        ];
    }

    /**
     * Update a resource record.
     */
    public function update(int $id, array $data, int $accountId): bool
    {
        $request = new \Illuminate\Http\Request($data);

        $resource = Resources::updateRecord($id, $request, $accountId);

        return (bool) $resource;
    }

    /**
     * Delete a resource record.
     */
    public function destroy(int $id): array
    {
        return Resources::deleteRecord($id);
    }
}
