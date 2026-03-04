<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Bundles extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'price', 'services_price', 'type', 'start', 'end', 'apply_discount', 'total_services', 'active', 'tax_treatment_type_id', 'created_at', 'updated_at', 'account_id'];

    protected static $_fillable = ['name', 'price', 'services_price', 'type', 'start', 'end', 'apply_discount', 'total_services', 'active', 'tax_treatment_type_id'];

    protected $table = 'bundles';

    protected static $_table = 'bundles';

    protected $casts = [
        'created_at' => 'datetime:F d,Y h:i A',
    ];

    /**
     * sent the bundle data to resource has rota.
     */
    public function resourcehasrota()
    {
        return $this->hasMany('App\Models\ResourceHasRota', 'bundle_id');
    }

    /**
     * Get the Locations for Bundle.
     */
    public function locations()
    {
        return $this->hasMany('App\Models\Locations', 'bundle_id');
    }

    /**
     * Get the Active Locations for Bundle.
     */
    public function locationsActive()
    {
        return $this->hasMany('App\Models\Locations', 'bundle_id')->where(['active' => 1]);
    }

    /**
     * Get the doctors for Bundle.
     */
    public function doctors()
    {
        return $this->hasMany('App\Models\Doctors', 'bundle_id');
    }

    /**
     * Get the appointments for Bundle.
     */
    public function appointments()
    {
        return $this->hasMany('App\Models\Appointments', 'bundle_id');
    }

    /**
     * sent the bundle data to Package Bundle.
     */
    public function packagebundle()
    {
        return $this->hasMany('App\Models\PackageBundles', 'bundle_id');
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveSorted($bundleId = false, $get_all = false)
    {
        if ($bundleId && !is_array($bundleId)) {
            $bundleId = [$bundleId];
        }
        if ($bundleId) {
            return self::where(['active' => 1, 'type' => 'multiple'])->whereIn('id', $bundleId)->where('account_id', '=', Auth::User()->account_id)->get()->pluck('name', 'id');
        } else {
            return self::where(['active' => 1, 'type' => 'multiple'])->where('account_id', '=', Auth::User()->account_id)->pluck('name', 'id');
        }
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveOnly($bundleId = false)
    {
        if ($bundleId && !is_array($bundleId)) {
            $bundleId = [$bundleId];
        }
        $query = self::where(['active' => 1]);
        if ($bundleId) {
            $query->whereIn('id', $bundleId);
        }

        return $query->OrderBy('sort_number', 'asc')->get();
    }

    /**
     * Calculate Price based on package price
     *
     * @param  (array)  $services
     * @param  (double)  $services_price
     * @param  (double)  $price
     * @return (array) $services
     */
    public static function calculatePrices($services, $services_price, $price)
    {

        $calculated_services = [];

        /*
         * Case 1: $services_price is greater than $price
         */
        if ($services_price == $price) {
            foreach ($services as $key => $service) {
                $services[$key]['calculated_price'] = $services[$key]['service_price'];
            }
        } elseif ($services_price > $price) {

            $ratio = (1 - round((self::convertToInt($price) / self::convertToInt($services_price)), 8));

            foreach ($services as $key => $service) {
                $services[$key]['calculated_price'] = round($services[$key]['service_price'] - ($services[$key]['service_price'] * $ratio), 2);
            }
        } else {
            $ratio = -1 * (1 - round(($price / $services_price), 8));

            foreach ($services as $key => $service) {
                $services[$key]['calculated_price'] = round($services[$key]['service_price'] + ($services[$key]['service_price'] * $ratio), 2);
            }
        }

        return $services;
    }

    public static function convertToInt($val)
    {

        return (int)(str_replace(',', '', $val));
    }
}
