<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserHasLocations extends Model
{
    protected $fillable = ['user_id', 'region_id', 'location_id'];

    protected static array $_fillable = ['user_id', 'region_id', 'location_id'];

    protected $table = 'user_has_locations';

    protected static string $_table = 'user_has_locations';

    public $timestamps = false;

    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function createRecord(array $data, mixed $parentData): bool
    {
        $record = self::insert($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record, $parentData);

        return $record;
    }

    public static function updateRecord(array $data, mixed $parentData): bool
    {
        $record = self::insert($data);

        $parentId = is_object($parentData) ? $parentData->id : $parentData;
        $oldData = '0';

        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $oldData, $record, $parentId);

        return $record;
    }
}
