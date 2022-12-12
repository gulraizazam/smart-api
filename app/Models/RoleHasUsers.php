<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\AuditTrails;
use Auth;

class RoleHasUsers extends Model
{
    use SoftDeletes;

    protected $fillable = ['role_id', 'user_id'];

    protected static $_fillable = ['role_id', 'user_id'];

    protected $table = 'role_has_users';

    protected static $_table = 'role_has_users';

    public $timestamps = false;

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

        $parent_id = $parent_data->id;

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
