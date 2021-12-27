<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Auth;
class BaseModal extends Model
{

    /**
     * Get Data
     *
     * @param (int) $id
     *
     * @return (mixed)
     */
    static public function getData($id) {

        return self::where([
            ['id','=',$id],
            ['account_id','=',Auth::User()->account_id]
        ])->first();
    }

    /*
     * Get Bulk Data
     *
     * @param (int)|(array) $id
     *
     * @return (mixed)
     */
    static public function getBulkData($id) {
        if(!is_array($id)) {
            $id = array($id);
        }
        return self::where([
            ['account_id','=',Auth::User()->account_id]
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
    static public function getBulkData_forimage($id)
    {
        if (!is_array($id)) {
            $id = array($id);
        }
        return self::whereIn('id', $id)->get();
    }

}
