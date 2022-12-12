<?php

namespace App\Models;

class HeavyLifter extends BaseModal
{
    protected $fillable = ['payload', 'type', 'reserved_at', 'available_at', 'created_at', 'updated_at', 'account_id'];

    protected $table = 'heavy_lifters';
}
