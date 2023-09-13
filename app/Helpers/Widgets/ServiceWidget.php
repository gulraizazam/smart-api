<?php
/**
 * Created by PhpStorm.
 * User: REDSignal
 * Date: 3/22/2018
 * Time: 3:49 PM
 */

namespace App\Helpers\Widgets;

use App\Helpers\GeneralFunctions;
use App\Helpers\NodesTree;
use App\Models\Bundles;
use App\Models\Locations;
use App\Models\ServiceHasLocations;
use App\Models\Services;
use Carbon\Carbon;

class ServiceWidget
{
    /*
     * create Service Dropdown with Heiracrchy
     * @param: $request (int) $account_id
     *
     * @return: (mixed) $result
     */
    public static function generateServiceArrayArray($request, $account_id)
    {
        $Services = [];
        $result = [];
        $location = Locations::find($request->id);
        if ($location) {
            if ($location->slug == 'region') {
                $locations = Locations::where([
                    ['slug', '=', 'custom'],
                    ['region_id', '=', $location->region_id],
                ])->get();
            }
            if ($location->slug == 'custom') {
                $locations = Locations::where('id', '=', $location->id)->get();
            }
            if ($location->slug == 'all') {
                $locations = Locations::where('slug', '=', 'custom')->get();
            }
            foreach ($locations as $s_location) {
                $service_has_location = ServiceHasLocations::where('location_id', '=', $s_location->id)->get();
                foreach ($service_has_location as $servicehaslocation) {
                    $service_data = Services::find($servicehaslocation->service_id);
                    if ($service_data->slug == 'all') {
                        $Services = GeneralFunctions::ServicesTreeList();

                        return $Services;
                    } else {
                        $Services = GeneralFunctions::ServicesTreeList(null, 0, $service_data->id);

                        return $Services;
                    }
                }
            }
        }
    }
    public static function generateServiceArrayDiscount($request, $account_id)
    {
        $Services = [];
        $result = [];
        $Services = GeneralFunctions::ServicesTreeList();
        return $Services;              
                   
    }
    /*
     * create Service Dropdown with Heiracrchy for consultancy
     * @param: $request (int) $account_id
     *
     * @return: (mixed) $result
     */
    public static function generateServiceArrayConsultancy($request, $account_id)
    {
        $Services = [];
        $result = [];
        $location = Locations::find($request->id);
        if ($location) {
            if ($location->slug == 'region') {
                $locations = Locations::where([
                    ['slug', '=', 'custom'],
                    ['region_id', '=', $location->region_id],
                ])->get();
            }
            if ($location->slug == 'custom') {
                $locations = Locations::where('id', '=', $location->id)->get();
            }
            if ($location->slug == 'all') {
                $locations = Locations::where('slug', '=', 'custom')->get();
            }
            foreach ($locations as $s_location) {
                $service_has_location = ServiceHasLocations::where('location_id', '=', $s_location->id)->get();
                foreach ($service_has_location as $servicehaslocation) {
                    $service_data = Services::find($servicehaslocation->service_id);
                    if ($service_data->slug == 'all') {
                        $Services = GeneralFunctions::ServicesTreeList();

                        return $Services;
                    } else {
                        $Services = GeneralFunctions::ServicesTreeList(null, 0, $service_data->id);

                        return $Services;
                    }
                }
            }
        }
    }

