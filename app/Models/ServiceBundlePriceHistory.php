<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBundlePriceHistory extends BaseModel
{
    protected $table = 'service_bundle_price_history';

    public $timestamps = false;

    protected $fillable = [
        'service_bundle_id',
        'service_id',
        'sessions',
        'discount_percentage',
        'old_service_price',
        'new_service_price',
        'old_bundle_price',
        'new_bundle_price',
        'changed_by',
        'account_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'service_bundle_id' => 'integer',
            'service_id' => 'integer',
            'sessions' => 'integer',
            'discount_percentage' => 'float',
            'old_service_price' => 'float',
            'new_service_price' => 'float',
            'old_bundle_price' => 'float',
            'new_bundle_price' => 'float',
            'changed_by' => 'integer',
            'account_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function serviceBundle(): BelongsTo
    {
        return $this->belongsTo(ServiceBundle::class, 'service_bundle_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id');
    }
}
