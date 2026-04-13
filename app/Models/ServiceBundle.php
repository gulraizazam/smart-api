<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceBundle extends BaseModel
{
    use SoftDeletes;

    protected $table = 'service_bundles';

    protected $fillable = [
        'service_id',
        'sessions',
        'price',
        'discount_percentage',
        'sort_number',
        'active',
        'account_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'service_id'          => 'integer',
            'sessions'            => 'integer',
            'price'               => 'float',
            'discount_percentage' => 'float',
            'sort_number'         => 'integer',
            'active'              => 'integer',
            'account_id'          => 'integer',
            'created_by'          => 'integer',
            'updated_by'          => 'integer',
        ];
    }

    // ── Relationships ───────────────────────────────────

    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id');
    }

    // ── Scopes ──────────────────────────────────────────

    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeIsActive(Builder $query): Builder
    {
        return $query->where('active', 1);
    }
}
