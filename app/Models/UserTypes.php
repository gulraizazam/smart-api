<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class UserTypes extends BaseModal
{
    use SoftDeletes;

    protected $table = 'user_types';

    protected $fillable = [
        'name',
        'type',
        'account_id',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Users belonging to this user type
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'user_type_id');
    }

    /**
     * Scope: Filter by account
     */
    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope: Filter active records
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', 1);
    }

    /**
     * Scope: Filter by type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Exclude administrator
     */
    public function scopeExcludeAdmin(Builder $query): Builder
    {
        return $query->where('name', '!=', 'Administrator');
    }

    /**
     * Scope: Search by name
     */
    public function scopeSearchByName(Builder $query, ?string $name): Builder
    {
        if ($name) {
            return $query->where('name', 'like', "%{$name}%");
        }
        return $query;
    }

    /**
     * Check if this user type has associated users
     */
    public function hasUsers(): bool
    {
        return $this->users()->exists();
    }
}
