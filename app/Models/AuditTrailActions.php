<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditTrailActions extends Model
{
    protected $fillable = ['name', 'created_at', 'updated_at'];

    protected $table = 'audit_trail_actions';
}
