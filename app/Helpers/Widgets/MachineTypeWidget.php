<?php
/**
 * Created by PhpStorm.
 * User: REDSignal
 * Date: 3/22/2018
 * Time: 3:49 PM
 */

namespace App\Helpers\Widgets;

use App\Models\MachineTypeHasServices;
use App\Models\ServiceHasLocations;
use App\Models\Services;

class MachineTypeWidget
{
    /*
    * Function give root or parent service ids if you give location id
    *
    * @param:  (int) $account_id (array) $service
    * @return: (mixed)
    */
    static function loadlocationservice($location_id, $account_id, $reverse_process = false)
    {
        $searchServices = Services::where(array(
            'account_id' => $account_id,
            'active' => 1,
        ))->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id');

        if ($searchServices->count()) {
            $searchServices = $searchServices->toArray();
        }

        // Locaton Based Services Array
        $location_services_array = array();

        $services = ServiceHasLocations
            ::join('services', 'services.id', '=', 'service_has_locations.service_id')
            ->where([
                'service_has_locations.service_id' => Services::where(array(
                    'slug' => 'all',
                    'account_id' => $account_id
                ))->select('id')->first()->id,
                'service_has_locations.location_id' => $location_id
            ])->get();

        if ($services->count()) {
            $ss = Services::where(array(
                'slug' => 'custom',
                'account_id' => $account_id,
                'parent_id' => '0',
                'active' => 1,
            ))->select('id')->get();

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
            $centreServices = ServiceHasLocations
                ::join('services', 'services.id', '=', 'service_has_locations.service_id')
                ->where(array(
                    'service_has_locations.account_id' => $account_id,
                    'service_has_locations.location_id' => $location_id,
                ))->get();

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
                        if (!in_array($rootService, $location_services_array)) {
                            $location_services_array[] = $rootService;
                        }
                    }
                }
            }
        }

        if (count($location_services_array)) {
            return $location_services_array;
        }
        return array();
    }

    /*
    * Function give root or parent service ids if you give machine id
    *
    * @param:  (int) $account_id (array) $service
    * @return: (mixed)
    */
    static function loadmachinetypeservice($machine_type_id, $account_id, $reverse_process = false)
    {
        $searchServices = Services::where(array(
            'account_id' => $account_id,
            'active' => 1,
        ))->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id');

        if ($searchServices->count()) {
            $searchServices = $searchServices->toArray();
        }

        // machine type Based Services Array
        $machinetype_services_array = array();

        $services = MachineTypeHasServices
            ::join('services', 'services.id', '=', 'machine_type_has_services.service_id')
            ->where([
                'machine_type_has_services.service_id' => Services::where(array(
                    'slug' => 'all',
                    'account_id' => $account_id
                ))->select('id')->first()->id,
                'machine_type_has_services.machine_type_id' => $machine_type_id
            ])->get();

        if ($services->count()) {
            $ss = Services::where(array(
                'slug' => 'custom',
                'account_id' => $account_id,
                'parent_id' => '0',
                'active' => 1,
            ))->select('id')->get();

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
            $machinetypeServices = MachineTypeHasServices
                ::join('services', 'services.id', '=', 'machine_type_has_services.service_id')
                ->where(array(
                    'machine_type_has_services.machine_type_id' => $machine_type_id,
                ))->get();

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
                        if (!in_array($rootService, $machinetype_services_array)) {
                            $machinetype_services_array[] = $rootService;
                        }
                    }
                }
            }
        }

        if (count($machinetype_services_array)) {
            return $machinetype_services_array;
        }
        return array();
    }


    static public function findNestedServicesEndNodes($data, $nodes = array())
    {
        foreach ($data as $node) {
            if ((isset($node['children']) && sizeof($node['children']))) {
                $nodes = array_unique(array_merge($nodes, self::findNestedServicesEndNodes($node['children'], $nodes)));
            } else {
                if ($node['end_node'] == '1') {
                    $nodes[] = $node['id'];
                }
            }
        }
        return $nodes;
    }

    static function getNestedServicesByID($service_id, $data)
    {
        $nested = array();

        foreach ($data as &$s) {
            if ($s['id'] == $service_id) {
                // no parent_id so we put it in the root of the array
                $nested[$s['id']] = &$s;

                if ($s['end_node'] == '0') {
                    $nested[$s['id']]['children'] = array();
                }
            } else {
                $pid = $s['parent_id'];
                $id = $s['id'];
                if (isset($data[$pid])) {
                    // If the parent ID exists in the source array
                    // we add it to the 'children' array of the parent after initializing it.

                    if ($data[$id]['end_node'] == '0' && !isset($data[$id]['children'])) {
                        $data[$id]['children'] = array();
                    }

                    if (!isset($data[$pid]['children'])) {
                        $data[$pid]['children'] = array();
                    }

                    $data[$pid]['children'][$s['id']] = &$s;
                }
            }
        }

        return $nested;
    }

    static public function findRoot($service_id, $data)
    {
        if ($data[$service_id]['parent_id'] == '0') {
            return $service_id;
        } else {
            return self::findRoot($data[$service_id]['parent_id'], $data);
        }
    }
}