<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Support;

use App\Enums\ResourceType;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Canonical "cash-mode collection" filter for the package_advances ledger.
 *
 * Collection is distinct from recognised revenue:
 *   - REVENUE (RevenueLedgerQuery)  — valid revenue rows less refund offsets.
 *   - COLLECTION (this class)       — money received via Cash / Card / Bank
 *     wire-transfer, minus refunds. Matches dashboardreport::collectionbycenter()
 *     (/admin/home's "Sales" tile).
 *
 * Usage:
 *   SalesLedgerQuery::forScope($scope, $range)
 *       ->groupBy('pa.location_id')
 *       ->selectRaw('pa.location_id, ' . SalesLedgerQuery::netCollectionSelectRaw())
 *       ->get();
 *
 * Keeping this in one place means a semantic change (e.g. adding a new
 * payment mode) happens in exactly one spot, and the Stats "Sales" tile
 * stays byte-identical to any per-branch breakdown.
 */
final class SalesLedgerQuery
{
    /**
     * Payment modes that count as hard collection. Excludes Credit Line,
     * Adjustment, Voucher, etc. Matches the legacy home-page definition.
     *
     * @var list<string>
     */
    public const VALID_PAYMENT_MODES = ['Cash', 'Card', 'Bank/Wire Transfer'];

    /**
     * Base builder joined with payment_modes and scoped to the account +
     * date range. Callers chain additional constraints / group-bys.
     */
    public static function forRange(int $accountId, string $startDate, string $endDate): Builder
    {
        // Compare against literal timestamp bounds rather than whereDate().
        // whereDate() wraps the column in DATE() which kills index usage on
        // pa.created_at — the dashboard's stats card was paying for a full
        // package_advances scan on every call. Plain range comparisons let
        // MySQL use the (account_id, created_at) index path.
        return DB::table('package_advances as pa')
            ->join('payment_modes as pm', 'pa.payment_mode_id', '=', 'pm.id')
            ->where('pa.account_id', $accountId)
            ->where('pa.created_at', '>=', $startDate.' 00:00:00')
            ->where('pa.created_at', '<=', $endDate.' 23:59:59')
            ->where('pa.is_adjustment', 0)
            ->where('pa.is_tax', 0)
            ->where('pa.is_cancel', 0)
            ->where('pa.cash_amount', '!=', 0)
            ->whereNull('pa.deleted_at');
    }

    /**
     * Same as forRange() but additionally applies the branch / doctor
     * scoping carried on a MetricScope. Preferred entry point for dashboard
     * metrics so scope handling is consistent across widgets.
     */
    public static function forScope(MetricScope $scope, DateRange $range): Builder
    {
        $query = self::forRange($scope->accountId, $range->startString(), $range->endString());

        if ($scope->isBranchScoped() && $scope->branchIds !== null) {
            $query->whereIn('pa.location_id', $scope->branchIds);
        }

        if ($scope->isResourceScoped() && $scope->resourceType === ResourceType::Doctor) {
            $query->where('pa.doctor_id', $scope->resourceId);
        }

        return $query;
    }

    /**
     * SQL fragment that computes net collection (cash-mode in, minus
     * refund outs). Alias it yourself, e.g.
     *   ->selectRaw(SalesLedgerQuery::netCollectionSelectRaw() . ' AS sales')
     * Only valid on queries built from forRange() / forScope() (which
     * join `payment_modes as pm`).
     */
    public static function netCollectionSelectRaw(): string
    {
        $modes = "'".implode("','", self::VALID_PAYMENT_MODES)."'";

        return "COALESCE(SUM(CASE WHEN pa.cash_flow = 'in' AND pm.name IN ({$modes}) THEN pa.cash_amount ELSE 0 END), 0)"
            .' - '
            ."COALESCE(SUM(CASE WHEN pa.cash_flow = 'out' AND pa.is_refund = 1 THEN pa.cash_amount ELSE 0 END), 0)";
    }

    /**
     * Scalar net collection for the given scope + range. Consolidates the
     * forScope + netCollectionSelectRaw pattern used by the Stats tile and
     * the Sales Momentum deltas so both stay byte-identical.
     */
    public static function totalNet(MetricScope $scope, DateRange $range): float
    {
        $value = self::forScope($scope, $range)
            ->selectRaw(self::netCollectionSelectRaw().' AS sales')
            ->value('sales');

        return round((float) ($value ?? 0), 2);
    }
}
