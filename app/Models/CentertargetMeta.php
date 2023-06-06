<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CentertargetMeta extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_id', 'month', 'year', 'location_id', 'target_amount', 'centertarget_id', 'created_at', 'updated_at', 'deleted_at',
    ];

    protected static $_fillable = [
        'account_id', 'month', 'year', 'location_id', 'target_amount', 'centertarget_id',
    ];

    protected $table = 'centretargetmeta';

    protected static $_table = 'centretargetmeta';

    /**
     * Get the doctors for staff_target.
     */
    public function location()
    {
        return $this->belongsTo('App\Models\Locations', 'location_id');
    }

    /*
     * Create Meta data in center target
     */
    public static function createRecord($key, $amount, $account_id, $record)
    {
        $parent_id = $record->id;
        // Set Account ID
        $data['account_id'] = $account_id;
        $data['month'] = $record->month;
        $data['year'] = $record->year;
        $data['location_id'] = $key;
        if ($amount) {
            $data['target_amount'] = $amount;
        } else {
            $data['target_amount'] = 0;
        }
        $data['centertarget_id'] = $record->id;

        $record = self::create($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record, $parent_id);

        return $record;
    }

    /*
     * Update meta data in centre
     */
    public static function updateRecord($key, $amount, $account_id, $record_parent)
    {
        $old_data = [];
        $parent_id = $record_parent->id;
        $new_result = (self::where([
            ['location_id', '=', $key],
            ['centertarget_id', '=', $record_parent->id],
        ]))->first();

        if ($new_result) {
            $old_data = $new_result->toArray();
        }

        $record = self::where([
            ['location_id', '=', $key],
            ['centertarget_id', '=', $record_parent->id],
        ])->first();

        if (! $record) {
            return false;
        }

        // Set Account ID
        $data['account_id'] = $account_id;
        $data['month'] = $record_parent->month;
        $data['year'] = $record_parent->year;
        $data['location_id'] = $key;
        if ($amount) {
            $data['target_amount'] = $amount;
        } else {
            $data['target_amount'] = 0;
        }
        $data['centertarget_id'] = $record_parent->id;

        $record = $record->update($data);

        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $data, $parent_id);

        return $record;

    }
}
