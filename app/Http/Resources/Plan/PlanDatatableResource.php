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
        $totalPrice = (float) $this->total_price;
        // Balance = what the patient still OWES us. Money already paid
        // (less any actual refunds we returned) reduces the balance.
        // `settle_amount` is internal ledger bookkeeping for service-
        // consumed-against-prepayment entries — they are NOT refunds and
        // must not enter the balance calc, otherwise a fully-paid plan
        // with consumed sessions reads as still owing the consumed value.
        $balance = max(0.0, $totalPrice - $cashReceive + $refundAmount);

        $planType = $this->plan_type instanceof PlanType
            ? $this->plan_type->value
            : ($this->plan_type ?? PlanType::Plan->value);

        $sessionCount = (int) ($this->session_count ?? 0);
        $consumedCount = (int) ($this->consumed_count ?? 0);

        return [
            'id'               => $this->id,
            'patient_id'       => $this->patient_id ?? 'N/A',
            'name'             => $this->user?->name ?? 'N/A',
            'phone'            => $this->user?->phone,
            'package_id'       => $this->name,
            'plan_name'        => $this->plan_name ?? '',
            'location_id'      => $this->formatLocation(),
            'location_name'    => $this->location?->name ?? 'N/A',
            'city_name'        => $this->location?->city?->name ?? 'N/A',
            'session_count'    => $sessionCount,
            'consumed_count'   => $consumedCount,
            'total'            => number_format($totalPrice),
            'total_raw'        => $totalPrice,
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
            // Latest of: most recent payment, bundle update, or service
            // consumption. Lets the UI render "last active 12 days ago"
            // — the single best signal of whether a plan is still alive.
            'latest_activity'  => $this->latest_advance_updated_at
                ? \Carbon\Carbon::parse($this->latest_advance_updated_at)->toIso8601String()
                : null,
            // True when the plan has unused sessions AND the patient has
            // no future booking at the branch — same as the at-risk
            // metric's abandoned-package bucket. Drives the at-risk
            // column chip on the plans list.
            'is_at_risk'       => (bool) ($this->is_at_risk ?? false),
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
