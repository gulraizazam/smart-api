<?php

declare(strict_types=1);

namespace App\Services\Plan;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ConsumptionReservation — the single rule that decides how much of a plan's
 * payment is "committed" by what has already been consumed.
 *
 * Bundles (/bundles) and Packages (/packages) are sold cheap in exchange for
 * full upfront payment; a configurable Buy/Get group pairs a paid BUY session
 * with discounted/free GET sessions (consumption_order 1 = BUY, 2 = priced
 * GET, 3 = free GET). When a plan is FULLY PAID the client may consume in any
 * order — including a free GET before its paid BUY. The moment a GET is used
 * ahead of its BUY, that BUY is "reserved": its money can neither be spent on
 * a newly added service nor refunded until the BUY itself is consumed. That
 * stops a client keeping a free GET while the paying BUY is left stranded and
 * its money quietly diverted elsewhere.
 *
 * Two questions, one rule:
 *   - consume gate → requiredFloor(): the minimum payment that must be present
 *     before a session can be consumed (regular-price basis).
 *   - refund gate  → reservedRefundAmount(): money that must be held back from
 *     a refund (sold-price basis, matching the refund's existing deductions).
 *
 * source_type is authoritative for both product types — no overloaded
 * bundle_id join. Reusable across any plan type.
 */
class ConsumptionReservation
{
    /** Product source_types whose consume floor is the REGULAR price. */
    private const BUNDLE_PRODUCTS = ['bundle', 'service_bundle'];

    /**
     * Minimum cumulative payment that must remain on the plan, given what is
     * consumed plus what out-of-order consumption has reserved. Capped at the
     * plan's sold total so a client never owes more than the plan costs.
     *
     * Regular-price basis for bundle/service_bundle sessions (the
     * "regular-price minimum" consume rule, floored at the sold price so it is
     * never weaker than the old gate); sold price for a standalone service.
     *
     * @param  int|null  $consumingSessionId  treat this package_service as if
     *   it were also consumed — the consume gate's "after this session"
     *   question. null = current consumed state (refund / cash-down gate).
     */
    public function requiredFloor(int $packageId, ?int $consumingSessionId = null): float
    {
        $rows = $this->rows($packageId);

        $consumed = static fn (object $r): bool => (int) $r->is_consumed === 1
            || (int) $r->id === $consumingSessionId;

        $floor = 0.0;
        foreach ($rows as $r) {
            if ($consumed($r) || $this->isReserved($r, $rows, $consumed)) {
                $floor += $this->requiredPrice($r);
            }
        }

        $planValue = (float) $rows->sum(static fn (object $r): float => (float) $r->tax_including_price);

        return min($floor, $planValue);
    }

    /**
     * Money that must be withheld from a refund because a free/discounted GET
     * was consumed ahead of its paid BUY. Sold-price basis, matching the
     * refund's existing consumed-amount deduction — purely additive: zero
     * unless a plan has actually been consumed out of order.
     */
    public function reservedRefundAmount(int $packageId): float
    {
        $rows = $this->rows($packageId);
        $consumed = static fn (object $r): bool => (int) $r->is_consumed === 1;

        $reserved = 0.0;
        foreach ($rows as $r) {
            if (! $consumed($r) && $this->isReserved($r, $rows, $consumed)) {
                $reserved += (float) $r->price;
            }
        }

        return $reserved;
    }

    /**
     * A row is reserved when it is unconsumed but a LATER-order sibling in the
     * same configurable group is consumed — i.e. a discounted/free session was
     * used ahead of this paid one.
     *
     * @param  Collection<int, object>  $rows
     * @param  callable(object):bool  $consumed
     */
    private function isReserved(object $row, Collection $rows, callable $consumed): bool
    {
        if ($row->config_group_id === null) {
            return false;
        }

        return $rows->contains(static fn (object $o): bool => $o->config_group_id !== null
            && (int) $o->config_group_id === (int) $row->config_group_id
            && (int) $o->id !== (int) $row->id
            && (int) $o->consumption_order > (int) $row->consumption_order
            && $consumed($o));
    }

    /**
     * Per-session "required" amount: REGULAR price for a bundle/package session
     * (floored at the sold price so it is never weaker than the discounted
     * gate), sold price for a standalone service. source_type is authoritative.
     */
    private function requiredPrice(object $row): float
    {
        return in_array($row->source_type, self::BUNDLE_PRODUCTS, true)
            ? max((float) $row->orignal_price, (float) $row->tax_including_price)
            : (float) $row->tax_including_price;
    }

    /**
     * The plan's sessions joined to their bundle row for source_type +
     * config_group_id. One query; the per-session arithmetic happens in PHP so
     * the rule stays readable and unit-testable.
     *
     * @return Collection<int, object>
     */
    private function rows(int $packageId): Collection
    {
        return DB::table('package_services')
            ->leftJoin('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
            ->where('package_services.package_id', $packageId)
            ->select([
                'package_services.id',
                'package_services.is_consumed',
                'package_services.consumption_order',
                'package_services.orignal_price',
                'package_services.tax_including_price',
                'package_services.price',
                'package_bundles.source_type',
                'package_bundles.config_group_id',
            ])
            ->get();
    }
}
