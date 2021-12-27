<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BundleHasServices extends Model
{

    protected $fillable = ['bundle_id', 'service_id', 'service_price', 'calculated_price', 'end_node'];

    protected static $_fillable = ['bundle_id', 'service_id', 'service_price', 'end_node'];

    protected $table = 'bundle_has_services';

    protected static $_table = 'bundle_has_services';

    public $timestamps = false;

    /**
     * Get Bundle Service belong to Service.
     */
    public function service()
    {
        return $this->belongsTo('App\Models\Services', 'service_id');
    }

    /**
     * Get Bundle Service belong to Bundle.
     */
    public function bundle()
    {
        return $this->belongsTo('App\Models\Bundles', 'bundle_id');
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
