<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditTrailTables extends Model
{
    protected $fillable = ['name', 'screen', 'created_at', 'updated_at'];

    protected $table = 'audit_trail_tables';
}
