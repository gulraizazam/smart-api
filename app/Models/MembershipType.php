<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipType extends Model
{
    use HasFactory;

    protected $table = 'membership_types';

    protected $fillable = [
        'name',
        'status',
        'period',
        'amount',
        'created_by',
        'updated_by',
        'deleted_by',
        'active',
        'parent_id',
    ];

    protected function casts(): array
    {
        return [
            'active'    => 'boolean',
            'period'    => 'integer',
            'amount'    => 'decimal:2',
            'parent_id' => 'integer',
        ];
    }

    // ── Relationships ────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'membership_type_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function discounts(): BelongsToMany
    {
        return $this->belongsToMany(Discounts::class, 'membership_type_has_discounts', 'membership_type_id', 'discount_id')
            ->withTimestamps();
    }

    // ── Scopes ───────────────────────────────────────────

    public function scopeParentsOnly(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeRenewalsOnly(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    // ── Helpers ──────────────────────────────────────────

    public function isRenewal(): bool
    {
        return $this->parent_id !== null;
    }
}
