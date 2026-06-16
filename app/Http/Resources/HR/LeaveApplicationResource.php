<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API shape for a leave application.
 *
 * ALLURA-REVIEW: CLAUDE.md mandates ISO-8601 UTC for API dates. The legacy
 * datatable resource emits `d M Y` strings; once the mobile app ships we should
 * migrate the datatable too. Flagging rather than changing the datatable here.
 */
class LeaveApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Models\LeaveApplication $app */
        $app = $this->resource;
        $employee = $app->user;
        $reviewer = $app->reviewer;
        $detail = $employee?->employeeDetail;

        $totalDays = (float) $app->total_days;
        $shiftHours = $app->shift_hours_snapshot !== null ? (float) $app->shift_hours_snapshot : null;
        $totalHours = $shiftHours !== null ? round($totalDays * $shiftHours, 2) : null;

        return [
            'id' => $app->id,
            'employee' => $employee ? [
                'id' => $employee->id,
                'name' => $employee->name,
                'designation' => $detail?->designation?->name,
            ] : null,
            'leave_type' => $app->leaveType ? [
                'id' => $app->leaveType->id,
                'name' => $app->leaveType->name,
            ] : null,
            'duration_type' => $app->duration_type?->value,
            'duration_label' => $app->duration_type?->label(),
            'start_date' => $app->start_date?->toIso8601String(),
            'end_date' => $app->end_date?->toIso8601String(),
            'total_days' => $totalDays,
            'custom_hours' => $app->custom_hours !== null ? (float) $app->custom_hours : null,
            'shift_hours_snapshot' => $shiftHours,
            'total_hours' => $totalHours,
            'reason' => $app->reason,
            'status' => $app->status?->value,
            'status_label' => $app->status?->label(),
            'review_notes' => $app->review_notes,
            'reviewed_by' => $reviewer ? [
                'id' => $reviewer->id,
                'name' => $reviewer->name,
            ] : null,
            'reviewed_at' => $app->reviewed_at?->toIso8601String(),
            'created_at' => $app->created_at?->toIso8601String(),
            'updated_at' => $app->updated_at?->toIso8601String(),
        ];
    }
}
