<?php
/**
 * Created by PhpStorm.
 * User: REDSignal
 * Date: 3/22/2018
 * Time: 3:49 PM
 */

namespace App\Helpers\Widgets;

use App\Models\DiscountHasLocations;
use App\Models\Locations;
use App\Models\Regions;
use App\Models\Services;
use App\Models\Discounts;
use Carbon\Carbon;
use App\User;

class DiscountWidget
{
    /*
     * Find discounts based on Location and Service
     * @param: (int) $account_id
     *
     * @return: (mixed) $result
     */
    static function findDiscountsByLocationNService($account_id)
    {


        $regions = Regions::where(array(
            'account_id' => $account_id,
        ))->orderBy('sort_number', 'asc')->select('id', 'name', 'slug')->get();

        $dropdown_array = array();

        foreach ($regions as $region) {
            $dropdown_array[$region->id] = array(
                'id' => $region->id,
                'name' => $region->name,
                'optgroup' => $region->name,
                'children' => array(),
            );

            if ($region->slug == 'all') {
                $first_child = Locations::where(array(
                    'account_id' => $account_id,
                    'region_id' => $region->id,
                    'slug' => 'all',
                ))->select('id', 'name', 'slug')->first();

                if ($first_child) {
                    $dropdown_array[$region->id]['children'][$first_child->id] = array(
                        'id' => $first_child->id,
                        'name' => $first_child->name,
                        'slug' => $first_child->slug,
                    );
                }
            } else {
                $first_child = Locations::where(array(
                    'account_id' => $account_id,
                    'region_id' => $region->id,
                    'slug' => 'region',
                ))->select('id', 'name', 'slug')->first();

                if ($first_child) {
                    $dropdown_array[$region->id]['children'][$first_child->id] = array(
                        'id' => $first_child->id,
                        'name' => $first_child->name,
                        'slug' => $first_child->slug,
                    );
                }
            }

            $other_childrens = Locations::where(array(
                'account_id' => $account_id,
                'region_id' => $region->id,
                'slug' => 'custom',
            ))->orderBy('name', 'asc')->get();

            if ($other_childrens) {
                foreach ($other_childrens as $other_children) {
                    $dropdown_array[$region->id]['children'][$other_children->id] = array(
                        'id' => $other_children->id,
                        'name' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $other_children->full_address,
                        'slug' => $other_children->slug,
                    );
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
    static function loadPlanDsicountByLocationService($location_id, $service_id, $account_id)
    {
        $searchServices = Services::where(array(
            'account_id' => $account_id,
        ))->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id');

        if ($searchServices->count()) {
            $searchServices = $searchServices->toArray();
        }

        /*
         * Case 1: Find those discounts which are All centre based
         */
        $discount_array = array();

        // 1. Find All Centres
        $rootlocation = DiscountHasLocations::where([
            'location_id' => Locations::where(array(
                'slug' => 'all',
                'account_id' => $account_id
            ))->select('id')->first()->id,
        ])->get();

        if ($rootlocation->count()) {
            //      Find All Services
            $rootdiscounts = DiscountHasLocations::where([
                'service_id' => Services::where(array(
                    'slug' => 'all',
                    'account_id' => $account_id
                ))->select('id')->first()->id,
                'location_id' => Locations::where(array(
                    'slug' => 'all',
                    'account_id' => $account_id
                ))->select('id')->first()->id,
            ])->get();

            if ($rootdiscounts->count()) {
                foreach ($rootdiscounts as $rootdiscount) {
                    if (!in_array($rootdiscount->discount_id, $discount_array)) {
                        $discount_array[] = $rootdiscount->discount_id;
                    }
                }
            }

            //      Find Matching Services
            $serviceWithParents = LocationsWidget::findServiceParents($service_id, $searchServices);
            $serviceWithParents = array_merge($serviceWithParents, [$service_id]);

            $servicediscounts = DiscountHasLocations::where([
                'location_id' => Locations::where(array(
                    'slug' => 'all',
                    'account_id' => $account_id
                ))->select('id')->first()->id,
            ])
                ->whereIn('service_id', $serviceWithParents)
                ->get();

            if ($servicediscounts->count()) {
                foreach ($servicediscounts as $servicediscount) {
                    if (!in_array($servicediscount->discount_id, $discount_array)) {
                        $discount_array[] = $servicediscount->discount_id;
                    }
                }
            }
        }

        /*
         * Case 2: Find those discounts which are region based
        */
        // 2. Find All Regions
        $singleLocation = Locations::find($location_id);
        $regionlocation = DiscountHasLocations::where([
            'location_id' => Locations::where(array(
                'slug' => 'region',
                'account_id' => $account_id,
                'region_id' => $singleLocation->region_id,
            ))->select('id')->first()->id,
        ])->get();

        if ($regionlocation->count()) {
            //      Find All Services
            $regiondiscounts = DiscountHasLocations::where([
                'service_id' => Services::where(array(
                    'slug' => 'all',
                    'account_id' => $account_id
                ))->select('id')->first()->id,
                'location_id' => Locations::where(array(
                    'slug' => 'region',
                    'account_id' => $account_id,
                    'region_id' => $singleLocation->region_id,
                ))->select('id')->first()->id,
            ])->get();

            if ($regiondiscounts->count()) {
                foreach ($regiondiscounts as $regiondiscount) {
                    if (!in_array($regiondiscount->discount_id, $discount_array)) {
                        $discount_array[] = $regiondiscount->discount_id;
                    }
                }
            }

            //      Find Matching Services
            $serviceWithParents = LocationsWidget::findServiceParents($service_id, $searchServices);
            $serviceWithParents = array_merge($serviceWithParents, [$service_id]);

            $servicediscounts = DiscountHasLocations::where([
                'location_id' => Locations::where(array(
                    'slug' => 'region',
                    'account_id' => $account_id,
                    'region_id' => $singleLocation->region_id,
                ))->select('id')->first()->id,
            ])
                ->whereIn('service_id', $serviceWithParents)
                ->get();

            if ($servicediscounts->count()) {
                foreach ($servicediscounts as $servicediscount) {
                    if (!in_array($servicediscount->discount_id, $discount_array)) {
                        $discount_array[] = $servicediscount->discount_id;
                    }
                }
            }
        }

        /*
         * Case 3: Find those discounts which single centre based
        */

        // Find All Services
        $centrediscounts = DiscountHasLocations::where([
            'service_id' => Services::where(array(
                'slug' => 'all',
                'account_id' => $account_id
            ))->select('id')->first()->id,
            'location_id' => $location_id,
        ])->get();
        if ($centrediscounts->count()) {
            foreach ($centrediscounts as $centrediscount) {
                if (!in_array($centrediscount->discount_id, $discount_array)) {
                    $discount_array[] = $centrediscount->discount_id;
                }
            }
        }
        //      Find Matching Services
        $serviceWithParents = LocationsWidget::findServiceParents($service_id, $searchServices);
        $serviceWithParents = array_merge($serviceWithParents, [$service_id]);

        $centreservicediscounts = DiscountHasLocations::where([
            'location_id' => $location_id,
        ])
            ->whereIn('service_id', $serviceWithParents)
            ->get();

        if ($centreservicediscounts->count()) {
            foreach ($centreservicediscounts as $centreservicediscount) {
                if (!in_array($centreservicediscount->discount_id, $discount_array)) {
                    $discount_array[] = $centreservicediscount->discount_id;
                }
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

    static function Discount_data_consultancy($appointment,$account_id){

        $discountIds = self::loadPlanDsicountByLocationService($appointment->location_id,$appointment->service_id,$account_id);
        $today = Carbon::now()->toDateString();
        $discounts = Discounts::whereIn('id', $discountIds)->where([
            ['discount_type', '=', 'Consultancy'],
            ['active','=','1']
        ])->whereDate('start','<=',$today)->whereDate('end','>=',$today)->get();
        /*Now Checked Brithday promotion valid or not*/
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

                $patient_info = User::find($appointment->patient_id);

                /*Now checked birthday valid or not*/
                if ($patient_info->dob) {

                    $patientbirthday = Carbon::parse($patient_info->dob)->format($today_3->year . '-' . 'm-d');

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