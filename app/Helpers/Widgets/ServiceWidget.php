<?php

declare(strict_types=1);
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
        $location = Locations::find($request->id);
        if (!$location) {
            return [];
        }

        $locations = collect();
        if ($location->slug == 'region') {
            $locations = Locations::where([
                ['slug', '=', 'custom'],
                ['region_id', '=', $location->region_id],
            ])->get();
        } elseif ($location->slug == 'custom') {
            $locations = Locations::where('id', '=', $location->id)->get();
        } elseif ($location->slug == 'all') {
            $locations = Locations::where('slug', '=', 'custom')->get();
        }

        if ($locations->isEmpty()) {
            return [];
        }

        $locationIds = $locations->pluck('id');
        $serviceIds = ServiceHasLocations::whereIn('location_id', $locationIds)
            ->pluck('service_id')
            ->unique()
            ->filter();

        if ($serviceIds->isEmpty()) {
            return [];
        }

        $allocatedServices = Services::whereIn('id', $serviceIds)->get();

        // If any allocated service has slug 'all', return the full services tree.
        if ($allocatedServices->contains(fn ($s) => $s->slug === 'all')) {
            return GeneralFunctions::ServicesTreeList();
        }

        // Otherwise build a merged tree: [AllServices, parent1+children, parent2+children, ...].
        // Root services are stored with parent_id = NULL. A ServiceHasLocations row may
        // point to either a root service or a child; walk up to find the root id for each.
        $rootIds = $allocatedServices->map(function ($service) {
            if ($service->parent_id === null || (int) $service->parent_id === 0) {
                return $service->id;
            }
            $parent = Services::find($service->parent_id);

            return $parent?->id;
        })->filter()->unique()->values();

        if ($rootIds->isEmpty()) {
            return [];
        }

        $parents = Services::with(['children' => function ($q) {
            $q->where('active', 1)->orderBy('name');
        }])
            ->whereIn('id', $rootIds)
            ->where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            })
            ->where('slug', '!=', 'all')
            ->orderBy('name')
            ->get()
            ->map->toArray()
            ->all();

        $allServiceRow = Services::where('slug', 'all')->first();
        if ($allServiceRow) {
            array_unshift($parents, $allServiceRow->toArray());
        }

        return $parents;
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
            $locationIds = $locations->pluck('id');
            $serviceHasLocations = ServiceHasLocations::whereIn('location_id', $locationIds)->get();
            $serviceIds = $serviceHasLocations->pluck('service_id')->unique()->filter();
            $servicesMap = Services::whereIn('id', $serviceIds)->get()->keyBy('id');

            foreach ($serviceHasLocations as $servicehaslocation) {
                $service_data = $servicesMap[$servicehaslocation->service_id] ?? null;
                if (!$service_data) {
                    continue;
                }
                if ($service_data->slug == 'all') {
                    return GeneralFunctions::ServicesTreeList();
                } else {
                    return GeneralFunctions::ServicesTreeList(null, 0, $service_data->id);
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
        $serviceIds = collect($service_has_location)->pluck('service_id')->unique()->filter();
        $servicesMap = Services::whereIn('id', $serviceIds)->get()->keyBy('id');
        foreach ($service_has_location as $servicehaslocation) {
            $service_data = $servicesMap[$servicehaslocation->service_id] ?? null;
            if (!$service_data) {
                continue;
            }
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
                    ])->select('bundles.*')->distinct()->get();
                $service2 = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
                    ->whereIn('bundle_has_services.service_id', $Services)
                    ->where([
                        ['bundle_has_services.end_node', '=', '1'],
                        ['bundle_has_services.service_id', '!=', $allService->id],
                        ['bundles.account_id', '=', $account_id],
                        ['bundles.active', '=', '1'],
                        ['bundles.type', '=', 'single'],
                    ])->select('bundles.*')->distinct()->get();
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
            ])->select('bundles.*')->distinct()->get();
        $service2 = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
            ->whereIn('bundle_has_services.service_id', $Services)
            ->where([
                ['bundle_has_services.end_node', '=', '1'],
                ['bundle_has_services.service_id', '!=', $allService->id],
                ['bundles.account_id', '=', $account_id],
                ['bundles.active', '=', '1'],
                ['bundles.type', '=', 'single'],
            ])->select('bundles.*')->distinct()->get();
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

        $dhlServiceIds = collect($doctor_has_locations)->pluck('service_id')->unique()->filter();
        $dhlServicesMap = Services::whereIn('id', $dhlServiceIds)->get()->keyBy('id');

        foreach ($doctor_has_locations as $doctorhaslocation) {

            $service_data = $dhlServicesMap[$doctorhaslocation->service_id] ?? null;
            if (!$service_data) {
                continue;
            }

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
                    ->select('bundles.*')
                    ->distinct()->get();

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
            ->select('bundles.id', 'bundles.name')
            ->distinct()
            ->get();

        return $service;
    }
}
