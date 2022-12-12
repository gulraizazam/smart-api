<?php

namespace App\Models;

use App\Models\AuditTrails;
use Auth;

class StaffTargetServices extends BaseModal
{

    protected $fillable = [
        'account_id', 'location_id', 'staff_target_id', 'service_id',
        'target_amount', 'target_services', 'month', 'year', 'created_at', 'updated_at', 'deleted_at'
    ];

    protected static $_fillable = [
        'account_id', 'location_id', 'staff_target_id', 'service_id',
        'target_amount', 'target_services', 'month', 'year'
    ];

    protected $table = 'staff_target_services';

    protected static $_table = 'staff_target_services';

    /**
     * Get Service locations belong to location.
     */
    public function location()
    {
        return $this->belongsTo('App\Models\Locations', 'location_id');
    }

    /**
     * Get Service Locations belong to user.
     */
    public function service()
    {
        return $this->belongsTo('App\Models\Services', 'service_id');
    }

    /**
     * Get Service Locations belong to user.
     */
    public function account()
    {
        return $this->belongsTo('App\Models\Accounts', 'account_id');
    }

    /**
     * Create Record
     *
     * @param \Illuminate\Http\Request $request ,$parent_data
     *
     * @return (mixed)
     */
    static public function createRecord($data, $parent_id)
    {
        $record = self::insert($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record, $parent_id);

        return $record;
    }

    /**
     * update Record
     *
     * @param \Illuminate\Http\Request $request ,$parent_data
     *
     * @return (mixed)
     */
    static public function updateRecord($data, $parent_data)
    {
        $record = self::insert($data);

        $parent_id = $parent_data->id;

        $old_data = '0';

        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $record, $parent_id);

        return $record;
    }
}
