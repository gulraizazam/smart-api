<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserHasWarehouse extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'warehouse_id'];

    public static function createRecord($data, $parent_data)
    {
        $record = self::insert($data);

        return $record;
    }

    public static function updateRecord($data, $parent_data)
    {
        $record = self::insert($data);

        return $record;
    }
}
