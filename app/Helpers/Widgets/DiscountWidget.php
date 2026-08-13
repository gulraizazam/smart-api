<?php

declare(strict_types=1);
namespace App\Helpers\Widgets;

use App\Models\DiscountHasLocations;
use App\Models\Discounts;
use App\Models\Locations;
use App\Models\Regions;
use App\Models\Services;
use App\Models\User;
use Carbon\Carbon;

class DiscountWidget
{
    /*
     * Find discounts based on Location and Service
     * @param: (int) $account_id
     *
     * @return: (mixed) $result
     */
    public static function findDiscountsByLocationNService($account_id)
    {

        $regions = Regions::where([
            'account_id' => $account_id,
        ])->orderBy('sort_number', 'asc')->select('id', 'name', 'slug')->get();

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
    * Array of centers with option group of regions
    *
    * @param:  (int) $account_id (array) $service
    * @return: (mixed)
    */
    public static function loadPlanDiscountAllocationsByLocationService($location_id, $service_id, $account_id)
    {
        $searchServices = Services::where([
            'account_id' => $account_id,
        ])->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id');

        if ($searchServices->count()) {
            $searchServices = $searchServices->toArray();
        }

        /*
         * Returns allocation records with type and amount for hybrid approach
         * Key: discount_id, Value: allocation record with type/amount
         */
        $allocations = [];

        // 1. Find All Centres
        $allLocationId = Locations::where([
            'slug' => 'all',
            'account_id' => $account_id,
        ])->select('id')->first();

        if ($allLocationId) {
            $allServiceId = Services::where([
                'slug' => 'all',
                'account_id' => $account_id,
            ])->select('id')->first();

            // Find All Services allocations
            if ($allServiceId) {
                $rootAllocations = DiscountHasLocations::where([
                    'service_id' => $allServiceId->id,
                    'location_id' => $allLocationId->id,
                ])->get();

                foreach ($rootAllocations as $allocation) {
                    if (!isset($allocations[$allocation->discount_id])) {
                        $allocations[$allocation->discount_id] = $allocation;
                    }
                }
            }

            // Find Matching Services
            $serviceWithParents = LocationsWidget::findServiceParents($service_id, $searchServices);
            $serviceWithParents = array_merge($serviceWithParents ?? [], [$service_id]);

            $serviceAllocations = DiscountHasLocations::where([
                'location_id' => $allLocationId->id,
            ])->whereIn('service_id', $serviceWithParents)->get();

            foreach ($serviceAllocations as $allocation) {
                // More specific allocation overrides less specific
                $allocations[$allocation->discount_id] = $allocation;
            }
        }

        // 2. Find Region-based allocations
        $singleLocation = Locations::find($location_id);
        if ($singleLocation) {
            $regionLocation = Locations::where([
                'slug' => 'region',
                'account_id' => $account_id,
                'region_id' => $singleLocation->region_id,
            ])->select('id')->first();

            if ($regionLocation) {
                $allServiceId = Services::where([
                    'slug' => 'all',
                    'account_id' => $account_id,
                ])->select('id')->first();

                // Find All Services in region
                if ($allServiceId) {
                    $regionAllocations = DiscountHasLocations::where([
                        'service_id' => $allServiceId->id,
                        'location_id' => $regionLocation->id,
                    ])->get();

                    foreach ($regionAllocations as $allocation) {
                        $allocations[$allocation->discount_id] = $allocation;
                    }
                }

                // Find Matching Services in region
                $serviceWithParents = LocationsWidget::findServiceParents($service_id, $searchServices);
                $serviceWithParents = array_merge($serviceWithParents ?? [], [$service_id]);

                $regionServiceAllocations = DiscountHasLocations::where([
                    'location_id' => $regionLocation->id,
                ])->whereIn('service_id', $serviceWithParents)->get();

                foreach ($regionServiceAllocations as $allocation) {
                    $allocations[$allocation->discount_id] = $allocation;
                }
            }
        }

        // 3. Find Specific Centre allocations (most specific - highest priority)
        $allServiceId = Services::where([
            'slug' => 'all',
            'account_id' => $account_id,
        ])->select('id')->first();

        if ($allServiceId) {
            $centreAllocations = DiscountHasLocations::where([
                'service_id' => $allServiceId->id,
                'location_id' => $location_id,
            ])->get();

            foreach ($centreAllocations as $allocation) {
                $allocations[$allocation->discount_id] = $allocation;
            }
        }

        // Find Matching Services at specific centre (highest priority)
        $serviceWithParents = LocationsWidget::findServiceParents($service_id, $searchServices);
        $serviceWithParents = array_merge($serviceWithParents ?? [], [$service_id]);

        $centreServiceAllocations = DiscountHasLocations::where([
            'location_id' => $location_id,
        ])->whereIn('service_id', $serviceWithParents)->get();

        foreach ($centreServiceAllocations as $allocation) {
            $allocations[$allocation->discount_id] = $allocation;
        }

        return $allocations;
    }

    /*
    * Array of centers with option group of regions (original function - returns only discount IDs)
    *
    * @param:  (int) $account_id (array) $service
    * @return: (mixed)
    */
    public static function loadPlanDsicountByLocationService($location_id, $service_id, $account_id)
    {
        $searchServices = Services::where([
            'account_id' => $account_id,
        ])->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id');

        if ($searchServices->count()) {
            $searchServices = $searchServices->toArray();
        }

        // Pre-fetch reusable lookups once (eliminates ~10 repeated queries)
        $allLocationId = Locations::where([
            'slug' => 'all',
            'account_id' => $account_id,
        ])->value('id');

        $allServiceId = Services::where([
            'slug' => 'all',
            'account_id' => $account_id,
        ])->value('id');

        $singleLocation = Locations::find($location_id);

        $regionLocationId = $singleLocation ? Locations::where([
            'slug' => 'region',
            'account_id' => $account_id,
            'region_id' => $singleLocation->region_id,
        ])->value('id') : null;

        $serviceWithParents = LocationsWidget::findServiceParents($service_id, $searchServices);
        $serviceWithParents = array_merge($serviceWithParents ?? [], [$service_id]);

        /*
         * Case 1: Find those discounts which are All centre based
         */
        $discount_array = [];

        if ($allLocationId) {
            $rootlocation = DiscountHasLocations::where('location_id', $allLocationId)->get();

            if ($rootlocation->count()) {
                if ($allServiceId) {
                    $rootdiscounts = DiscountHasLocations::where([
                        'service_id' => $allServiceId,
                        'location_id' => $allLocationId,
                    ])->get();

                    foreach ($rootdiscounts as $rootdiscount) {
                        if (! in_array($rootdiscount->discount_id, $discount_array, true)) {
                            $discount_array[] = $rootdiscount->discount_id;
                        }
                    }
                }

                $servicediscounts = DiscountHasLocations::where('location_id', $allLocationId)
                    ->whereIn('service_id', $serviceWithParents)
                    ->get();

                foreach ($servicediscounts as $servicediscount) {
                    if (! in_array($servicediscount->discount_id, $discount_array, true)) {
                        $discount_array[] = $servicediscount->discount_id;
                    }
                }
            }
        }

        /*
         * Case 2: Find those discounts which are region based
         */
        if ($regionLocationId) {
            $regionlocation = DiscountHasLocations::where('location_id', $regionLocationId)->get();

            if ($regionlocation->count()) {
                if ($allServiceId) {
                    $regiondiscounts = DiscountHasLocations::where([
                        'service_id' => $allServiceId,
                        'location_id' => $regionLocationId,
                    ])->get();

                    foreach ($regiondiscounts as $regiondiscount) {
                        if (! in_array($regiondiscount->discount_id, $discount_array, true)) {
                            $discount_array[] = $regiondiscount->discount_id;
                        }
                    }
                }

                $servicediscounts = DiscountHasLocations::where('location_id', $regionLocationId)
                    ->whereIn('service_id', $serviceWithParents)
                    ->get();

                foreach ($servicediscounts as $servicediscount) {
                    if (! in_array($servicediscount->discount_id, $discount_array, true)) {
                        $discount_array[] = $servicediscount->discount_id;
                    }
                }
            }
        }

        /*
         * Case 3: Find those discounts which single centre based
         */
        if ($allServiceId) {
            $centrediscounts = DiscountHasLocations::where([
                'service_id' => $allServiceId,
                'location_id' => $location_id,
            ])->get();

            foreach ($centrediscounts as $centrediscount) {
                if (! in_array($centrediscount->discount_id, $discount_array, true)) {
                    $discount_array[] = $centrediscount->discount_id;
                }
            }
        }

        $centreservicediscounts = DiscountHasLocations::where('location_id', $location_id)
            ->whereIn('service_id', $serviceWithParents)
            ->get();

        foreach ($centreservicediscounts as $centreservicediscount) {
            if (! in_array($centreservicediscount->discount_id, $discount_array, true)) {
                $discount_array[] = $centreservicediscount->discount_id;
            }
        }

        return $discount_array;
    }
    /*
     * Function that filter discount for consultancy
     *
     * @param:  $location_id $service_id $account_id
     * @return: (mixed)
     */

    public static function Discount_data_consultancy($appointment, $account_id)
    {

        $discountIds = self::loadPlanDsicountByLocationService($appointment->location_id, $appointment->service_id, $account_id);
        $today = Carbon::now()->toDateString();
        $discounts = Discounts::whereIn('id', $discountIds)->where([
            ['discount_type', '=', 'Consultancy'],
            ['active', '=', '1'],
        ])->whereDate('start', '<=', $today)->whereDate('end', '>=', $today)->get();
        /*Now Checked Brithday promotion valid or not*/
        $patient_info = null;
        $hasBirthdayDiscount = $discounts->contains('slug', 'birthday');
        if ($hasBirthdayDiscount) {
            $patient_info = User::find($appointment->patient_id);
        }
        foreach ($discounts as $key => $discount) {

            if ($discount->slug == 'birthday') {
                /*first get the pre and post days*/
                $pre_days = $discount->pre_days;
                $post_days = $discount->post_days;
                /*end*/

                $today_1 = Carbon::today();
                $today_2 = Carbon::today();
                $today_3 = Carbon::today();

                /*get the date range to checked patient birthday exist between or not*/
                $predate = $today_1->subDay($pre_days)->format('Y-m-d');
                $postdate = $today_2->addDay($post_days)->format('Y-m-d');

                /*Now checked birthday valid or not*/
                if ($patient_info->dob) {

                    $patientbirthday = Carbon::parse($patient_info->dob)->format($today_3->year.'-'.'m-d');

                    if (($patientbirthday >= $predate) && ($patientbirthday <= $postdate)) {
                    } else {
                        $discounts->forget($key);
                    }
                } else {
                    $discounts->forget($key);
                }
            }
        }

        return $discounts;
    }
}
