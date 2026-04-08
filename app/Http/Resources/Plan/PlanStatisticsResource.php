<?php

declare(strict_types=1);

namespace App\Http\Resources\Plan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms plan statistics data for API response.
 */
final class PlanStatisticsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'total_plans'    => (int) ($this['total_plans'] ?? 0),
            'active_plans'   => (int) ($this['active_plans'] ?? 0),
            'total_amount'   => number_format((float) ($this['total_amount'] ?? 0), 2),
            'cash_received'  => number_format((float) ($this['cash_received'] ?? 0), 2),
            'refunded_plans' => (int) ($this['refunded_plans'] ?? 0),
        ];
    }
}
