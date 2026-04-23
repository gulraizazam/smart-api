<?php

declare(strict_types=1);

namespace App\Http\Resources\CentreTarget;

use App\Models\Centertarget;
use App\Models\CentertargetMeta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Config;

class CentreTargetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Centertarget $target */
        $target = $this->resource;

        $monthsMap = Config::get('constants.months_array', []);
        $monthInt = $target->month !== null ? (int) $target->month : null;

        return [
            'id' => (int) $target->id,
            'year' => $target->year !== null ? (int) $target->year : null,
            'month' => $monthInt,
            'month_name' => $monthInt !== null && isset($monthsMap[$monthInt])
                ? (string) $monthsMap[$monthInt]
                : null,
            'working_days' => $target->working_days !== null ? (int) $target->working_days : null,
            'total_target_amount' => $this->whenLoaded(
                'center_target_meta',
                fn (): float => (float) $target->center_target_meta->sum('target_amount'),
            ),
            'centres' => $this->whenLoaded(
                'center_target_meta',
                fn (): array => $target->center_target_meta->map(fn (CentertargetMeta $meta): array => [
                    'location_id' => (int) $meta->location_id,
                    'location_name' => $meta->relationLoaded('location') ? $meta->location?->name : null,
                    'target_amount' => (float) $meta->target_amount,
                ])->values()->all(),
            ),
            'created_at' => $target->created_at?->toIso8601String(),
            'updated_at' => $target->updated_at?->toIso8601String(),
        ];
    }
}
