<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountHasLocations extends Model
{
    protected $table = 'discount_has_locations';

    protected $fillable = [
        'discount_id',
        'location_id',
        'service_id',
        'type',
        'amount',
        'slug',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    // ── Relationships ────────────────────────────────────

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class, 'discount_id')->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id')->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id')->withTrashed();
    }
}
