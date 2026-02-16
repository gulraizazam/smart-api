<?php
/**
 * Created by PhpStorm.
 * User: REDSignal
 * Date: 3/22/2018
 * Time: 3:49 PM
 */

namespace App\Helpers\Widgets;

use App\Models\DoctorHasLocations;
use App\Models\Doctors;
use App\Models\Locations;
use App\Models\MachineType;
use App\Models\MachineTypeHasServices;
use App\Models\Regions;
use App\Models\Resources;
use App\Models\ServiceHasLocations;
use App\Models\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LocationsWidget
{
    /*
     * create Locations Dropdown with Heiracrchy
     * @param: (int) $account_id
     *
     * @return: (mixed) $result
     */
    public static function generateDropDownArray($account_id)
    {
        $regions = Regions::where([
            'account_id' => $account_id,
            'active' => 1,
        ])->whereIn('slug', ['custom', 'all'])->orderBy('sort_number', 'asc')->select('id', 'name', 'slug')->get();

        $dropdown_array = [];

        foreach ($regions as $region) {
            $dropdown_array[$region->id] = [
                'id' => $region->id,
                'name' => $region->name,
                'optgroup' => $region->name,
                'children' => [],
            ];

            if ($region->slug == 'all') {
                $first_child = Locations::where([
                    'account_id' => $account_id,
                    'region_id' => $region->id,
                    'slug' => 'all',
                ])->select('id', 'name', 'slug')->first();

                if ($first_child) {
                    $dropdown_array[$region->id]['children'][$first_child->id] = [
                        'id' => $first_child->id,
                        'name' => $first_child->name,
                        'slug' => $first_child->slug,
                    ];
                }
            } else {
                $first_child = Locations::where([
                    'account_id' => $account_id,
                    'region_id' => $region->id,
                    'slug' => 'region',
                    'active' => 1,
                ])->select('id', 'name', 'slug')->first();

                if ($first_child) {
                    $dropdown_array[$region->id]['children'][$first_child->id] = [
                        'id' => $first_child->id,
                        'name' => $first_child->name,
                        'slug' => $first_child->slug,
                    ];
                }
            }

            $other_childrens = Locations::where([
                'account_id' => $account_id,
                'region_id' => $region->id,
                'slug' => 'custom',
            ])->orderBy('name', 'asc')->get();

            if ($other_childrens) {
                foreach ($other_childrens as $other_children) {
                    $dropdown_array[$region->id]['children'][$other_children->id] = [
                        'id' => $other_children->id,
                        'name' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'.$other_children->full_address,
                        'slug' => $other_children->slug,
                    ];
                }
            }
        }

        return $dropdown_array;
    }

    /*
     * create Locations Array to store in tables
     * @param: (array) $centers (int) $account_id (int) $user_id
     *
     * @return: (mixed) $result
     */
    public static function generatelocationArray($centers, $account_id, $user_id)
    {

        if (is_array($centers) && count($centers)) {
            $first_child = Locations::where([
                'account_id' => $account_id,
                'slug' => 'all',
            ])->select('id', 'name')->first();

            if ($first_child && in_array($first_child->id, $centers)) {

                $all_location = Locations::where([
                    ['account_id', '=', $account_id],
                    ['active', '=', '1'],
                ])->get();

                foreach ($all_location as $location_all) {
                    $location_array[] = [
                        'user_id' => $user_id,
                        'region_id' => $location_all->region_id,
                        'location_id' => $location_all->id,
                    ];
                }

                return $location_array;
            } else {
                // Check Regions in centres array any one found then add their respective centres as well.
                $regions_mapping = self::generateDropDownArray($account_id);
                $region_centres = Locations::where([
                    'account_id' => $account_id,
                    'slug' => 'region',
                ])->select('id', 'name', 'region_id')->get();

                if ($region_centres) {
                    foreach ($region_centres as $region_centre) {
                        if (in_array($region_centre->id, $centers) && count($regions_mapping[$region_centre->region_id]['children'])) {
                            foreach ($regions_mapping[$region_centre->region_id]['children'] as $child) {
                                $centers[] = $child['id'];
                            }
                        }
                    }
                }

                $centers = array_unique($centers);

                foreach ($centers as $center) {

                    $location = Locations::find($center);

                    $location_array[] = [
                        'user_id' => $user_id,
                        'region_id' => $location->region_id,
                        'location_id' => $location->id,
                    ];
                }

                return $location_array;
            }

        }
    }

    /*
    * create Locations Array to edit
    * @param: (array) $centers (int) $account_id (int) $user_id
    *
    * @return: (mixed) $result
    */
    public static function generatelocationArrayEdit($centers, $account_id, $user)
    {
        $array1 = [];
        $location_array_1 = [];
        $collection = $centers;

        if (count($collection) > 0) {

            $first_child = Locations::where([
                'account_id' => $account_id,
                'slug' => 'all',
            ])->select('id', 'name')->first();

            if ($first_child && $collection->search($first_child->id)) {
                $location_array = $user->user_has_locations->where('location_id', '=', $first_child->id)->pluck('location_id')->toArray();

                return $location_array;

            } else {

                // Check Regions in centres array any one found then add their respective centres as well.
                $regions_mapping = self::generateDropDownArray($account_id);

                $region_centres = Locations::where([
                    'account_id' => $account_id,
                    'slug' => 'region',
                    'active' => 1,
                ])->select('id', 'name', 'region_id')->get();
                if ($region_centres) {

                    foreach ($region_centres as $region_centre) {

                        if ($collection->search($region_centre->id) && isset($regions_mapping[$region_centre->region_id]['children'])) {
                            foreach ($regions_mapping[$region_centre->region_id]['children'] as $child) {
                                $region_id = Locations::find($child['id']);
                                $location_array_1[] = $region_id->id;

                                $array1[] = $region_id->region_id;

                                break;
                            }

                        }
                    }
                    foreach ($centers as $centerlives) {
                        $locationchecked = Locations::find($centerlives);
                        if ($locationchecked && ! in_array($locationchecked->region_id, $array1)) {
                            $location_array_1[] = $centerlives;
                        }
                    }

                }
                if (count($location_array_1) == 0) {
                    $location_array_2 = $centers;
                    $location_array_1 = $location_array_2->toArray();
                }
            }
        }

        return $location_array_1;
    }

    /*
    * create Service Array to Add
    * @param:  (int) $account_id (array) $service
    *
    * @return: (mixed)
    */
    public static function generateservicearray($store_service, $account_id)
    {

        if (is_array($store_service) && count($store_service)) {
            $first_child = Services::where([
                'account_id' => $account_id,
                'slug' => 'all',
                'active' => 1,
            ])->select('id', 'name')->first();

            if ($first_child && in_array($first_child->id, $store_service)) {

                $service_array[] = $first_child->id;

                return $service_array;
            } else {
                foreach ($store_service as $service) {
                    $service = Services::find($service);
                    if ($service->end_node == '0') {
                        $servicechild = Services::where('parent_id', '=', $service->id)->get();
                        foreach ($servicechild as $servicechild) {
                            $fields = array_flip($store_service);
                            unset($fields[$servicechild->id]);
                            $store_service = array_flip($fields);
                        }

                    }
                }

                return $store_service;
            }
        }
    }

    /*
    * Array of centers with option group of regions
    *
    * @param:  (int) $account_id (array) $service
    *
    * @return: (mixed)
    */
    public static function locationPackageArray($account_id)
    {

        $regions = Regions::where([
            'account_id' => $account_id,
            'slug' => 'custom',
        ])->orderBy('sort_number', 'asc')->select('id', 'name', 'slug')->get();

        $dropdown_array = [];

        foreach ($regions as $region) {
            $dropdown_array[$region->id] = [
                'id' => $region->id,
                'name' => $region->name,
                'children' => [],
            ];

            $other_childrens = Locations::where([
                'account_id' => $account_id,
                'region_id' => $region->id,
                'slug' => 'custom',
            ])->orderBy('name', 'asc')->get();

            if ($other_childrens) {
                foreach ($other_childrens as $other_children) {
                    $dropdown_array[$region->id]['children'][$other_children->id] = [
                        'id' => $other_children->id,
                        'name' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'.$other_children->full_address,
                        'slug' => $other_children->slug,
                    ];
                }
            }
        }

        return $dropdown_array;

    }

    /*
    * Array of centers with option group of regions
    *
    * @param:  (int) $account_id (array) $service
    * @return: (mixed)
    */
    public static function loadAppointmentDoctorByLocation($location_id, $account_id)
    {

        /*
         * Strategy:
         * 1) Get All Location Doctors
         * 2) Get Region Based Doctors
         * 3) Get Centre based Doctors
         */

        // 1)
        $doctors = DoctorHasLocations::where('is_allocated',1)->where(['location_id' => Locations::where([
            'slug' => 'all',
            'account_id' => $account_id,
        ])->select('id')->first()->id])->select('user_id')->get();

        $doctor_array = [];

        if ($doctors->count()) {
            foreach ($doctors as $doctor) {
                $doctor_array[] = $doctor->user_id;
            }
        }

        // 2)
        $location = Locations::find($location_id);
        $regionLocation = Locations::where([
            'slug' => 'region',
            'region_id' => $location->region_id,
        ])->select('id')->first();

        $doctors = DoctorHasLocations::where('is_allocated',1)->where(['location_id' => Locations::where([
            'slug' => 'region',
            'region_id' => $location->region_id,
        ])->select('id')->first()?->id])->select('user_id')->get();

        if ($doctors->count()) {
            foreach ($doctors as $doctor) {
                $doctor_array[] = $doctor->user_id;
            }
        }

        $doctors = Doctors::getActiveOnly($location_id);

        if ($doctors) {
            foreach ($doctors as $doctor_id => $value) {
                $doctor_array[] = $doctor_id;
            }
        }

        $doctors = Doctors::getActiveOnly(null, $account_id, $doctor_array);

        return $doctors;
    }
    public static function loadConsultantDoctorByLocation($location_id, $account_id)
{
    $doctorIds = DoctorHasLocations::where('is_allocated',1)->where('location_id', $location_id)
        ->pluck('user_id')
        ->toArray();

    // Include doctors who have Consultant/Lifestyle Consultant role 
    // OR Aesthetic Doctor role with can_perform_consultation=true
    $filteredDoctors = User::whereIn('id', $doctorIds)
        ->where('active', 1)
        ->where(function ($query) {
            // Consultants and Lifestyle Consultants
            $query->whereHas('user_roles', function ($q) {
                $q->whereIn('name', ['Consultant', 'Lifestyle Consultant']);
            })
            // OR Aesthetic Doctors with can_perform_consultation enabled
            ->orWhere(function ($q) {
                $q->where('can_perform_consultation', 1)
                    ->whereHas('user_roles', function ($roleQuery) {
                        $roleQuery->where('name', 'Aesthetic Doctor');
                    });
            });
        })
        ->pluck('name', 'id'); // key = user_id, value = name

    return $filteredDoctors;
}
    /*
    * Array of centers with option group of regions
    *
    * @param:  (int) $account_id (array) $service
    * @return: (mixed)
    */
    public static function loadAppointmentServiceByLocation($location_id, $account_id)
    {

        /*
         * Strategy:
         * 1) Get All Location Services
         * 2) Get Region Based Services
         * 3) Get Centre based Services
         */

        // 1)
        $doctors = DoctorHasLocations::where('is_allocated',1)->where(['location_id' => Locations::where([
            'slug' => 'all',
            'account_id' => $account_id,
        ])->select('id')->first()->id])->select('user_id')->get();

        $doctor_array = [];

        if ($doctors->count()) {
            foreach ($doctors as $doctor) {
                $doctor_array[] = $doctor->user_id;
            }
        }

        // 2)
        $location = Locations::find($location_id);
        $regionLocation = Locations::where([
            'slug' => 'region',
            'region_id' => $location->region_id,
        ])->select('id')->first();

        $doctors = DoctorHasLocations::where('is_allocated',1)->where(['location_id' => Locations::where([
            'slug' => 'region',
            'region_id' => $location->region_id,
        ])->select('id')->first()->id])->select('user_id')->get();

        if ($doctors->count()) {
            foreach ($doctors as $doctor) {
                $doctor_array[] = $doctor->user_id;
            }
        }

        $doctors = Doctors::getActiveOnly($location_id);

        if ($doctors) {
            foreach ($doctors as $doctor_id => $value) {
                $doctor_array[] = $doctor_id;
            }
        }

        $doctors = Doctors::getActiveOnly(null, $account_id, $doctor_array);

        return $doctors;
    }

    public static function findRoot($service_id, $data)
    {
        if ($data[$service_id]['parent_id'] == '0') {
            return $service_id;
        } else {
            return self::findRoot($data[$service_id]['parent_id'], $data);
        }
    }

    public static function findParent($service_id, $data)
    {
        if ($data[$service_id]['end_node'] == '0') {
            return $service_id;
        } else {
            return self::findParent($data[$service_id]['parent_id'], $data);
        }
    }

    public static function findServiceParents($service_id, $data, $parents = [])
    {
        if (isset($data[$service_id]['parent_id']) && $data[$service_id]['parent_id'] == '0') {
            if ($data[$service_id]['end_node'] == '0') {
                $parents[] = $data[$service_id]['id'];
            }

            return $parents;
        } else {
            if (isset($data[$service_id]['end_node']) && $data[$service_id]['end_node'] == '0') {
                $parents[] = $data[$service_id]['id'];
            }
            if (isset($data[$service_id]['parent_id'])) {
                return self::findServiceParents($data[$service_id]['parent_id'], $data, $parents);
            }
        }
    }

    public static function findNestedServicesEndNodes($data, $nodes = [])
    {
        foreach ($data as $node) {
            if ((isset($node['children']) && count($node['children']))) {
                $nodes = array_unique(array_merge($nodes, self::findNestedServicesEndNodes($node['children'], $nodes)));
            } else {
                if ($node['end_node'] == '1') {
                    $nodes[] = $node['id'];
                }
            }
        }

        return $nodes;
    }

    public static function getNestedServicesByID($service_id, $data)
    {
        $nested = [];

        foreach ($data as &$s) {
            if ($s['id'] == $service_id) {
                // no parent_id so we put it in the root of the array
                $nested[$s['id']] = &$s;

                if ($s['end_node'] == '0') {
                    $nested[$s['id']]['children'] = [];
                }
            } else {
                $pid = $s['parent_id'];
                $id = $s['id'];
                if (isset($data[$pid])) {
                    // If the parent ID exists in the source array
                    // we add it to the 'children' array of the parent after initializing it.

                    if ($data[$id]['end_node'] == '0' && ! isset($data[$id]['children'])) {
                        $data[$id]['children'] = [];
                    }

                    if (! isset($data[$pid]['children'])) {
                        $data[$pid]['children'] = [];
                    }

                    $data[$pid]['children'][$s['id']] = &$s;
                }
            }
        }

        return $nested;
    }

    public static function buildNestedServices($data)
    {
        $nested = [];

        foreach ($data as &$s) {
            if ($s['parent_id'] == '0') {
                // no parent_id so we put it in the root of the array
                $nested[$s['id']] = &$s;

                if ($s['end_node'] == '0') {
                    $nested[$s['id']]['children'] = [];
                }
            } else {
                $pid = $s['parent_id'];
                $id = $s['id'];
                if (isset($data[$pid])) {
                    // If the parent ID exists in the source array
                    // we add it to the 'children' array of the parent after initializing it.

                    if ($data[$id]['end_node'] == '0' && ! isset($data[$id]['children'])) {
                        $data[$id]['children'] = [];
                    }

                    if (! isset($data[$pid]['children'])) {
                        $data[$pid]['children'] = [];
                    }

                    $data[$pid]['children'][$s['id']] = &$s;
                }
            }
        }

        return $nested;
    }

    /*
    * Function to provide root service group
    *
    * @param:  (int) $account_id (array) $service
    * @return: (mixed)
    */
    public static function getRootServiceGroup($service_id, $account_id)
    {
        $service_id = 14;

        $services = Services::where([
            'account_id' => $account_id,
            'active' => 1,
        ])->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id');

        if ($services->count()) {
            $services = $services->toArray();
        }
    }

    /*
    * Array of centers with option group of regions
    *
    * @param:  (int) $account_id (array) $service
    * @return: (mixed)
    */
    public static function loadAppointmentServiceByLocationDoctor($location_id, $doctor_id, $account_id, $reverse_process = false)
    {
        $searchServices = Services::where([
            'account_id' => $account_id,
            'active' => 1,
        ])->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id');

        if ($searchServices->count()) {
            $searchServices = $searchServices->toArray();
        }

        // Locaton Based Services Array
        $location_services_array = [];
        $services = ServiceHasLocations::join('services', 'services.id', '=', 'service_has_locations.service_id')
            ->where([
                'service_has_locations.service_id' => Services::where([
                    'slug' => 'all',
                    'account_id' => $account_id,
                ])->select('id')->first()->id,
                'service_has_locations.location_id' => $location_id,
            ])->get();

        if ($services->count()) {

            $ss = Services::where([
                'slug' => 'custom',
                'account_id' => $account_id,
                'parent_id' => '0',
                'active' => 1,
            ])->select('id')->get();

            if ($ss->count()) {
                foreach ($ss as $service) {
                    if ($reverse_process) {
                        $location_services_array = array_unique(
                            array_merge(
                                $location_services_array,
                                self::findNestedServicesEndNodes(
                                    self::getNestedServicesByID(
                                        $service->id, $searchServices
                                    )
                                )
                            )
                        );
                    } else {
                        $location_services_array[] = $service->id;
                    }
                }
            }
        } else {
            $centreServices = ServiceHasLocations::join('services', 'services.id', '=', 'service_has_locations.service_id')
                ->where([
                    'service_has_locations.account_id' => $account_id,
                    'service_has_locations.location_id' => $location_id,
                ])->get();

            if ($centreServices->count()) {
                foreach ($centreServices as $centreService) {
                    if ($reverse_process) {
                        $location_services_array = array_unique(
                            array_merge(
                                $location_services_array,
                                self::findNestedServicesEndNodes(
                                    self::getNestedServicesByID(
                                        $centreService->service_id, $searchServices
                                    )
                                )
                            )
                        );
                    } else {
                        $rootService = self::findRoot($centreService->service_id, $searchServices);
                        if (! in_array($rootService, $location_services_array)) {
                            $location_services_array[] = $rootService;
                        }
                    }
                }
            }
        }

        /*
         * Doctor Based Services Array
         */
        $doctor_services_array = [];

        // 1. Find All Centres
        $rootlocation = DoctorHasLocations::where('is_allocated',1)->where([
            'location_id' => Locations::where([
                'slug' => 'all',
                'account_id' => $account_id,
            ])->select('id')->first()->id,
            'user_id' => $doctor_id,
        ])->get();

        if ($rootlocation->count()) {
            //      Find All Services
            $rootservice = DoctorHasLocations::where('is_allocated',1)->where([
                'service_id' => Services::where([
                    'slug' => 'all',
                    'account_id' => $account_id,
                ])->select('id')->first()->id,
                'user_id' => $doctor_id,
            ])->get();

            if ($rootservice->count()) {
                $ss = Services::where([
                    'slug' => 'custom',
                    'account_id' => $account_id,
                    'parent_id' => '0',
                ])->select('id')->get();

                if ($ss->count()) {
                    foreach ($ss as $service) {
                        if ($reverse_process) {
                            $doctor_services_array = array_unique(
                                array_merge(
                                    $doctor_services_array,
                                    self::findNestedServicesEndNodes(
                                        self::getNestedServicesByID(
                                            $service->id, $searchServices
                                        )
                                    )
                                )
                            );
                        } else {
                            $doctor_services_array[] = $service->id;
                        }
                    }
                }
            } else {
                //      Find Allocated Services
                $doctorservices = DoctorHasLocations::where('is_allocated',1)->where([
                    'user_id' => $doctor_id,
                ])->get();

                if ($doctorservices->count()) {
                    foreach ($doctorservices as $doctorservice) {
                        if ($reverse_process) {
                            $doctor_services_array = array_unique(
                                array_merge(
                                    $doctor_services_array,
                                    self::findNestedServicesEndNodes(
                                        self::getNestedServicesByID(
                                            $doctorservice->service_id, $searchServices
                                        )
                                    )
                                )
                            );
                        } else {
                            $rootService = self::findRoot($doctorservice->service_id, $searchServices);
                            if (! in_array($rootService, $doctor_services_array)) {
                                $doctor_services_array[] = $rootService;
                            }
                        }
                    }
                }
            }
        } else {
            // 2. Find All Regions
            $singleLocation = Locations::find($location_id);
            $location_count = Locations::where(['slug' => 'region', 'account_id' => $account_id, 'region_id' => $singleLocation->region_id])->count();
            if ($location_count) {
                $regionlocation = DoctorHasLocations::where('is_allocated',1)->where([
                    'location_id' => Locations::where([
                        'slug' => 'region',
                        'account_id' => $account_id,
                        'region_id' => $singleLocation->region_id,
                    ])->select('id')->first()->id,
                    'user_id' => $doctor_id,
                ])->get();
            } else {
                $regionlocation = DoctorHasLocations::where('is_allocated',1)->where([
                    'location_id' => Locations::where([
                        'slug' => 'region',
                        'account_id' => $account_id,
                        'region_id' => null,
                    ])->select('id')->first(),
                    'user_id' => $doctor_id,
                ])->get();
            }

            if ($regionlocation->count()) {
                //      Find All Services
                $rootservice = DoctorHasLocations::where('is_allocated',1)->where([
                    'service_id' => Services::where([
                        'slug' => 'all',
                        'account_id' => $account_id,
                    ])->select('id')->first()->id,
                    'user_id' => $doctor_id,
                ])->get();

                if ($rootservice->count()) {
                    $ss = Services::where([
                        'slug' => 'custom',
                        'account_id' => $account_id,
                        'parent_id' => '0',
                    ])->select('id')->get();

                    if ($ss->count()) {
                        foreach ($ss as $service) {
                            if ($reverse_process) {
                                $doctor_services_array = array_unique(
                                    array_merge(
                                        $doctor_services_array,
                                        self::findNestedServicesEndNodes(
                                            self::getNestedServicesByID(
                                                $service->id, $searchServices
                                            )
                                        )
                                    )
                                );
                            } else {
                                $doctor_services_array[] = $service->id;
                            }
                        }
                    }
                } else {
                    //      Find Allocated Services
                    $doctorservices = DoctorHasLocations::where('is_allocated',1)->where([
                        'user_id' => $doctor_id,
                    ])->get();

                    if ($doctorservices->count()) {
                        foreach ($doctorservices as $doctorservice) {
                            if ($reverse_process) {
                                $doctor_services_array = array_unique(
                                    array_merge(
                                        $doctor_services_array,
                                        self::findNestedServicesEndNodes(
                                            self::getNestedServicesByID(
                                                $doctorservice->service_id, $searchServices
                                            )
                                        )
                                    )
                                );
                            } else {
                                $rootService = self::findRoot($doctorservice->service_id, $searchServices);
                                if (! in_array($rootService, $doctor_services_array)) {
                                    $doctor_services_array[] = $rootService;
                                }
                            }
                        }
                    }
                }
            } else {
                // 3. Find Single Centre
                $singlelocation = DoctorHasLocations::where('is_allocated',1)->where([
                    'user_id' => $doctor_id,
                    'location_id' => $location_id,
                ])->get();

                if ($singlelocation->count()) {
                    //      Find All Services
                    $rootservice = DoctorHasLocations::where('is_allocated',1)->where([
                        'service_id' => Services::where([
                            'slug' => 'all',
                            'account_id' => $account_id,
                        ])->select('id')->first()->id,
                        'user_id' => $doctor_id,
                        'location_id' => $location_id,
                    ])->get();

                    if ($rootservice->count()) {
                        $ss = Services::where([
                            'slug' => 'custom',
                            'account_id' => $account_id,
                            'parent_id' => '0',
                        ])->select('id')->get();

                        if ($ss->count()) {
                            foreach ($ss as $service) {
                                if ($reverse_process) {
                                    $doctor_services_array = array_unique(
                                        array_merge(
                                            $doctor_services_array,
                                            self::findNestedServicesEndNodes(
                                                self::getNestedServicesByID(
                                                    $service->id, $searchServices
                                                )
                                            )
                                        )
                                    );
                                } else {
                                    $doctor_services_array[] = $service->id;
                                }
                            }
                        }
                    } else {
                        //      Find Allocated Services
                        $doctorservices =DoctorHasLocations::where('is_allocated',1)->where([
                            'user_id' => $doctor_id,
                            'location_id' => $location_id,
                        ])->get();

                        if ($doctorservices->count()) {
                            foreach ($doctorservices as $doctorservice) {
                                if ($reverse_process) {
                                    $doctor_services_array = array_unique(
                                        array_merge(
                                            $doctor_services_array,
                                            self::findNestedServicesEndNodes(
                                                self::getNestedServicesByID(
                                                    $doctorservice->service_id, $searchServices
                                                )
                                            )
                                        )
                                    );
                                } else {
                                    $rootService = self::findRoot($doctorservice->service_id, $searchServices);
                                    if (! in_array($rootService, $doctor_services_array)) {
                                        $doctor_services_array[] = $rootService;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        if (count($location_services_array) && count($doctor_services_array)) {
            return array_intersect($location_services_array, $doctor_services_array);
        }

        return [];
    }

    /*
    * Array of centers with option group of regions
    *
    * @param:  (int) $account_id (array) $service
    * @return: (mixed)
    */
    public static function loadEndServiceByLocation($location_id, $account_id)
    {
        $searchServices = Services::where([
            'account_id' => $account_id,
            'active' => 1,
        ])->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id');

        if ($searchServices->count()) {
            $searchServices = $searchServices->toArray();
        }

        // Locaton Based Services Array
        $location_services_array = [];
        $services = ServiceHasLocations::where([
            'service_id' => Services::where([
                'slug' => 'all',
                'account_id' => $account_id,
            ])->select('id')->first()->id,
            'location_id' => $location_id,
        ])->get();

        if ($services->count()) {
            $ss = Services::where([
                'slug' => 'custom',
                'account_id' => $account_id,
                'parent_id' => '0',
            ])->select('id')->get();

            if ($ss->count()) {
                foreach ($ss as $service) {
                    $location_services_array = array_unique(
                        array_merge(
                            $location_services_array,
                            self::findNestedServicesEndNodes(
                                self::getNestedServicesByID(
                                    $service->id, $searchServices
                                )
                            )
                        )
                    );
                }
            }
        } else {
            $centreServices = ServiceHasLocations::where([
                'account_id' => $account_id,
                'location_id' => $location_id,
            ])->get();

            if ($centreServices->count()) {
                foreach ($centreServices as $centreService) {
                    $location_services_array = array_unique(
                        array_merge(
                            $location_services_array,
                            self::findNestedServicesEndNodes(
                                self::getNestedServicesByID(
                                    $centreService->service_id, $searchServices
                                )
                            )
                        )
                    );
                }
            }
        }

        return $location_services_array;
    }

    /*
    * funtion that return machine type assign services
    *
    * @param:  (int) $account_id (array) $service
    * @return: (mixed)
    */
    public static function loadAppointmentServiceByLocationResource($resource_id, $account_id)
    {
        /*First that use to return resource assign service I (Bilal) shift that function in machine type assign services */
        $searchServices = Services::where([
            'account_id' => $account_id,
            'active' => 1,
        ])->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id');

        if ($searchServices->count()) {
            $searchServices = $searchServices->toArray();
        }

        $resource_machine_type_services_array = [];
        //      Find All Services

        $resoruce_info = Resources::find($resource_id);

        $machinetype = MachineType::find($resoruce_info->machine_type_id);

        $rootservice = MachineTypeHasServices::join('services', 'services.id', '=', 'machine_type_has_services.service_id')
            ->where([
                'machine_type_has_services.service_id' => Services::where([
                    'slug' => 'all',
                    'account_id' => $account_id,
                ])->select('id')->first()->id,
                'services.active' => 1,
                'machine_type_has_services.machine_type_id' => $machinetype->id,
            ])->get();

        if ($rootservice->count()) {
            $ss = Services::where([
                'slug' => 'custom',
                'account_id' => $account_id,
                'parent_id' => '0',
                'active' => 1,
            ])->select('id')->get();

            if ($ss->count()) {
                foreach ($ss as $service) {
                    $resource_machine_type_services_array[] = $service->id;
                }
            }
        } else {
            //      Find Allocated Services
            $machineervices = MachineTypeHasServices::join('services', 'services.id', '=', 'machine_type_has_services.service_id')
                ->where([
                    'machine_type_has_services.machine_type_id' => $machinetype->id,
                    'services.active' => 1,
                ])
                ->get();

            if ($machineervices->count()) {
                foreach ($machineervices as $resourceservice) {
                    $rootService = self::findRoot($resourceservice->service_id, $searchServices);
                    if (! in_array($rootService, $resource_machine_type_services_array)) {
                        $resource_machine_type_services_array[] = $rootService;
                    }
                }
            }
        }

        return $resource_machine_type_services_array;
    }
}
