<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use App\Models\LeaveBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LeaveBalance $balance */
        $balance = $this->resource;

        $allocated = (float) $balance->allocated;
        $used = (float) $balance->used;

        return [
            'id' => (int) $balance->id,
            'user_id' => (int) $balance->user_id,
            'leave_type_id' => (int) $balance->leave_type_id,
            'fiscal_year' => (string) $balance->fiscal_year,
            'allocated' => $allocated,
            'used' => $used,
            'remaining' => round($allocated - $used, 2),
            'user' => $this->whenLoaded('user', fn () => $balance->user ? [
                'id' => (int) $balance->user->id,
                'name' => (string) $balance->user->name,
            ] : null),
            'leave_type' => $this->whenLoaded('leaveType', fn () => $balance->leaveType ? [
                'id' => (int) $balance->leaveType->id,
                'name' => (string) $balance->leaveType->name,
                'is_paid' => (bool) $balance->leaveType->is_paid,
            ] : null),
            'created_at' => $balance->created_at?->toIso8601String(),
            'updated_at' => $balance->updated_at?->toIso8601String(),
        ];
    }
}
