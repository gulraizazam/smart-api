<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppointmentTypes extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'active', 'created_at', 'updated_at','account_id'];

    protected $table = 'appointment_types';

    /*Relation for audit trail*/
    public function audit_field_before()
    {
        return $this->hasMany('App\Models\AuditTrailChanges','field_before');
    }
    public function audit_field_after()
    {
        return $this->hasMany('App\Models\AuditTrailChanges','field_after');
    }
    /*end*/

    /**
     * Get All Records
     *
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getAllRecords($account_id)
    {
        return self::where(['account_id' => $account_id])->get();
    }
}
