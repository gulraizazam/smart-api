<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Permission extends \Spatie\Permission\Models\Permission
{
    use HasFactory;

    protected $fillable = [
        'name',
        'title',
        'main_group',
        'parent_id',
        'status',
        'guard_name',
        'sort_order',
    ];

    protected $casts = [
        'main_group' => 'boolean',
        'status' => 'boolean',
        'parent_id' => 'integer',
    ];

    /**
     * Get the parent permission
     */
    public function parent()
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * Get child permissions
     */
    public function children()
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    /**
     * Scope for parent groups only
     */
    public function scopeParentGroups($query)
    {
        return $query->where('main_group', 1);
    }

    /**
     * Scope for active permissions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
