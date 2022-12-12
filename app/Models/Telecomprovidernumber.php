<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use App\Models\AuditTrails;
use Auth;

class Telecomprovidernumber extends Model
{
    use SoftDeletes;

    protected $fillable = ['pre_fix', 'active', 'telecomprovider_id', 'created_at', 'updated_at','deleted_at'];

    protected static $_fillable = ['pre_fix', 'active', 'telecomprovider_id'];

    protected $table = 'telecomprovidernumbers';

    protected static $_table = 'telecomprovidernumbers';
}
