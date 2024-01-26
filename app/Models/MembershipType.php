<?php

namespace App\Models;

use App\Helpers\ACL;
use App\Helpers\Filters;
use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class MembershipType extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = ['name', 'status','period', 'created_by', 'updated_by', 'deleted_by','active'];
    protected $table = 'membership_types';

    public function createdBy()
    {
        return $this->belongsTo(User::class,'created_by');
    }
    public function updatedBy()
    {
        return $this->belongsTo(User::class,'updated_by');
    }
    public function deletedBy()
    {
        return $this->belongsTo(User::class,'deleted_by');
    }
    
}
