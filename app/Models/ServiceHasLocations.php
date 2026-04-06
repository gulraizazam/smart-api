<?php

declare(strict_types=1);
namespace App\Models;

class ServiceHasLocations extends BaseModel
{
    protected $fillable = ['service_id', 'location_id', 'account_id'];

    protected static array $_fillable = ['service_id', 'location_id'];

    protected $table = 'service_has_locations';

    protected static string $_table = 'service_has_locations';

    public $timestamps = false;

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
        return $this->belongsTo(Locations::class, 'service_id');
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
