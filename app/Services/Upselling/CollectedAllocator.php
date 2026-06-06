<?php

declare(strict_types=1);

namespace App\Services\Upselling;

/**
 * The line-value cash-constrained waterfall at the heart of "upsell collected".
 *
 * Given the cash available to a plan and the plan's upsold lines, it allocates
 * the cash DELIVERED-FIRST, each line capped at its OWN value. `total_price` is
 * never consulted — allocation depends only on per-line values and delivery
 * status. Pure + DB-free so it can be unit-tested exhaustively.
 *
 * Invariants (pinned by CollectedAllocatorTest):
 *   - Σ credit == min(availableCash, Σ line values)   (conservation)
 *   - no line ever receives more than its value
 *   - availableCash <= 0  ⇒  every line gets 0
 *
 * Business spec: memory `project_upsell_collected_incentive`.
 */
final class CollectedAllocator
{
    /**
     * @param  array<int, array{id:int, value:float|int|string, is_consumed:bool, consumption_order:int}>  $lines
     * @return array<int, float>  line id => credited amount
     */
    public function allocate(array $lines, float $availableCash): array
    {
        $credit = [];
        foreach ($lines as $line) {
            $credit[(int) $line['id']] = 0.0;
        }

        if ($availableCash <= 0.0 || $lines === []) {
            return $credit;
        }

        // Delivered lines first (is_consumed = true), then by consumption_order
        // ascending, then id ascending — a stable, deterministic priority.
        $ordered = $lines;
        usort($ordered, static function (array $a, array $b): int {
            if ($a['is_consumed'] !== $b['is_consumed']) {
                return $a['is_consumed'] ? -1 : 1;
            }
            if ($a['consumption_order'] !== $b['consumption_order']) {
                return $a['consumption_order'] <=> $b['consumption_order'];
            }
            return (int) $a['id'] <=> (int) $b['id'];
        });

        $remaining = $availableCash;
        foreach ($ordered as $line) {
            if ($remaining <= 0.0) {
                break;
            }
            $value = max(0.0, (float) $line['value']);
            $take = min($value, $remaining);
            $credit[(int) $line['id']] = $take;
            $remaining -= $take;
        }

        return $credit;
    }
}
