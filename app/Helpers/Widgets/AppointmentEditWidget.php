<?php
/**
 * Created by PhpStorm.
 * User: REDSignal
 * Date: 3/22/2018
 * Time: 3:49 PM
 */

namespace App\Helpers\Widgets;

use App\Models\DoctorHasLocations;
use App\Models\Locations;
use App\Models\MachineType;
use App\Models\MachineTypeHasServices;
use App\Models\PackageService;
use App\Models\Resources;
use App\Models\ServiceHasLocations;
use App\Models\Services;
use Illuminate\Support\Facades\Auth;

class AppointmentEditWidget
{
    /*
    * That function give you root or parent service ids if you give location id
    *
    * @param:  (int) $account_id (array) $service
    * @return: (mixed)
    */
    public static function loadlocationservice_edit($location_id, $account_id, $reverse_process = false)
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

        if (count($location_services_array)) {
            return $location_services_array;
        }

        return [];
    }

    /*
    * That function give you root or parent service ids if you give doctor id
    * We can assign one doctor to multiple canters so for getting service ids against one center, we need to give location id also
    * @param:  (int) $account_id (array) $service
    * @return: (mixed)
    */
    public static function loaddoctorservice_edit($doctor_id, $location_id, $account_id, $reverse_process = false)
    {
        $searchServices = Services::where([
            'account_id' => $account_id,
            'active' => 1,
        ])->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id');

        if ($searchServices->count()) {
            $searchServices = $searchServices->toArray();
        }

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
            $regionlocation = DoctorHasLocations::where('is_allocated',1)->where([
                'location_id' => Locations::where([
                    'slug' => 'region',
                    'account_id' => $account_id,
                    'region_id' => $singleLocation->region_id,
                ])->select('id')->first()->id,
                'user_id' => $doctor_id,
            ])->get();

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
                        $doctorservices = DoctorHasLocations::where('is_allocated',1)->where([
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

        if (count($doctor_services_array)) {
            return $doctor_services_array;
        }

        return [];
    }

    /*
    * That function give you root or parent service ids if you give machine id
    *
    * @param:  (int) $account_id (array) $service
    * @return: (mixed)
    */
    public static function loadmachinetypeservice_edit($machine_type_id, $account_id, $reverse_process = false)
    {
        $searchServices = Services::where([
            'account_id' => $account_id,
            'active' => 1,
        ])->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id');

        if ($searchServices->count()) {
            $searchServices = $searchServices->toArray();
        }

        // machine type Based Services Array
        $machinetype_services_array = [];

        $services = MachineTypeHasServices::join('services', 'services.id', '=', 'machine_type_has_services.service_id')
            ->where([
                'machine_type_has_services.service_id' => Services::where([
                    'slug' => 'all',
                    'account_id' => $account_id,
                ])->select('id')->first()->id,
                'machine_type_has_services.machine_type_id' => $machine_type_id,
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
                        $machinetype_services_array = array_unique(
                            array_merge(
                                $machinetype_services_array,
                                self::findNestedServicesEndNodes(
                                    self::getNestedServicesByID(
                                        $service->id, $searchServices
                                    )
                                )
                            )
                        );
                    } else {
                        $machinetype_services_array[] = $service->id;
                    }
                }
            }
        } else {
            $machinetypeServices = MachineTypeHasServices::join('services', 'services.id', '=', 'machine_type_has_services.service_id')
                ->where([
                    'machine_type_has_services.machine_type_id' => $machine_type_id,
                ])->get();

            if ($machinetypeServices->count()) {
                foreach ($machinetypeServices as $machinetypeService) {
                    if ($reverse_process) {
                        $machinetype_services_array = array_unique(
                            array_merge(
                                $machinetype_services_array,
                                self::findNestedServicesEndNodes(
                                    self::getNestedServicesByID(
                                        $machinetypeService->service_id, $searchServices
                                    )
                                )
                            )
                        );
                    } else {
                        $rootService = self::findRoot($machinetypeService->service_id, $searchServices);
                        if (! in_array($rootService, $machinetype_services_array)) {
                            $machinetype_services_array[] = $rootService;
                        }
                    }
                }
            }
        }

        if (count($machinetype_services_array)) {
            return $machinetype_services_array;
        }

        return [];
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

    public static function findRoot($service_id, $data)
    {
        if ($data[$service_id]['parent_id'] == '0') {
            return $service_id;
        } else {
            return self::findRoot($data[$service_id]['parent_id'], $data);
        }
    }

    /*
     *  That function I use only for Machine Wise Collection Report
     *  That function give machine type If I give package id
     */

    public static function LoadMachineType_machinewisecollection_report($package, $total_consume_packageservice_ids = false)
    {
        if ($total_consume_packageservice_ids) {
            $package_services = PackageService::where('package_id', '=', $package->id)->whereNotIn('id', $total_consume_packageservice_ids)->whereNotNull('package_id')->get();
        } else {
            $package_services = PackageService::where('package_id', '=', $package->id)->whereNotNull('package_id')->get();
        }

        $machines = Resources::where([
            ['location_id', '=', $package->location_id],
            ['active', '=', '1'],
        ])->get();

        $machines_ids = [];

        foreach ($machines as $machine) {
            if (! in_array($machine->machine_type_id, $machines_ids)) {
                $machines_ids[] = $machine->machine_type_id;
            }
        }

        $machine_types = MachineType::whereIn('id', $machines_ids)->get();

        $machine_service_allocation = [];
        $machine_types_ids = [];

        foreach ($package_services as $package_service) {
            foreach ($machine_types as $machine_type) {

                $machine_serivce = self::loadmachinetypeservice_machine_collection($machine_type->id, Auth::user()->account_id, 'true');

                if (in_array($package_service->service_id, $machine_serivce)) {

                    $machine_types_ids[] = $machine_type->id;

                    $machine_service_allocation[] = [
                        'Machine_type_id' => $machine_type->id,
                        'Service_id' => $package_service->service_id,
                        'Package_service_id' => $package_service->id,
                    ];
                }
            }
        }

        $machine_types = MachineType::whereIn('id', $machine_types_ids)->get();

        return [
            'machine_types' => $machine_types,
            'machine_service_allocation' => $machine_service_allocation,
        ];
    }

    /*
     * That function give you root or parent service ids if you give machine id that for machine
     *
     * @param:  (int) $account_id (array) $service
     * @return: (mixed)
     */
    public static function loadmachinetypeservice_machine_collection($machine_type_id, $account_id, $reverse_process = false)
    {
        $searchServices = Services::where([
            'account_id' => $account_id,
        ])->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id');

        if ($searchServices->count()) {
            $searchServices = $searchServices->toArray();
        }

        // machine type Based Services Array
        $machinetype_services_array = [];

        $services = MachineTypeHasServices::join('services', 'services.id', '=', 'machine_type_has_services.service_id')
            ->where([
                'machine_type_has_services.service_id' => Services::where([
                    'slug' => 'all',
                    'account_id' => $account_id,
                ])->select('id')->first()->id,
                'machine_type_has_services.machine_type_id' => $machine_type_id,
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
                        $machinetype_services_array = array_unique(
                            array_merge(
                                $machinetype_services_array,
                                self::findNestedServicesEndNodes(
                                    self::getNestedServicesByID(
                                        $service->id, $searchServices
                                    )
                                )
                            )
                        );
                    } else {
                        $machinetype_services_array[] = $service->id;
                    }
                }
            }
        } else {
            $machinetypeServices = MachineTypeHasServices::join('services', 'services.id', '=', 'machine_type_has_services.service_id')
                ->where([
                    'machine_type_has_services.machine_type_id' => $machine_type_id,
                ])->get();

            if ($machinetypeServices->count()) {
                foreach ($machinetypeServices as $machinetypeService) {
                    if ($reverse_process) {
                        $machinetype_services_array = array_unique(
                            array_merge(
                                $machinetype_services_array,
                                self::findNestedServicesEndNodes(
                                    self::getNestedServicesByID(
                                        $machinetypeService->service_id, $searchServices
                                    )
                                )
                            )
                        );
                    } else {
                        $rootService = self::findRoot($machinetypeService->service_id, $searchServices);
                        if (! in_array($rootService, $machinetype_services_array)) {
                            $machinetype_services_array[] = $rootService;
                        }
                    }
                }
            }
        }

        if (count($machinetype_services_array)) {
            return $machinetype_services_array;
        }

        return [];
    }
}
