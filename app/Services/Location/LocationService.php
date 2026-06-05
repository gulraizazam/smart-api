<?php

declare(strict_types=1);

namespace App\Services\Location;

use Carbon\Carbon;
use App\Helpers\ACL;
use App\Models\Cities;
use App\Models\Regions;
use App\Helpers\Filters;
use App\Models\Services;
use App\Models\Locations;
use App\Helpers\NodesTree;
use App\Models\UserHasLocations;
use App\Models\User;
use App\Helpers\GeneralFunctions;
use App\Models\ServiceHasLocations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Helpers\Widgets\ServiceWidget;
use App\Helpers\Widgets\LocationsWidget;

class LocationService
{
    /**
     * Process bulk delete from datatable filters.
     */
    public function processBulkDelete(array $filters, int $accountId): array
    {
        $records = [];
        $records['data'] = [];

        if (!empty($filters) && hasFilter($filters, 'delete') != '') {
            $ids = explode(',', $filters['delete']);
            $Locations = Locations::getBulkData($ids);
            if ($Locations) {

                foreach ($Locations as $Location) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! Locations::isChildExists($Location->id, $accountId)) {
                        $Location->delete();
                    }
                }
            }
            $records['status'] = true;
            $records['message'] = 'Records has been deleted successfully!';
        }

        return $records;
    }

    /**
     * Build datatable rows from location records.
     */
    public function buildDatatableRows(
        object $locations,
        int $accountId,
        string $orderBy,
        string $order,
        int $page,
        int $pages,
        int $iDisplayLength,
        int $iTotalRecords,
        array $records
    ): array {
        $Services = Services::getAllRecordsDictionary($accountId);
        $Cities = Cities::getAllRecordsDictionary($accountId);
        $Regions = Regions::getAllRecordsDictionary($accountId);

        $records = $this->getExtraData($records, $accountId);

        if ($locations->count()) {
            foreach ($locations as $location) {

                /*
                 * Record Level Services process start
                 */
                $_services = '';

                $locationServices = ServiceHasLocations::where(['location_id' => $location->id])->pluck('service_id');
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

        }

        return $records;
    }

    private function getExtraData(array $records, int $accountId): array
    {

        $filters = Filters::all(Auth::user()->id, 'locations');

        $cities = Cities::where([
            ['account_id', '=', $accountId],
            ['slug', '=', 'custom'],
            ['active', '=', '1'],
            ['is_featured', '=', '1'],
        ])->pluck('name', 'id');

        $regions = Regions::getActiveSorted(ACL::getUserRegions());

        /* Create Nodes with Parents */
        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, $accountId);
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
     * Proxy for Locations::getTotalRecords.
     */
    public function getTotalRecords(object $request, int $accountId, mixed $applyFilter): mixed
    {
        return Locations::getTotalRecords($request, $accountId, $applyFilter);
    }

    /**
     * Proxy for Locations::getRecords.
     */
    public function getRecords(object $request, int $iDisplayStart, int $iDisplayLength, int $accountId, mixed $applyFilter): mixed
    {
        return Locations::getRecords($request, $iDisplayStart, $iDisplayLength, $accountId, $applyFilter);
    }

    /**
     * Create a new location record.
     */
    public function createLocation(object $request, int $accountId): mixed
    {
        return Locations::createRecord($request, $accountId);
    }

    /**
     * Update a location record.
     */
    public function updateLocation(int $id, object $request, int $accountId): mixed
    {
        return Locations::updateRecord($id, $request, $accountId);
    }

    /**
     * Get data needed for the create form.
     */
    public function getCreateData(int $accountId): array
    {
        /*Get Service as we get in resource create module*/
        $Services = GeneralFunctions::ServicesTreeList();

        $cities = Cities::with('region')->where([
            ['account_id', '=', $accountId],
            ['slug', '=', 'custom'],
            ['active', '=', '1'],
            ['is_featured', '=', '1'],
        ])->get()->pluck('full_name', 'id');
        $ServiceLocations = [];

        return [
            'services' => $Services,
            'service_location' => $ServiceLocations,
            'cities' => $cities,
        ];
    }

    /**
     * Handle post-creation logic: assign location to users and services.
     */
    public function handlePostCreation(object $location, array $data, int $accountId): void
    {
        $locatUser = [];
        $location_slug_all = Locations::where('slug', '=', 'all')->first();
        $user_has_location_data = UserHasLocations::where('location_id', '=', $location_slug_all->id ?? 0)->select('user_id')->groupBy('user_id')->get();
        if (!empty($user_has_location_data)) {
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
            if (! in_array($Need_to_lcoateuser->user_id, $user_already_have_location, true)) {
                $locatUser[] = $Need_to_lcoateuser->user_id;
            }
        }
        if (!empty($locatUser)) {
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
        /*
         * New Audit Trail Process
         */
        if (isset($data['services']) && count($data['services'])) {
            $services = LocationsWidget::generateservicearray($data['services'], $accountId);
            $servicesData = [];
            foreach ($services as $service) {
                $servicesData = [
                    'service_id' => $service,
                    'location_id' => $location->id,
                    'account_id' => $accountId,
                ];
                ServiceHasLocations::createRecord($servicesData, $location);
            }
        }
    }

    /**
     * Get data needed for the edit form.
     */
    public function getEditData(int $id, int $accountId): array
    {
        $location = Locations::getData($id);
        if (! $location) {
            return ['found' => false];
        }
        $ServiceLocations = $location->service_has_locations()->pluck('service_id')->toArray();
        $cities = Cities::with('region')->where([
            ['account_id', '=', $accountId],
            ['slug', '=', 'custom'],
            ['active', '=', '1'],
            ['is_featured', '=', '1'],
        ])->get()->pluck('full_name', 'id');
        $cities->prepend('Select a City', '');
        $Services = GeneralFunctions::ServicesTreeList();

        return [
            'found' => true,
            'location' => $location,
            'services' => $Services,
            'service_location' => $ServiceLocations,
            'cities' => $cities,
        ];
    }

    /**
     * Handle post-update logic: reassign services.
     */
    public function handlePostUpdate(object $location, array $data, int $accountId): void
    {
        $location->service_has_locations()->delete();

        /*
         * Prepare services data for location
         */

        if (isset($data['services']) && count($data['services'])) {
            $servicesData = [];
            $services = LocationsWidget::generateservicearray($data['services'], $accountId);
            foreach ($services as $service) {
                $servicesData = [
                    'service_id' => $service,
                    'location_id' => $location->id,
                    'account_id' => $accountId,
                ];
                ServiceHasLocations::updateRecord($servicesData, $location);
            }
        }
    }

    /**
     * Delete a location.
     */
    public function deleteLocation(int $id): array
    {
        return Locations::deleteRecord($id);
    }

    /**
     * Change location active status.
     */
    public function changeStatus(int $id, string $status): bool
    {
        return (bool) Locations::activeRecord($id, $status);
    }

    /**
     * Get sortable locations.
     */
    public function getSortableLocations(int $accountId): object
    {
        return Locations::whereNull('deleted_at')->whereSlug('custom')->where(['account_id' => $accountId])->orderby('sort_no', 'ASC')->get();
    }

    /**
     * Save sort order for locations.
     */
    public function saveSortOrder(array $itemIDs): bool
    {
        if (count($itemIDs)) {
            foreach ($itemIDs as $key => $itemID) {
                Locations::where('id', '=', $itemID)->where('account_id', auth()->user()->account_id)->update(['sort_no' => $key]);
            }

            return true;
        }

        return false;
    }

    /**
     * Get services for a location.
     */
    public function getServicesForLocation(int $locationId, int $accountId): array
    {
        $serive = ServiceWidget::generateServiceArrayArray((object) ['id' => $locationId], $accountId);

        return ['services' => $serive, 'locaiton_id_1' => $locationId];
    }

    public static function getFDM(?array $location_ids = null): array
    {
        $fdo_ids = [];
        $fdm_ids = [];
        if ($location_ids && !empty($location_ids)) {
            $fdo_phones = Locations::whereIn('id', $location_ids)->pluck('fdo_phone');
            if ($fdo_phones->count()) {
                foreach ($fdo_phones as $fdo_phone) {
                    $fdo_ids[] = User::where('phone', GeneralFunctions::cleanNumber($fdo_phone ?? 0))
                        ->where('user_type_id', 2)->value('id');
                }
            }
            $fdm_ids = !empty($fdo_ids) ? array_filter($fdo_ids) : [0];
        }

        if (!empty($fdm_ids)) {
            return $fdm_ids;
        }

        return DB::table('role_has_users')
            ->whereIn('role_id', ['4'])
            ->pluck('user_id')->toArray();
    }

    public static function getCSR(): array
    {
        return DB::table('role_has_users')
            ->whereIn('role_id', ['2', '3'])
            ->pluck('user_id')->toArray();
    }

    public static function getLocationIds(mixed $location_id): ?array
    {
        if ($location_id) {
            $location_ids = null;
            if (is_string($location_id)) {
                $location_id = explode(',', $location_id);
            }
            $locationIds = array_filter($location_id);
            if (isset($locationIds) && count($locationIds)) {
                $location_ids = $locationIds;
            }

            return $location_ids;
        }

        return null;
    }
}
