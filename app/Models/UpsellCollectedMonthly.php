<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Pre-aggregated monthly "upsell collected" rollup row — the per-doctor cash
 * basis for monthly upsell incentives. Read-only from controllers / services;
 * writes go through App\Services\Upselling\Rollup\UpsellCollectedBuilder.
 * Mirrors App\Models\DashboardDailyMetric.
 */
class UpsellCollectedMonthly extends BaseModel
{
    protected $table = 'upsell_collected_monthly';

    protected $fillable = [
        'account_id',
        'month',
        'doctor_id',
        'location_id',
        'collected_amount',
        'is_closed',
        'closed_at',
    ];

    protected $casts = [
        'month' => 'date:Y-m-d',
        'collected_amount' => 'decimal:2',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
    ];
}
