<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membership extends Model
{
    use HasFactory;

    protected $table = 'memberships';

    protected $fillable = [
        'code',
        'membership_type_id',
        'start_date',
        'end_date',
        'patient_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'active',
        'assigned_at',
        'is_referral',
        'parent_membership_code',
    ];

    protected function casts(): array
    {
        return [
            'active'             => 'boolean',
            'is_referral'        => 'boolean',
            'membership_type_id' => 'integer',
            'patient_id'         => 'integer',
        ];
    }

    // ── Relationships ────────────────────────────────────

    public function membershipType(): BelongsTo
    {
        return $this->belongsTo(MembershipType::class, 'membership_type_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
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

    public function usedInServices(): HasMany
    {
        return $this->hasMany(PackageBundles::class, 'membership_code_id', 'id');
    }

    // ── Scopes ───────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeAssigned(Builder $query): Builder
    {
        return $query->whereNotNull('patient_id');
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('patient_id');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('end_date', '<', now()->toDateString());
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('end_date', '>=', now()->toDateString());
    }
}
