<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class BaseModal extends Model
{
    /**
     * Get Data
     *
     * @param  (int)  $id
     * @return (mixed)
     */
    public static function getData($id)
    {
        return self::where([
            ['id', '=', $id],
            ['account_id', '=', Auth::user()->account_id],
        ])->first();
    }

    /*
     * Get Bulk Data
     *
     * @param (int)|(array) $id
     *
     * @return (mixed)
     */
    public static function getBulkData($id)
    {
        if (! is_array($id)) {
            $id = [$id];
        }

        return self::where([
            ['account_id', '=', Auth::User()->account_id],
        ])->whereIn('id', $id)
            ->get();
    }

    /*
     * Get Bulk Data for appointment images
     *
     * @param (int)|(array) $id
     *
     * @return (mixed)
     */
    public static function getBulkData_forimage($id)
    {
        if (! is_array($id)) {
            $id = [$id];
        }

        return self::whereIn('id', $id)->get();
    }

    public function dateFormat($date, $format = 'Y-m-d')
    {
        return date($format, strtotime($date));
    }
}
