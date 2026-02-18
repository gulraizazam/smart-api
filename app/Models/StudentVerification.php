<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentVerification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'membership_id',
        'membership_type_id',
        'package_id',
        'document_paths',
        'status',
        'rejection_reason',
        'submitted_by',
        'reviewed_by',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'document_paths' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // Relationships
    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function membershipType()
    {
        return $this->belongsTo(MembershipType::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function package()
    {
        return $this->belongsTo(Packages::class, 'package_id');
    }
}
