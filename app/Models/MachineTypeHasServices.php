<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineTypeHasServices extends Model
{
    protected $fillable = ['machine_type_id ', 'service_id'];

    protected static $_fillable = ['machine_type_id ', 'service_id'];

    protected $table = 'machine_type_has_services';

    protected static $_table = 'machine_type_has_services';

    public $timestamps = false;

    /**
     * Create Record
     *
     * @param  \Illuminate\Http\Request  $request ,$parent_data
     * @return (mixed)
     */
    public static function createRecord($data, $parent_data)
    {
        $record = self::insert($data);

        $parent_id = $parent_data->id;

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record, $parent_id);

        return $record;
    }

    /**
     * update Record
     *
     * @param  \Illuminate\Http\Request  $request ,$parent_data
     * @return (mixed)
     */
    public static function updateRecord($data, $parent_data)
    {
        $record = self::insert($data);

        $parent_id = $parent_data->id;

        $old_data = '0';

        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $record, $parent_id);

        return $record;
    }
}
