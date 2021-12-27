<?php

namespace App\Models;

use App\Models\AuditTrails;
use Auth;

class ServiceHasLocations extends BaseModal
{

    protected $fillable = ['service_id', 'location_id', 'account_id'];

    protected static $_fillable = ['service_id', 'location_id'];

    protected $table = 'service_has_locations';

    protected static $_table = 'service_has_locations';

    public $timestamps = false;

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
        return $this->belongsTo('App\Models\Locations', 'service_id');
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
    static public function createRecord($data, $parent_data)
    {
        $record = self::insert($data);

        $parent_id = $parent_data->id;

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
