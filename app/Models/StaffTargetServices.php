<?php

declare(strict_types=1);
namespace App\Models;

class StaffTargetServices extends BaseModel
{
    protected $fillable = [
        'account_id', 'location_id', 'staff_target_id', 'service_id',
        'target_amount', 'target_services', 'month', 'year', 'created_at', 'updated_at', 'deleted_at',
    ];

    protected static array $_fillable = [
        'account_id', 'location_id', 'staff_target_id', 'service_id',
        'target_amount', 'target_services', 'month', 'year',
    ];

    protected $table = 'staff_target_services';

    protected static string $_table = 'staff_target_services';

    /**
     * Get Service locations belong to location.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id');
    }

    /**
     * Get Service Locations belong to user.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id');
    }

    /**
     * Get Service Locations belong to user.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Accounts::class, 'account_id');
    }

    /**
     * Create Record
     *
     * @param  \Illuminate\Http\Request  $request ,$parent_data
     * @return (mixed)
     */
    public static function createRecord($data, $parent_id)
    {
        $record = self::insert($data);

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
