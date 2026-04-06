<?php

declare(strict_types=1);
namespace App\Models;

class HeavyLifter extends BaseModel
{
    protected $fillable = ['payload', 'type', 'reserved_at', 'available_at', 'created_at', 'updated_at', 'account_id'];

    protected $table = 'heavy_lifters';
}
