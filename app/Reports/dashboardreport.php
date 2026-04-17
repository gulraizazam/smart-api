<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Locations;
use App\Services\Dashboard\Support\SalesLedgerQuery;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class dashboardreport
{
    private const VALID_PAYMENT_MODES = ['Cash', 'Card', 'Bank/Wire Transfer'];

    private const CHART_HEADER = [['Task', 'Hours per Day']];

    /**
     * Collection by centre widgets — aggregate SQL instead of loading all rows.
     */
    public static function CollectionByRevenueWidgets(
        array $locationIds,
        ?int $accountId,
        string $period,
        mixed $request = null,
    ): array {
        $total = 0;
        $reportData = self::CHART_HEADER;

        if (empty($locationIds)) {
            return [$reportData, $total];
        }

        $locations = Locations::with('city')->whereIn('id', $locationIds)->get()->keyBy('id');
        [$startDate, $endDate] = self::resolveDateRange($period);

        $results = DB::table('package_advances as pa')
            ->join('payment_modes as pm', 'pa.payment_mode_id', '=', 'pm.id')
            ->whereIn('pa.location_id', $locationIds)
            ->where('pa.account_id', $accountId)
            ->where('pa.created_at', '>=', $startDate)
            ->where('pa.created_at', '<', $endDate)
            ->where('pa.is_adjustment', 0)
            ->where('pa.is_tax', 0)
            ->where('pa.is_cancel', 0)
            ->where('pa.cash_amount', '!=', 0)
            ->whereNull('pa.deleted_at')
            ->select(
                'pa.location_id',
                DB::raw("SUM(CASE
                    WHEN pa.cash_flow = 'in' AND pm.name IN ('Cash','Card','Bank/Wire Transfer')
                    THEN pa.cash_amount ELSE 0 END) as revenue"),
                DB::raw("SUM(CASE
                    WHEN pa.cash_flow = 'out' AND pa.is_refund = 1
                    THEN pa.cash_amount ELSE 0 END) as refunds"),
            )
            ->groupBy('pa.location_id')
            ->get()
            ->keyBy('location_id');

        foreach ($locationIds as $locationId) {
            $location = $locations->get($locationId);
            if (! $location) {
                continue;
            }

            $row = $results->get($locationId);
            $balance = $row ? (float) $row->revenue - (float) $row->refunds : 0;

            if ($balance > 0) {
                $cityName = $location->city->name ?? '';
                $reportData[$locationId] = [
                    $cityName.' - '.$location->name,
                    $balance,
                ];
                $total += $balance;
            }
        }

        return [$reportData, $total];
    }

    /**
     * My Collection by centre widgets — filtered by current user.
     */
    public static function MyCollectionByRevenueWidgets(
        mixed $locationInformation,
        ?int $accountId,
        string $period,
        mixed $request = null,
    ): array {
        // Extract location IDs from array keys or Collection
        $locationIds = match (true) {
            is_array($locationInformation) => array_keys($locationInformation),
            $locationInformation instanceof Collection => $locationInformation->keys()->all(),
            default => (array) $locationInformation,
        };

        if (auth()->id() === 1) {
            return self::CollectionByRevenueWidgets($locationIds, $accountId, $period, $request);
        }

        $total = 0;
        $reportData = self::CHART_HEADER;

        if (empty($locationIds)) {
            return [$reportData, $total];
        }

        $locations = Locations::with('city')->whereIn('id', $locationIds)->get()->keyBy('id');
        [$startDate, $endDate] = self::resolveDateRange($period);

        // My collection = only cash_flow 'in', no refunds, filtered by created_by
        $results = DB::table('package_advances as pa')
            ->join('payment_modes as pm', 'pa.payment_mode_id', '=', 'pm.id')
            ->whereIn('pa.location_id', $locationIds)
            ->where('pa.account_id', $accountId)
            ->where('pa.created_by', Auth::id())
            ->where('pa.created_at', '>=', $startDate)
            ->where('pa.created_at', '<', $endDate)
            ->where('pa.cash_flow', 'in')
            ->where('pa.is_adjustment', 0)
            ->where('pa.is_tax', 0)
            ->where('pa.is_cancel', 0)
            ->where('pa.cash_amount', '!=', 0)
            ->whereNull('pa.deleted_at')
            ->whereIn('pm.name', self::VALID_PAYMENT_MODES)
            ->select(
                'pa.location_id',
                DB::raw('SUM(pa.cash_amount) as revenue'),
            )
            ->groupBy('pa.location_id')
            ->get()
            ->keyBy('location_id');

        foreach ($locationIds as $locationId) {
            $location = $locations->get($locationId);
            if (! $location) {
                continue;
            }

            $balance = (float) ($results->get($locationId)?->revenue ?? 0);

            if ($balance > 0) {
                $cityName = $location->city->name ?? '';
                $reportData[] = [
                    $cityName.' - '.$location->name,
                    $balance,
                ];
                $total += $balance;
            }
        }

        return [$reportData, $total];
    }

    /**
     * Collection total across all centres. Delegates to the canonical
     * SalesLedgerQuery helper so the home page and Management Dashboard
     * always report the same figure from the same SQL.
     */
    public static function collectionbycenter(
        array $locationIds,
        ?int $accountId,
        string $period,
        mixed $request = null,
    ): array {
        if (empty($locationIds) || $accountId === null) {
            return [0];
        }

        [$startDate, $endDate] = self::resolveDateRange($period);

        $sales = SalesLedgerQuery::forRange(
            accountId: (int) $accountId,
            startDate: $startDate->format('Y-m-d'),
            endDate: $endDate->copy()->subDay()->format('Y-m-d'),
        )
            ->whereIn('pa.location_id', $locationIds)
            ->selectRaw(SalesLedgerQuery::netCollectionSelectRaw().' AS sales')
            ->value('sales');

        return [(float) ($sales ?? 0)];
    }

    /**
     * Resolve period string to [startDate, endDate] for range queries.
     * Returns Carbon instances for >= start AND < end (exclusive end).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function resolveDateRange(string $period): array
    {
        return match ($period) {
            'today' => [Carbon::today(), Carbon::tomorrow()],
            'yesterday' => [Carbon::yesterday(), Carbon::today()],
            'last7day', 'last7days' => [Carbon::now()->subDays(6)->startOfDay(), Carbon::tomorrow()],
            'week' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()->addDay()],
            'thisMonth' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()->addDay()],
            'lastMonth', 'lastmonth' => [
                Carbon::now()->subMonthNoOverflow()->startOfMonth(),
                Carbon::now()->subMonthNoOverflow()->endOfMonth()->addDay(),
            ],
            default => [Carbon::today(), Carbon::tomorrow()],
        };
    }
}
