<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role;

class Discount extends BaseModel
{
    use SoftDeletes;

    protected $table = 'discounts';

    protected $fillable = [
        'name',
        'type',
        'amount',
        'discount_type',
        'pre_days',
        'post_days',
        'start',
        'end',
        'active',
        'slug',
        'account_id',
        'customer_type_id',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'amount'     => 'decimal:2',
            'pre_days'   => 'integer',
            'post_days'  => 'integer',
            'active'     => 'boolean',
            'created_at' => 'datetime:F d,Y h:i A',
        ];
    }

    // ── Accessors / Mutators ─────────────────────────────

    protected function start(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value ? $this->dateFormat($value, 'F d,Y') : null,
            set: fn (string $value): string => $this->dateFormat($value),
        );
    }

    protected function end(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value ? $this->dateFormat($value, 'F d,Y') : null,
            set: fn (string $value): string => $this->dateFormat($value),
        );
    }

    // ── Relationships ────────────────────────────────────

    public function allocations(): HasMany
    {
        return $this->hasMany(DiscountHasLocations::class, 'discount_id');
    }

    public function baseServices(): HasMany
    {
        return $this->hasMany(BaseDiscountService::class, 'discount_id');
    }

    public function getServices(): HasMany
    {
        return $this->hasMany(GetDiscountService::class, 'discount_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'discount_role', 'discount_id', 'role_id');
    }

    public function packageBundles(): HasMany
    {
        return $this->hasMany(PackageBundles::class, 'discount_id');
    }

    public function membershipTypes(): BelongsToMany
    {
        return $this->belongsToMany(MembershipType::class, 'membership_type_has_discounts', 'discount_id', 'membership_type_id');
    }

    // ── Scopes ───────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeExcludeVouchers(Builder $query): Builder
    {
        return $query->where('discount_type', '!=', 'voucher');
    }

    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeCurrentlyValid(Builder $query): Builder
    {
        $now = now();
        return $query->where('start', '<=', $now)->where('end', '>=', $now);
    }

    // ── Helpers ──────────────────────────────────────────

    public function isConfigurable(): bool
    {
        return $this->type === 'Configurable';
    }

    public function hasChildren(): bool
    {
        return $this->allocations()->exists() || $this->packageBundles()->exists();
    }
}
