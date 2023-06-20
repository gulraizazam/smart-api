<?php
/**
 * Created by PhpStorm.
 * User: REDSignal
 * Date: 3/22/2018
 * Time: 3:49 PM
 */

namespace App\Helpers;

use App\Models\Cities;
use App\Models\DoctorHasLocations;
use App\Models\Locations;
use App\Models\Regions;
use App\Models\User;
use Auth;
use Config;

class ACL
{
    /*
     * function to provide User has centres
     * @param: (void)
     * @return: (array)
     */
    public static function getUserCentres()
    {
        if (Auth::user()->id == 1) {
            $locations = Locations::whereActive(1)->get()->pluck('id');
        } else {
            if (Auth::user()->user_type_id == Config::get('constants.practitioner_id')) {
                $locations = DoctorHasLocations::where('user_id', '=', Auth::user()->id)->groupBy('location_id')->get()->pluck('location_id');
            } else {
                $locations = Auth::user()->user_has_locations()->pluck('location_id');
            }
        }
        if ($locations) {
            return $locations->toArray();
        }

        return [];
    }

    /*
     * function to provide User has regions
     * @param: (void)
     * @return: (array)
     */
    public static function getUserRegions()
    {
        if (Auth::user()->id == 1) {
            $regions = Regions::where('account_id', '=', Auth::User()->account_id)->pluck('id');
        } else {
            $regions = Regions::whereIn('id', Cities::getActiveOnly(ACL::getUserCities(), Auth::User()->account_id)->pluck('region_id'))
                ->where('account_id', '=', Auth::User()->account_id)
                ->get()->pluck('id');
        }

        if ($regions) {
            return $regions->toArray();
        }

        return [];
    }

    /*
     * function to provide User has location cities
     * @param: (void)
     * @return: (array)
     */
    public static function getUserCities()
    {
        if (Auth::user()->id == 1) {
            $cities = Cities::where('account_id', '=', Auth::User()->account_id)->pluck('id');
        } else {
            if (Auth::user()->user_type_id == Config::get('constants.practitioner_id')) {

                $cities = Locations::whereIn('id', DoctorHasLocations::where('user_id', '=', Auth::user()->id)->groupBy('location_id')->get()->pluck('location_id'))
                    ->where('account_id', '=', Auth::User()->account_id)
                    ->get()->pluck('city_id');
            } else {
                $cities = Locations::whereIn('id', Auth::user()->user_has_locations()->pluck('location_id'))
                    ->where('account_id', '=', Auth::User()->account_id)
                    ->get()->pluck('city_id');
            }

        }

        if ($cities) {
            return $cities->toArray();
        }

        return [];
    }
}
