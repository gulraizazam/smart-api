<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class MembershipType extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'status', 'period', 'amount', 'created_by', 'updated_by', 'deleted_by', 'active', 'parent_id'];
    protected $table = 'membership_types';

    /**
     * Get the parent membership type (for renewals)
     */
    public function parent()
    {
        return $this->belongsTo(MembershipType::class, 'parent_id');
    }

    /**
     * Get the child membership types (renewals)
     */
    public function children()
    {
        return $this->hasMany(MembershipType::class, 'parent_id');
    }

    /**
     * Check if this is a renewal (has a parent)
     */
    public function isRenewal(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Scope to get only parent membership types (not renewals)
     */
    public function scopeParentsOnly($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get only renewals
     */
    public function scopeRenewalsOnly($query)
    {
        return $query->whereNotNull('parent_id');
    }

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

    /**
     * Get the discounts associated with this membership type
     */
    public function discounts()
    {
        return $this->belongsToMany(Discounts::class, 'membership_type_has_discounts', 'membership_type_id', 'discount_id')
            ->withTimestamps();
    }
}
