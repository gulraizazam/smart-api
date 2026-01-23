<?php

/**
 * Created by PhpStorm.
 * User: REDSignal
 * Date: 3/22/2018
 * Time: 3:49 PM
 */

namespace App\Helpers;

use Auth;
use Config;
use App\Models\User;
use App\Models\Cities;
use App\Models\Regions;
use App\Models\Locations;
use App\Models\Warehouse;
use App\Models\DoctorHasLocations;
use Illuminate\Support\Facades\Auth as FacadesAuth;

class ACL
{
    /*
     * function to provide User has centres
     * @param: (void)
     * @return: (array)
     */
    public static function getUserCentres()
    {
        // OPTIMIZED: Cache result in static variable to avoid repeated queries in same request
        static $cachedLocations = [];
        $userId = Auth::id();
        
        if (isset($cachedLocations[$userId])) {
            return $cachedLocations[$userId];
        }
        
        if (Auth::user()->id == 1) {
            $locations = Locations::whereActive(1)->where('name', '!=', 'All Centres')->get()->pluck('id');
        } else {
            if (Auth::user()->user_type_id == Config::get('constants.practitioner_id')) {
                $locations = DoctorHasLocations::where('user_id', '=', Auth::user()->id)->where('is_allocated',1)->groupBy('location_id')->get()->pluck('location_id');
            } else {
                $locations = Auth::user()->user_has_locations()->pluck('location_id');
            }
        }
        
        $result = $locations ? $locations->toArray() : [];
        $cachedLocations[$userId] = $result;
        
        return $result;
    }

    public static function getUserWarehouse()
    {
        $locations = Auth::user()->user_has_warehouse()->pluck('warehouse_id');
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
        // OPTIMIZED: Cache result in static variable to avoid repeated queries in same request
        static $cachedRegions = [];
        $userId = Auth::id();
        
        if (isset($cachedRegions[$userId])) {
            return $cachedRegions[$userId];
        }
        
        if (Auth::user()->id == 1) {
            $regions = Regions::where('account_id', '=', Auth::User()->account_id)->pluck('id');
        } else {
            $regions = Regions::whereIn('id', Cities::getActiveOnly(ACL::getUserCities(), Auth::User()->account_id)->pluck('region_id'))
                ->where('account_id', '=', Auth::User()->account_id)
                ->get()->pluck('id');
        }

        $result = $regions ? $regions->toArray() : [];
        $cachedRegions[$userId] = $result;
        
        return $result;
    }

    /*
     * function to provide User has location cities
     * @param: (void)
     * @return: (array)
     */
    public static function getUserCities()
    {
        // OPTIMIZED: Cache result in static variable to avoid repeated queries in same request
        static $cachedCities = [];
        $userId = Auth::id();
        
        if (isset($cachedCities[$userId])) {
            return $cachedCities[$userId];
        }
        
        if (Auth::user()->id == 1) {
            $cities = Cities::where('account_id', '=', Auth::User()->account_id)->pluck('id');
        } else {
            if (Auth::user()->user_type_id == Config::get('constants.practitioner_id')) {
                $cities = Locations::whereIn('id', DoctorHasLocations::where('user_id', '=', Auth::user()->id)->where('is_allocated',1)->groupBy('location_id')->get()->pluck('location_id'))
                    ->where('account_id', '=', Auth::User()->account_id)
                    ->get()->pluck('city_id');
            } else {
                $cities = Locations::whereIn('id', Auth::user()->user_has_locations()->pluck('location_id'))
                    ->where('account_id', '=', Auth::User()->account_id)
                    ->get()->pluck('city_id');
            }
        }

        $result = $cities ? $cities->toArray() : [];
        $cachedCities[$userId] = $result;
        
        return $result;
    }
}
