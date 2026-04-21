<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Enums\ResourceType;
use App\Services\Dashboard\Contracts\Metric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use App\Services\DoctorDashboard\ProductRevenueCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Product (retail) revenue across any scope.
 *
 * Doctor scope → delegates to ProductRevenueCalculator (attribution via
 * orders.prescribed_by). Branch / company scope → single SQL aggregation
 * on orders with same filters, optionally scoped to doctors in branch.
 *
 * Return shape: ['product_revenue' => float, 'total_orders' => int]
 */
final class ProductRevenueMetric implements Metric
{
    public function __construct(
        private readonly ProductRevenueCalculator $calculator,
    ) {}

    /**
     * @return array{product_revenue: float, total_orders: int}
     */
    public function compute(MetricScope $scope, DateRange $range): array
    {
        if ($scope->isDenyAll()) {
            return $this->empty();
        }

        if ($scope->isResourceScoped() && $scope->resourceType === ResourceType::Doctor) {
            return $this->calculator->calculate(
                (int) $scope->resourceId,
                $range->startString(),
                $range->endString(),
            );
        }

        $start = $range->startString().' 00:00:00';
        $end = $range->endString().' 23:59:59';

        $query = DB::table('orders')
            ->where('order_type', 'sale')
            ->where('status', 1)
            ->whereBetween('created_at', [$start, $end]);

        if ($scope->isBranchScoped()) {
            $doctorIds = $this->doctorsInBranches($scope->branchIds ?? []);
            if ($doctorIds === []) {
                return $this->empty();
            }
            $query->whereIn('prescribed_by', $doctorIds);
        }

        $row = $query
            ->selectRaw('COALESCE(SUM(total_price), 0) as total_revenue, COUNT(*) as total_orders')
            ->first();

        return [
            'product_revenue' => round((float) ($row->total_revenue ?? 0), 2),
            'total_orders' => (int) ($row->total_orders ?? 0),
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<int>
     */
    private function doctorsInBranches(array $branchIds): array
    {
        return array_values(array_map(
            'intval',
            DB::table('doctor_has_locations')
                ->whereIn('location_id', $branchIds)
                ->where('is_allocated', 1)
                ->distinct()
                ->pluck('user_id')
                ->toArray(),
        ));
    }

    /**
     * @return array{product_revenue: float, total_orders: int}
     */
    private function empty(): array
    {
        return ['product_revenue' => 0.0, 'total_orders' => 0];
    }
}
