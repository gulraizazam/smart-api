<?php
/**
 * Created by PhpStorm.
 * User: REDSignal
 * Date: 3/22/2018
 * Time: 3:49 PM
 */

namespace App\Helpers\Widgets;

use App\Models\Cities;
use App\Models\Locations;

class RegionsWidget
{
    /*
     * function to create Heiracrchy for Region
     * @param: (mixed) $region
     * @param: (int) $account_id
     *
     * @return: (void)
     */
    static function advancedCreateRegion($record, $account_id)
    {
        // Create City
        $city = Cities::create(array(
            'slug' => 'region',
            'name' => 'All ' . $record->name,
            'region_id' => $record->id,
            'account_id' => $account_id,
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now(),
        ));
        $city->update(['sort_no' => $city->id]);

        // Create Centre
        $center = Locations::create(array(
            'slug' => 'region',
            'name' => 'All ' . $record->name,
            'address' => '',
            'google_map' => '',
            'city_id' => $city->id,
            'region_id' => $record->id,
            'account_id' => $account_id,
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now(),
        ));
        $center->update(['sort_no' => $city->id]);
    }

    /*
     * function to update Heiracrchy for Region
     * @param: (mixed) $region
     * @param: (int) $account_id
     *
     * @return: (void)
     */
    static function advancedUpdateRegion($record, $account_id)
    {
        Cities::where(array(
            'account_id' => $account_id,
            'region_id' => $record->id,
        ))->update(array(
            'name' => $record->name
        ));

        Locations::where(array(
            'account_id' => $account_id,
            'region_id' => $record->id,
        ))->update(array(
            'name' => $record->name
        ));
    }

}