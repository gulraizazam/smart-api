<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Bundle of the employee's own leave state for the current fiscal year:
 *   - leave types available
 *   - balances (allocated / used / remaining) keyed by leave_type_id
 *   - applications in this fiscal year
 *   - shift_hours on their profile (needed for custom-hour leaves)
 *   - fiscal_year string
 *
 * Expected resource payload shape (passed as an array):
 *   [
 *     'leave_types'  => Collection<LeaveType>,
 *     'balances'     => Collection<LeaveBalance>,
 *     'applications' => Collection<LeaveApplication>,
 *     'shift_hours'  => ?float,
 *     'fiscal_year'  => string,
 *   ]
 */
class MyLeavesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var array{leave_types: Collection, balances: Collection, applications: Collection, shift_hours: float|null, fiscal_year: string} $bundle */
        $bundle = $this->resource;

        return [
            'fiscal_year' => $bundle['fiscal_year'],
            'shift_hours' => $bundle['shift_hours'] !== null ? (float) $bundle['shift_hours'] : null,

            'leave_types' => $bundle['leave_types']
                ->map(fn (LeaveType $type) => [
                    'id' => $type->id,
                    'name' => $type->name,
                ])
                ->values()
                ->all(),

            'balances' => $bundle['balances']
                ->map(function (LeaveBalance $bal) {
                    $allocated = (float) $bal->allocated;
                    $used = (float) $bal->used;

                    return [
                        'leave_type_id' => (int) $bal->leave_type_id,
                        'allocated' => $allocated,
                        'used' => $used,
                        'remaining' => round($allocated - $used, 2),
                        'fiscal_year' => $bal->fiscal_year,
                    ];
                })
                ->values()
                ->all(),

            'applications' => LeaveApplicationResource::collection($bundle['applications'])
                ->resolve($request),
        ];
    }
}
