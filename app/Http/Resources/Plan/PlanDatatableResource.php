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
        // Balance is signed: positive = patient still owes us, negative =
        // patient over-paid (credit on the plan). `settle_amount` is
        // internal ledger bookkeeping for service-consumed-against-
        // prepayment entries — they are NOT refunds and must not enter the
        // balance calc, otherwise a fully-paid plan with consumed sessions
        // reads as still owing the consumed value.
        // We deliberately do not clamp to 0 here: a credit balance is an
        // operational signal (advance payment, no services attached, or
        // refund pending) the listing should surface, not hide.
        $balance = $totalPrice - $cashReceive + $refundAmount;

        $planType = $this->plan_type instanceof PlanType
            ? $this->plan_type->value
            : ($this->plan_type ?? PlanType::Plan->value);

        $sessionCount = (int) ($this->session_count ?? 0);
        $consumedCount = (int) ($this->consumed_count ?? 0);

        // Delete-blocker fields are pre-computed on the row by the
        // datatable query (joined `pa_agg` + `id_agg`). Mirrors the
        // `PlanService::deletePlan` server-side check so the SPA can
        // disable the trash icon and render the reason inline instead
        // of finding out post-click via a 409 response.
        $hasAdvances = (int) ($this->has_advances ?? 0) === 1;
        $invoiceCount = (int) ($this->invoice_count ?? 0);
        $deleteBlockers = [];
        if ($invoiceCount > 0) {
            $deleteBlockers[] = 'invoices';
        }
        if ($hasAdvances) {
            $deleteBlockers[] = 'payments';
        }

        return [
            'id'               => $this->id,
            'patient_id'       => $this->patient_id ?? 'N/A',
            'name'             => $this->user?->name ?? 'N/A',
            'phone'            => $this->user?->phone,
            'package_id'       => $this->name,
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
            'patient_name'     => $this->user?->name ?? 'N/A',
            'membership'       => $this->formatMembership(),
            'plan_type'        => $planType,
            'can_delete'       => $deleteBlockers === [],
            'delete_blocked_reason' => $deleteBlockers === []
                ? null
                : 'Cannot delete — plan has '.implode(' and ', $deleteBlockers).'.',
        ];
    }

    private function formatLocation(): string
    {
        if (!$this->location?->city) {
            return 'N/A';
        }

        return $this->location->city->name . ' - ' . $this->location->name;
    }

    /**
     * Structured membership snapshot for the patient cell. Frontend renders
     * this as a typed badge (gold / student / referral / etc.) so the SPA
     * doesn't have to parse a pre-formatted string. Returns null when the
     * patient has no active membership row.
     *
     * @return array{type: string, code: string|null, status: 'Active'|'Inactive'|'Expired', is_referral: bool}|null
     */
    private function formatMembership(): ?array
    {
        $membership = $this->user?->membership;
        if (!$membership) {
            return null;
        }

        // Three-way status — was previously two:
        //   • end_date NULL   → never activated (unpaid, or unpaid student
        //                       awaiting verification). Status: Inactive.
        //   • end_date < now  → activated previously, validity window
        //                       has closed. Status: Expired.
        //   • end_date >= now → currently within the validity window.
        //                       Status: Active (gated on `active` flag).
        // The earlier `$endDate?->isPast() ?? true` collapsed NULL into
        // "Expired", which read as a card that *had* been valid and
        // lapsed — wrong for a card that never started.
        if ($membership->end_date === null) {
            $status = 'Inactive';
        } else {
            $endDate = Carbon::parse($membership->end_date);
            $status = $endDate->isPast()
                ? 'Expired'
                : ((bool) $membership->active ? 'Active' : 'Inactive');
        }

        $typeName = $membership->membershipType?->name ?? 'Gold';

        return [
            'type'        => $typeName,
            'code'        => $membership->code,
            'status'      => $status,
            'is_referral' => (int) ($membership->is_referral ?? 0) === 1,
            // ISO date so the SPA can localise/format. NULL when the
            // card has never been activated (`Inactive` status). The
            // model doesn't cast `end_date`, so it arrives as a raw
            // string — pass it through directly.
            'end_date'    => $membership->end_date,
        ];
    }
}
