<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GetDiscountService extends Model
{
    protected $table = 'get_discount_services';

    protected $fillable = [
        'discount_id',
        'service_id',
        'service_price',
        'base_service_id',
        'sessions',
        'discount_type',
        'discount_amount',
        'same_service',
        'bundle_id',
    ];

    protected function casts(): array
    {
        return [
            'service_price'   => 'decimal:2',
            'sessions'        => 'integer',
            'discount_amount' => 'decimal:2',
            'same_service'    => 'boolean',
        ];
    }

    // ── Relationships ────────────────────────────────────

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class, 'discount_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id');
    }

    public function baseService(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'base_service_id');
    }
}
