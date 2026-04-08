<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaseDiscountService extends Model
{
    protected $table = 'base_discount_services';

    protected $fillable = [
        'discount_id',
        'service_id',
        'service_price',
        'sessions',
        'is_category',
        'bundle_id',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'service_price' => 'decimal:2',
            'sessions'      => 'integer',
            'is_category'   => 'boolean',
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
}
