<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Pre-aggregated daily metric row. Read-only from controllers / services;
 * writes go through App\Services\Dashboard\Rollup\DailyMetricsBuilder.
 */
class DashboardDailyMetric extends BaseModel
{
    protected $table = 'dashboard_daily_metrics';

    protected $fillable = [
        'account_id',
        'date',
        'branch_id',
        'resource_id',
        'resource_type',
        'metric_key',
        'value',
        'target_value',
        'dimensions',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'value' => 'decimal:2',
        'target_value' => 'decimal:2',
        'dimensions' => 'array',
    ];
}
