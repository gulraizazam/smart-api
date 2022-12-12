<?php
/**
 * Created by PhpStorm.
 * User: REDSignal
 * Date: 3/22/2018
 * Time: 3:49 PM
 */

namespace App\Helpers\Widgets;

use App\Models\Telecomprovidernumber;
use App\Models\Telecomprovider;

class TelecomproviderWidget
{
    /*
     * Make drop down for telecomprovider
     * @return: (mixed) $result
     */
    static function telecomprovider()
    {
        $telecomproviders = Telecomprovider::get();

        $sim_provider = array();

        foreach ($telecomproviders as $telecomprovider) {
            $sim_provider[$telecomprovider->id] = array(
                'id' => $telecomprovider->id,
                'name' => $telecomprovider->name,
                'children' => array(),
            );

            $other_child = Telecomprovidernumber::where(array(
                'telecomprovider_id' => $telecomprovider->id,
            ))->select('id', 'pre_fix')->get();

            if ($other_child) {
                foreach ($other_child as $other_child) {
                    $sim_provider[$telecomprovider->id]['children'][$other_child->id] = array(
                        'id' => $other_child->id,
                        'pre_fix' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $other_child->pre_fix,
                    );
                }
            }
        }
        return $sim_provider;
    }
}