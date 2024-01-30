<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class MembershipType extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'status', 'period', 'amount', 'created_by', 'updated_by', 'deleted_by', 'active'];
    protected $table = 'membership_types';

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
}
