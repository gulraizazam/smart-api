<?php

declare(strict_types=1);

namespace Tests\Unit\Upselling;

use App\Services\Upselling\CollectedAllocator;
use PHPUnit\Framework\TestCase;

/**
 * The pure waterfall at the heart of "upsell collected". These pin the
 * load-bearing invariants — delivered-first, capped at line value, and the
 * conservation rule (Σ credit can never exceed the cash available). Pure
 * static logic → extends PHPUnit TestCase directly (no container boot).
 *
 * Business spec: memory `project_upsell_collected_incentive`.
 */
class CollectedAllocatorTest extends TestCase
{
    /** @return array{id:int, value:float, is_consumed:bool, consumption_order:int} */
    private function line(int $id, float $value, bool $consumed = false, int $order = 0): array
    {
        return ['id' => $id, 'value' => $value, 'is_consumed' => $consumed, 'consumption_order' => $order];
    }

    public function test_delivered_line_is_funded_before_an_undelivered_one(): void
    {
        // line 1 undelivered (60), line 2 delivered (40), cash 40 → all to the
        // delivered line; the undelivered upsell earns nothing yet.
        $credit = (new CollectedAllocator())->allocate([
            $this->line(1, 60, consumed: false),
            $this->line(2, 40, consumed: true),
        ], 40);

        $this->assertSame(40.0, $credit[2]);
        $this->assertSame(0.0, $credit[1]);
    }

    public function test_each_line_is_capped_at_its_own_value(): void
    {
        // Cash (100) exceeds the lines' total (70): each line gets its value, no
        // line is over-credited, and the surplus 30 is simply not allocated.
        $credit = (new CollectedAllocator())->allocate([
            $this->line(1, 30, consumed: true),
            $this->line(2, 40, consumed: false),
        ], 100);

        $this->assertSame(30.0, $credit[1]);
        $this->assertSame(40.0, $credit[2]);
        $this->assertEqualsWithDelta(70.0, array_sum($credit), 0.001);
    }

    public function test_conservation_total_credit_never_exceeds_cash_or_line_values(): void
    {
        $lines = [$this->line(1, 50, true), $this->line(2, 30, false), $this->line(3, 20, false)];
        $allocator = new CollectedAllocator();

        // cash 70 < Σvalues 100 → total credit == 70
        $partial = $allocator->allocate($lines, 70);
        $this->assertEqualsWithDelta(70.0, array_sum($partial), 0.001);
        foreach ([1 => 50.0, 2 => 30.0, 3 => 20.0] as $id => $value) {
            $this->assertLessThanOrEqual($value + 0.001, $partial[$id]);
        }

        // cash 200 > Σvalues 100 → total credit capped at 100 (never over)
        $excess = $allocator->allocate($lines, 200);
        $this->assertEqualsWithDelta(100.0, array_sum($excess), 0.001);
    }

    public function test_zero_or_negative_cash_credits_nothing(): void
    {
        $lines = [$this->line(1, 50, true), $this->line(2, 30, false)];
        $allocator = new CollectedAllocator();

        $this->assertSame(0.0, array_sum($allocator->allocate($lines, 0)));
        $this->assertSame(0.0, array_sum($allocator->allocate($lines, -10)));
    }

    public function test_consumption_order_breaks_ties_among_delivered_lines(): void
    {
        // Both delivered; the lower consumption_order is funded first.
        $credit = (new CollectedAllocator())->allocate([
            $this->line(1, 30, consumed: true, order: 2),
            $this->line(2, 30, consumed: true, order: 1),
        ], 30);

        $this->assertSame(30.0, $credit[2]);
        $this->assertSame(0.0, $credit[1]);
    }

    public function test_remainder_after_delivered_flows_to_undelivered_by_value(): void
    {
        // delivered line 1 (40) funded in full; remaining 10 of 50 cash goes to
        // the undelivered line 2 (capped well under its 60 value).
        $credit = (new CollectedAllocator())->allocate([
            $this->line(1, 40, consumed: true, order: 1),
            $this->line(2, 60, consumed: false, order: 2),
        ], 50);

        $this->assertSame(40.0, $credit[1]);
        $this->assertSame(10.0, $credit[2]);
    }
}
