<?php

declare(strict_types=1);

namespace App\Services\Upselling\Rollup;

use App\Enums\AppointmentType;
use App\Models\UpsellCollectedMonthly;
use App\Services\Upselling\CollectedAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes one (account, month) slice of `upsell_collected_monthly` — the
 * per-doctor, per-location cash actually attributed to upsold lines in that
 * calendar month. Mirrors App\Services\Dashboard\Rollup\DailyMetricsBuilder
 * (single transaction, idempotent, re-runnable).
 *
 * Algorithm per plan (full spec: memory `project_upsell_collected_incentive`):
 *  - Upsold lines = package_services with sold_by set, EXCLUDING the
 *    self-consultation line (the plan's consultation doctor selling to their
 *    own consult — a conversion, not an upsell).
 *  - The plan's collection window = [firstUpsellSaleDate, +90 days]. Cash that
 *    counts is net IN receipts inside that window minus refunds (refunds have
 *    NO window gate — they reverse forever).
 *  - "Collected this month" for a line = (its waterfall credit as cash stood at
 *    month-END) − (as it stood at the previous month-end). That delta is the
 *    cash recognised THIS month; refunds make it negative (clawback).
 *  - Credit goes to the line's CURRENT sold_by (reassignment-aware).
 *
 * `monthLineCredits()` is the shared per-line engine — `rebuild()` aggregates
 * it into the rollup, and the drill-down ledger reuses it for one doctor.
 *
 * v1 SIMPLIFICATIONS (documented): (a) the window is anchored at the plan's
 * FIRST upsell sale date — exact when a plan's lines are sold together (the
 * common case), a slight approximation for staggered sales; (b) the stored
 * `collected_amount` is the RAW month delta (may be negative). The payout-side
 * floor-at-zero + per-doctor carry-forward (`upsell_clawback_carry`) is a thin
 * layer applied at payroll close, NOT here. (c) `is_closed` rows are never
 * touched (frozen once a month's incentive is paid).
 */
final class UpsellCollectedBuilder
{
    /** Tolerance below which a delta is treated as zero (rounding noise). */
    private const EPSILON = 0.005;

    public function __construct(
        private readonly CollectedAllocator $allocator = new CollectedAllocator(),
    ) {}

    /**
     * Rebuild every open (doctor, location) row for the given (account, month).
     *
     * @return int number of rows written
     */
    public function rebuild(int $accountId, string $month): int
    {
        $monthDate = Carbon::parse($month)->startOfMonth()->toDateString();

        // Aggregate per "doctorId|locationId" → delta-this-month.
        $acc = [];
        foreach ($this->monthLineCredits($accountId, $month) as $rec) {
            $locKey = $rec['location_id'] === null ? 'null' : (int) $rec['location_id'];
            $key = $rec['sold_by'].'|'.$locKey;
            $acc[$key] = ($acc[$key] ?? 0.0) + $rec['collected'];
        }

        $written = 0;
        DB::transaction(function () use ($acc, $accountId, $monthDate, &$written): void {
            // Full-month rebuild: wipe OPEN rows, then write the fresh set.
            // Closed (frozen) rows are left untouched and never overwritten.
            UpsellCollectedMonthly::query()
                ->where('account_id', $accountId)
                ->where('month', $monthDate)
                ->where('is_closed', false)
                ->delete();

            foreach ($acc as $key => $amount) {
                [$doctorId, $locationRaw] = explode('|', $key);
                $locationId = $locationRaw === 'null' ? null : (int) $locationRaw;

                $isClosed = UpsellCollectedMonthly::query()
                    ->where('account_id', $accountId)
                    ->where('month', $monthDate)
                    ->where('doctor_id', (int) $doctorId)
                    ->when($locationId === null, fn ($q) => $q->whereNull('location_id'))
                    ->when($locationId !== null, fn ($q) => $q->where('location_id', $locationId))
                    ->where('is_closed', true)
                    ->exists();
                if ($isClosed) {
                    continue;
                }

                UpsellCollectedMonthly::create([
                    'account_id' => $accountId,
                    'month' => $monthDate,
                    'doctor_id' => (int) $doctorId,
                    'location_id' => $locationId,
                    'collected_amount' => round($amount, 2),
                ]);
                $written++;
            }
        });

        return $written;
    }

    /**
     * Per-line cash recognised in the (account, month) — the shared engine.
     * Pass `$onlyDoctor` to restrict the result to one seller's lines (the
     * allocation still uses all of each plan's lines so it stays correct).
     *
     * @return array<int, array{package_id:int, line_id:int, sold_by:int, location_id:int|null, value:float, is_consumed:bool, consumed_at:?string, service_id:int, sale_date:string, collected:float}>
     */
    public function monthLineCredits(int $accountId, string $month, ?int $onlyDoctor = null): array
    {
        $start = Carbon::parse($month)->startOfMonth()->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();
        $prevEnd = $start->copy()->subSecond();
        $consultancyTypeId = AppointmentType::Consultancy->value;

        // Candidate plans: an upsold line whose 90-day window overlaps the month.
        // When scoped to one doctor, only plans where THEY sold a line.
        $planIds = DB::table('package_services as ps')
            ->join('packages as p', 'ps.package_id', '=', 'p.id')
            ->whereNotNull('ps.sold_by')
            ->where('p.account_id', $accountId)
            ->where('ps.created_at', '<=', $end)
            ->whereRaw('DATE_ADD(ps.created_at, INTERVAL 90 DAY) >= ?', [$start])
            ->when($onlyDoctor !== null, fn ($q) => $q->where('ps.sold_by', $onlyDoctor))
            ->distinct()
            ->pluck('ps.package_id')
            ->all();

        if ($planIds === []) {
            return [];
        }

        $plans = DB::table('packages as p')
            ->join('appointments as a', 'p.appointment_id', '=', 'a.id')
            ->whereIn('p.id', $planIds)
            ->get(['p.id', 'p.location_id', 'a.appointment_type_id', 'a.doctor_id as appt_doctor_id'])
            ->keyBy('id');

        $linesByPlan = DB::table('package_services')
            ->whereIn('package_id', $planIds)
            ->whereNotNull('sold_by')
            ->get(['id', 'package_id', 'sold_by', 'tax_including_price', 'is_consumed', 'consumed_at', 'consumption_order', 'service_id', 'created_at'])
            ->groupBy('package_id');

        // Cash events: live IN receipts (not cancelled) + live OUT refunds.
        $cashByPlan = DB::table('package_advances')
            ->whereIn('package_id', $planIds)
            ->whereNull('deleted_at')
            ->where(function ($q): void {
                $q->where(function ($q2): void {
                    $q2->where('cash_flow', 'in')->where('is_cancel', 0);
                })->orWhere(function ($q2): void {
                    $q2->where('cash_flow', 'out')->where('is_refund', 1);
                });
            })
            ->get(['package_id', 'cash_flow', 'cash_amount', 'created_at'])
            ->groupBy('package_id');

        $out = [];

        foreach ($planIds as $planId) {
            $plan = $plans->get($planId);
            if ($plan === null) {
                continue;
            }

            // Upsold lines, self-consultation excluded.
            $upsold = [];
            foreach ($linesByPlan->get($planId, collect()) as $ln) {
                $isSelfConsult = (int) $plan->appointment_type_id === $consultancyTypeId
                    && (int) $plan->appt_doctor_id === (int) $ln->sold_by;
                if ($isSelfConsult) {
                    continue;
                }
                $upsold[] = [
                    'id' => (int) $ln->id,
                    'value' => (float) $ln->tax_including_price,
                    'is_consumed' => (int) $ln->is_consumed === 1,
                    'consumption_order' => (int) ($ln->consumption_order ?? 0),
                    'sold_by' => (int) $ln->sold_by,
                    'consumed_at' => $ln->consumed_at !== null ? (string) $ln->consumed_at : null,
                    'service_id' => (int) ($ln->service_id ?? 0),
                    'sale_date' => (string) $ln->created_at,
                    'sale_ts' => Carbon::parse($ln->created_at),
                ];
            }
            if ($upsold === []) {
                continue;
            }

            $firstSale = $upsold[0]['sale_ts'];
            foreach ($upsold as $l) {
                if ($l['sale_ts']->lt($firstSale)) {
                    $firstSale = $l['sale_ts'];
                }
            }
            $windowEnd = $firstSale->copy()->addDays(90)->endOfDay();

            $cash = $cashByPlan->get($planId, collect());
            $poolEnd = $this->pool($cash, $firstSale, $end, $windowEnd);
            $poolPrev = $this->pool($cash, $firstSale, $prevEnd, $windowEnd);

            $creditEnd = $this->allocator->allocate($upsold, $poolEnd);
            $creditPrev = $this->allocator->allocate($upsold, $poolPrev);

            $locationId = $plan->location_id === null ? null : (int) $plan->location_id;
            foreach ($upsold as $l) {
                $delta = ($creditEnd[$l['id']] ?? 0.0) - ($creditPrev[$l['id']] ?? 0.0);
                if (abs($delta) < self::EPSILON) {
                    continue;
                }
                if ($onlyDoctor !== null && $l['sold_by'] !== $onlyDoctor) {
                    continue;
                }
                $out[] = [
                    'package_id' => (int) $planId,
                    'line_id' => $l['id'],
                    'sold_by' => $l['sold_by'],
                    'location_id' => $locationId,
                    'value' => $l['value'],
                    'is_consumed' => $l['is_consumed'],
                    'consumed_at' => $l['consumed_at'],
                    'service_id' => $l['service_id'],
                    'sale_date' => $l['sale_date'],
                    'collected' => round($delta, 2),
                ];
            }
        }

        return $out;
    }

    /**
     * Net cash usable by the plan as of `$upTo`: IN receipts inside the
     * collection window [$from, min($upTo, $windowEnd)], minus refunds up to
     * `$upTo` (refunds have NO window gate — they reverse forever).
     *
     * @param  \Illuminate\Support\Collection<int, object>  $cash
     */
    private function pool($cash, Carbon $from, Carbon $upTo, Carbon $windowEnd): float
    {
        if ($upTo->lt($from)) {
            return 0.0;
        }
        $inCap = $upTo->lt($windowEnd) ? $upTo : $windowEnd;

        $in = 0.0;
        $refund = 0.0;
        foreach ($cash as $c) {
            $t = Carbon::parse($c->created_at);
            if ($c->cash_flow === 'in') {
                if ($t->gte($from) && $t->lte($inCap)) {
                    $in += (float) $c->cash_amount;
                }
            } elseif ($t->lte($upTo)) { // OUT refund — no window gate
                $refund += (float) $c->cash_amount;
            }
        }

        return $in - $refund;
    }
}
