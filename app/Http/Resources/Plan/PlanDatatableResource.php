<?php

declare(strict_types=1);

namespace App\Http\Resources\Plan;

use App\Enums\PlanType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a single package row for the plans datatable.
 *
 * Expects the underlying model to have been loaded with pre-aggregated
 * aliases (cash_receive, settle_amount, refund_amount_calculated,
 * session_count, total_price) and eager-loaded user + location + city.
 */
final class PlanDatatableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cashReceive = (float) ($this->cash_receive ?? 0);
        $settleAmount = (float) ($this->settle_amount ?? 0);
        $refundAmount = (float) ($this->refund_amount_calculated ?? 0);
        $balance = $cashReceive - $settleAmount - $refundAmount;

        $planType = $this->plan_type instanceof PlanType
            ? $this->plan_type->value
            : ($this->plan_type ?? PlanType::Plan->value);

        return [
            'id'               => $this->id,
            'patient_id'       => $this->patient_id ?? 'N/A',
            'name'             => $this->user?->name ?? 'N/A',
            'package_id'       => $this->name,
            'plan_name'        => $this->plan_name ?? '',
            'location_id'      => $this->formatLocation(),
            'location_name'    => $this->location?->name ?? 'N/A',
            'city_name'        => $this->location?->city?->name ?? 'N/A',
            'session_count'    => (int) ($this->session_count ?? 0),
            'total'            => number_format((float) $this->total_price),
            'total_raw'        => (float) $this->total_price,
            'cash_receive'     => number_format($cashReceive),
            'cash_receive_raw' => $cashReceive,
            'settle_amount'    => number_format($settleAmount),
            'settle_amount_raw' => $settleAmount,
            'refunded'         => number_format($refundAmount),
            'balance'          => number_format($balance),
            'active'           => $this->active,
            'status'           => $this->active ? 'Active' : 'Inactive',
            'date'             => $this->created_at?->format('Y-m-d') ?? '',
            'created_at'       => $this->created_at?->format('F j, Y h:i A') ?? '',
            'patient_name'     => $this->user?->name ?? 'N/A',
            'membership_info'  => $this->formatMembershipInfo(),
            'plan_type'        => $planType,
        ];
    }

    private function formatLocation(): string
    {
        if (!$this->location?->city) {
            return 'N/A';
        }

        return $this->location->city->name . ' - ' . $this->location->name;
    }

    private function formatMembershipInfo(): string
    {
        $membership = $this->user?->membership;

        if (!$membership) {
            return 'No Membership';
        }

        $endDate = $membership->end_date ? Carbon::parse($membership->end_date) : null;
        $isExpired = $endDate?->isPast() ?? true;
        $status = $isExpired ? 'Expired' : ((bool) $membership->active ? 'Active' : 'Inactive');

        if ((int) ($membership->is_referral ?? 0) === 1) {
            return "Ref: ({$membership->code}) - {$status}";
        }

        $typeName = $membership->membershipType?->name ?? 'Gold';

        return "{$typeName} - {$membership->code} - {$status}";
    }
}
