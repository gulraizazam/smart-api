<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public function scopeParentGroups(Builder $query): Builder
    {
        return $query->where('main_group', 1);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function scopeChildrenOf(Builder $query, int $parentId): Builder
    {
        return $query->where('parent_id', $parentId);
    }

    public function isParentGroup(): bool
    {
        return (bool) $this->main_group;
    }
}