    /*
     * create Service Dropdown for plans against location id.
     *
     * @param: $service_has_location (int) $account_id
     *
     * @return: (mixed) $result
     */
    public static function generateServicelcoationArray($service_has_location, $account_id)
    {
        $date = Carbon::now();
        $services = [];
        $Services = [];
        $allService = Services::where(['slug' => 'all'])->select('id')->first();
        foreach ($service_has_location as $servicehaslocation) {
            $service_data = Services::find($servicehaslocation->service_id);
            if ($service_data->slug == 'all') {
                $parentGroups = new NodesTree();
                $parentGroups->current_id = 0;
                $parentGroups->non_negative_groups = true;
                $parentGroups->build(0, $account_id, true, true);
                $parentGroups->toList($parentGroups, 0);
                $parentGroups = $parentGroups->nodeList;
                foreach ($parentGroups as $key => $parentGroup) {
                    if ($key == 0) {
                        continue;
                    }
                    $Services[] = $parentGroup['id'];
                }
                $service1 = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
                    ->whereIn('bundle_has_services.service_id', $Services)
                    ->where([
                        ['bundle_has_services.end_node', '=', '1'],
                        ['bundle_has_services.service_id', '!=', $allService->id],
                        ['bundles.account_id', '=', $account_id],
                        ['bundles.active', '=', '1'],
                        ['bundles.start', '<=', \Carbon\Carbon::parse($date)->format('Y-m-d')],
                        ['bundles.end', '>=', \Carbon\Carbon::parse($date)->format('Y-m-d')],
                        ['bundles.type', '=', 'multiple'],
                    ])->groupBy('bundles.id')->get();
                $service2 = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
                    ->whereIn('bundle_has_services.service_id', $Services)
                    ->where([
                        ['bundle_has_services.end_node', '=', '1'],
                        ['bundle_has_services.service_id', '!=', $allService->id],
                        ['bundles.account_id', '=', $account_id],
                        ['bundles.active', '=', '1'],
                        ['bundles.type', '=', 'single'],
                    ])->groupBy('bundles.id')->get();
                $merged = $service2->merge($service1);
                $service = $merged->all();

                return $service;
            } else {
                $parentGroups = new NodesTree();
                $parentGroups->current_id = 1;
                $parentGroups->non_negative_groups = true;
                $parentGroups->build($service_data->id, $account_id, false, true);
                $parentGroups->toList($parentGroups, 0);
                $parentGroups = $parentGroups->nodeList;
                $services[] = $parentGroups;
            }
        }
        foreach ($services as $key => $parentGroup) {
            foreach ($parentGroup as $service) {
                $Services[] = $service['id'];
            }
        }
        $service1 = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
            ->whereIn('bundle_has_services.service_id', $Services)
            ->where([
                ['bundle_has_services.end_node', '=', '1'],
                ['bundle_has_services.service_id', '!=', $allService->id],
                ['bundles.account_id', '=', $account_id],
                ['bundles.active', '=', '1'],
                ['bundles.start', '<=', \Carbon\Carbon::parse($date)->format('Y-m-d')],
                ['bundles.end', '>=', \Carbon\Carbon::parse($date)->format('Y-m-d')],
                ['bundles.type', '=', 'multiple'],
            ])->groupBy('bundles.id')->get();
        $service2 = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
            ->whereIn('bundle_has_services.service_id', $Services)
            ->where([
                ['bundle_has_services.end_node', '=', '1'],
                ['bundle_has_services.service_id', '!=', $allService->id],
                ['bundles.account_id', '=', $account_id],
                ['bundles.active', '=', '1'],
                ['bundles.type', '=', 'single'],
            ])->groupBy('bundles.id')->get();
        $merged = $service2->merge($service1);
        $service = $merged->all();

        return $service;
    }

    /*
     * create Service Dropdown for appoitment against Doctor id.
     *
     * @param: $service_has_location (int) $account_id
     *
     * @return: (mixed) $result
     */
    public static function generateServiceArrayForAppointment($doctor_has_locations, $account_id)
    {

        $allService = Services::where(['slug' => 'all'])->select('id')->first();

        foreach ($doctor_has_locations as $doctorhaslocation) {

            $service_data = Services::find($doctorhaslocation->service_id);

            if ($service_data->slug == 'all') {

                $parentGroups = new NodesTree();
                $parentGroups->current_id = 0;
                $parentGroups->non_negative_groups = true;
                $parentGroups->build(0, $account_id, true, true);
                $parentGroups->toList($parentGroups, 0);
                $parentGroups = $parentGroups->nodeList;

                foreach ($parentGroups as $key => $parentGroup) {
                    if ($key == 0) {
                        continue;
                    }
                    $Services[] = $parentGroup['id'];
                }
                $service = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
                    ->whereIn('bundle_has_services.service_id', $Services)
                    ->where([
                        ['bundle_has_services.end_node', '=', '1'],
                        ['bundle_has_services.service_id', '!=', $allService->id],
                        ['bundles.account_id', '=', $account_id],
                        ['bundles.type', '=', 'single'],
                    ])
                    ->groupBy('bundles.id')->get();

                return $service;
            } else {
                $parentGroups = new NodesTree();
                $parentGroups->current_id = 1;
                $parentGroups->non_negative_groups = true;
                $parentGroups->build($service_data->id, $account_id, false, true);
                $parentGroups->toList($parentGroups, 0);
                $parentGroups = $parentGroups->nodeList;
                $services[] = $parentGroups;
            }
        }

        foreach ($services as $key => $parentGroup) {
            foreach ($parentGroup as $service) {
                $Services[] = $service['id'];
            }

        }

        $service = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
            ->whereIn('bundle_has_services.service_id', $Services)
            ->where([
                ['bundle_has_services.end_node', '=', '1'],
                ['bundle_has_services.service_id', '!=', $allService->id],
                ['bundles.account_id', '=', $account_id],
                ['bundles.type', '=', 'single'],
            ])
            ->groupBy('bundles.id')
            ->select('bundles.id', 'bundles.name')
            ->get();

        return $service;
    }
}
