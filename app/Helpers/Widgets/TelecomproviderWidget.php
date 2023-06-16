<?php
/**
 * Created by PhpStorm.
 * User: REDSignal
 * Date: 3/22/2018
 * Time: 3:49 PM
 */

namespace App\Helpers\Widgets;

use App\Models\Telecomprovider;
use App\Models\Telecomprovidernumber;

class TelecomproviderWidget
{
    /*
     * Make drop down for telecomprovider
     * @return: (mixed) $result
     */
    public static function telecomprovider()
    {
        $telecomproviders = Telecomprovider::get();

        $sim_provider = [];

        foreach ($telecomproviders as $telecomprovider) {
            $sim_provider[$telecomprovider->id] = [
                'id' => $telecomprovider->id,
                'name' => $telecomprovider->name,
                'children' => [],
            ];

            $other_child = Telecomprovidernumber::where([
                'telecomprovider_id' => $telecomprovider->id,
            ])->select('id', 'pre_fix')->get();

            if ($other_child) {
                foreach ($other_child as $other_child) {
                    $sim_provider[$telecomprovider->id]['children'][$other_child->id] = [
                        'id' => $other_child->id,
                        'pre_fix' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'.$other_child->pre_fix,
                    ];
                }
            }
        }

        return $sim_provider;
    }
}
