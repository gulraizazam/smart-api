<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill plan 47439's tax math from exclusive to inclusive.
 *
 * Plan 47439 was created on 2026-04-30 14:10 — before the org-wide
 * `sys-tax-treatment` setting was finalised to "Inclusive" (3) on
 * 2026-05-02 11:01. The package_bundles rows landed with exclusive
 * math (tax stacked on top of the operator's Net price), so the
 * customer-facing Total reads 47,954 even though the operator quoted
 * 40,985 (catalog Net price for the two packages combined).
 *
 * The detail dialog and printable invoice render the data correctly —
 * the data itself is misaligned with the operator's intent. Re-keying
 * the bundles to inclusive math (operator's Net IS the all-in total,
 * tax derived inside) makes the dialog/invoice show what the customer
 * was actually quoted.
 *
 * Scope: only this one plan. New plans created after 2026-05-02 11:01
 * already land inclusive via PlanDiscountService::saveServiceForPlan
 * (which honors Settings::getOrgTaxTreatment). A broader sweep across
 * historical plans is out of scope here — those plans may have been
 * intentionally exclusive depending on what was quoted.
 *
 * Down: writes back the original exclusive snapshot so the migration
 * is reversible.
 */
return new class extends Migration
{
    /** Plan id targeted by this backfill. */
    private const PLAN_ID = 47439;

    /**
     * Inclusive tax math for parent `package_bundles` row.
     * Operator's Net price (`service_price`) becomes `tax_including_price`;
     * pre-tax and tax_price are derived inside it. Mirrors the formula in
     * `PlanDiscountService::saveServiceForPlan` (line 1636 onward).
     *
     * @return array{tax_exclusive_net_amount: int, tax_price: int, tax_including_price: float}
     */
    private function inclusiveBundleMath(float $netPrice, float $taxPct): array
    {
        if ($taxPct <= 0) {
            return [
                'tax_exclusive_net_amount' => (int) ceil($netPrice),
                'tax_price' => 0,
                'tax_including_price' => $netPrice,
            ];
        }
        $exclusive = (int) ceil((100 * $netPrice) / ($taxPct + 100));
        return [
            'tax_exclusive_net_amount' => $exclusive,
            'tax_price' => (int) ceil($netPrice - $exclusive),
            'tax_including_price' => $netPrice,
        ];
    }

    /**
     * Inclusive tax math for a child `package_services` row.
     * Same formula, but for tax_exclusive_*price* (note the column-name
     * difference between bundle and service rows).
     *
     * @return array{tax_exclusive_price: int, tax_price: int, tax_including_price: float}
     */
    private function inclusiveChildMath(float $childInclusive, float $taxPct): array
    {
        if ($taxPct <= 0) {
            return [
                'tax_exclusive_price' => (int) ceil($childInclusive),
                'tax_price' => 0,
                'tax_including_price' => $childInclusive,
            ];
        }
        $exclusive = (int) ceil((100 * $childInclusive) / ($taxPct + 100));
        return [
            'tax_exclusive_price' => $exclusive,
            'tax_price' => (int) ceil($childInclusive - $exclusive),
            'tax_including_price' => $childInclusive,
        ];
    }

    public function up(): void
    {
        DB::transaction(function () {
            $bundles = DB::table('package_bundles')
                ->where('package_id', self::PLAN_ID)
                ->get();

            $newGrandTotal = 0.0;

            foreach ($bundles as $b) {
                $netPrice = (float) $b->service_price;
                $taxPct = (float) $b->tax_percentage;

                $bundleMath = $this->inclusiveBundleMath($netPrice, $taxPct);
                $newGrandTotal += $bundleMath['tax_including_price'];

                DB::table('package_bundles')
                    ->where('id', $b->id)
                    ->update([
                        'tax_exclusive_net_amount' => $bundleMath['tax_exclusive_net_amount'],
                        'tax_price' => $bundleMath['tax_price'],
                        'tax_including_price' => $bundleMath['tax_including_price'],
                        'net_amount' => $netPrice,
                        'is_exclusive' => 0,
                        'updated_at' => now(),
                    ]);

                // Children: split the parent's new inclusive total across
                // `count(children)` sessions. Last child absorbs any rounding
                // remainder so the children sum exactly to the parent total.
                $children = DB::table('package_services')
                    ->where('package_bundle_id', $b->id)
                    ->orderBy('id')
                    ->get();

                $sessionCount = $children->count();
                if ($sessionCount === 0) {
                    continue;
                }

                $perSession = round($bundleMath['tax_including_price'] / $sessionCount, 2);
                $runningSum = 0.0;
                $lastIdx = $sessionCount - 1;

                foreach ($children->values() as $idx => $c) {
                    $childInclusive = $idx === $lastIdx
                        ? round($bundleMath['tax_including_price'] - $runningSum, 2)
                        : $perSession;
                    $runningSum += $childInclusive;

                    $childMath = $this->inclusiveChildMath($childInclusive, $taxPct);

                    DB::table('package_services')
                        ->where('id', $c->id)
                        ->update([
                            'price' => $childInclusive,
                            'tax_exclusive_price' => $childMath['tax_exclusive_price'],
                            'tax_price' => $childMath['tax_price'],
                            'tax_including_price' => $childMath['tax_including_price'],
                            'is_exclusive' => 0,
                            'updated_at' => now(),
                        ]);
                }
            }

            DB::table('packages')
                ->where('id', self::PLAN_ID)
                ->update([
                    'total_price' => $newGrandTotal,
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        // Restore the original exclusive-math values captured pre-migration.
        // These are the values from `package_bundles` and `package_services`
        // for plan 47439 as they existed on 2026-05-02 (before this fix).
        DB::transaction(function () {
            // ── package_bundles ──
            DB::table('package_bundles')->where('id', 165341)->update([
                'tax_exclusive_net_amount' => 20990.00,
                'tax_price' => 3569.00,
                'tax_including_price' => 24559.00,
                'net_amount' => 20990.00,
                'is_exclusive' => 0,
            ]);
            DB::table('package_bundles')->where('id', 165355)->update([
                'tax_exclusive_net_amount' => 19995.00,
                'tax_price' => 3400.00,
                'tax_including_price' => 23395.00,
                'net_amount' => 19995.00,
                'is_exclusive' => 0,
            ]);

            // ── package_services children ──
            // Carbon (165341): 3 sessions of 8186.34/8186.34/8186.32
            DB::table('package_services')->where('id', 260525)->update([
                'price' => 8186.34, 'tax_exclusive_price' => 6997.00,
                'tax_price' => 1190.00, 'tax_including_price' => 8186.34, 'is_exclusive' => 0,
            ]);
            DB::table('package_services')->where('id', 260526)->update([
                'price' => 8186.34, 'tax_exclusive_price' => 6997.00,
                'tax_price' => 1190.00, 'tax_including_price' => 8186.34, 'is_exclusive' => 0,
            ]);
            DB::table('package_services')->where('id', 260527)->update([
                'price' => 8186.32, 'tax_exclusive_price' => 6997.00,
                'tax_price' => 1190.00, 'tax_including_price' => 8186.32, 'is_exclusive' => 0,
            ]);

            // Aqualyx (165355): 2 sessions of 11697.50 each
            DB::table('package_services')->where('id', 260561)->update([
                'price' => 11697.50, 'tax_exclusive_price' => 9998.00,
                'tax_price' => 1700.00, 'tax_including_price' => 11697.50, 'is_exclusive' => 0,
            ]);
            DB::table('package_services')->where('id', 260562)->update([
                'price' => 11697.50, 'tax_exclusive_price' => 9998.00,
                'tax_price' => 1700.00, 'tax_including_price' => 11697.50, 'is_exclusive' => 0,
            ]);

            // ── packages.total_price ──
            DB::table('packages')->where('id', 47439)->update(['total_price' => 47954.00]);
        });
    }
};
