<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserHasLocations extends Model
{

    protected $fillable = ['user_id', 'region_id', 'location_id'];

    protected static $_fillable = ['user_id', 'region_id', 'location_id'];

    protected $table = 'user_has_locations';

    protected static $_table = 'user_has_locations';

    public $timestamps = false;

    /**
     * Get User locations belong to location.
     */
    public function location()
    {
        return $this->belongsTo('App\Models\Locations', 'location_id');
    }

    /**
     * Get User locations belong to user.
     */
    public function user()
    {
        return $this->belongsTo('App\Models\Locations', 'user_id');
    }

    /**
     * Create Record
     *
     * @param data
     *
     * @return (mixed)
     */
    static public function createRecord($data, $parent_data)
    {

        $record = self::insert($data);

        $parent_id = $parent_data;

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record, $parent_id);

        return $record;
    }

    /**
     * update Record
     *
     * @param data ,parent_data
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
