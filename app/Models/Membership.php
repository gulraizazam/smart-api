<?php

namespace App\Models;

use App\Helpers\Filters;
use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Membership extends Model
{
    use HasFactory;
    protected $fillable = ['code', 'membership_type_id', 'start_date', 'end_date', 'patient_id', 'created_by', 'updated_by', 'deleted_by', 'active', 'assigned_at', 'is_referral', 'parent_membership_code'];
    protected $table = 'memberships';

    protected $casts = [
        'active' => 'integer',
        'is_referral' => 'integer',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
    public function membershipType()
    {
        return $this->belongsTo(MembershipType::class, 'membership_type_id');
    }
    public function patient()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get package bundles where this membership discount was applied
     */
    public function usedInServices()
    {
        return $this->hasMany(PackageBundles::class, 'membership_code_id', 'id');
    }
}
