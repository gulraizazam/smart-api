<?php
/**
 * Created by PhpStorm.
 * User: shehbaz@redsignal@biz
 * Date: 6/27/18
 * Time: 4:13 PM
 */


namespace App\Helpers;

use Illuminate\Http\Request;

class Explode_Multi_select
{
    public static function explode($locationids){

        $data = explode(",",$locationids);
        return $data;
    }
}
