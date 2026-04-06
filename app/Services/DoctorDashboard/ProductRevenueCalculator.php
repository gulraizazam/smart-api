<?php

declare(strict_types=1);
namespace App\Services\DoctorDashboard;

use Illuminate\Support\Facades\DB;

class ProductRevenueCalculator
{
    /**
     * Calculate product revenue for a doctor in a date range.
     *
     * Uses orders.total_price (includes discount) attributed via prescribed_by = doctor_id.
     *
     * @param int $doctorId
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @return array
     */
    public function calculate(int $doctorId, string $startDate, string $endDate): array
    {
        $result = DB::table('orders')
            ->where('prescribed_by', $doctorId)
            ->where('order_type', 'sale')
            ->where('status', 1)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                DB::raw('SUM(total_price) as total_revenue'),
                DB::raw('COUNT(*) as total_orders')
            )
            ->first();

        return [
            'product_revenue' => round((float) ($result->total_revenue ?? 0), 2),
            'total_orders' => (int) ($result->total_orders ?? 0),
        ];
    }

    /**
     * Calculate product revenue for multiple doctors (benchmark).
     *
     * @param array $doctorIds
     * @param string $startDate
     * @param string $endDate
     * @return array [doctorId => product_revenue]
     */
    public function calculateForDoctors(array $doctorIds, string $startDate, string $endDate): array
    {
        if (empty($doctorIds)) {
            return [];
        }

        return DB::table('orders')
            ->whereIn('prescribed_by', $doctorIds)
            ->where('order_type', 'sale')
            ->where('status', 1)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select('prescribed_by as doctor_id', DB::raw('SUM(total_price) as total'))
            ->groupBy('prescribed_by')
            ->pluck('total', 'doctor_id')
            ->map(fn($v) => round((float) $v, 2))
            ->toArray();
    }

    /**
     * Get highest product revenue month in last N months.
     *
     * @param int $doctorId
     * @param int $months
     * @return array|null
     */
    public function getHighestRevenueMonth(int $doctorId, int $months = 6): ?array
    {
        $startDate = now()->subMonths($months)->startOfMonth()->format('Y-m-d');

        $result = DB::table('orders')
            ->where('prescribed_by', $doctorId)
            ->where('order_type', 'sale')
            ->where('status', 1)
            ->where('created_at', '>=', $startDate . ' 00:00:00')
            ->select(
                DB::raw('YEAR(created_at) as yr'),
                DB::raw('MONTH(created_at) as mn'),
                DB::raw('SUM(total_price) as total_revenue')
            )
            ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
            ->orderByDesc('total_revenue')
            ->first();

        if (!$result || (float) $result->total_revenue === 0.0) {
            return null;
        }

        return [
            'year' => (int) $result->yr,
            'month' => (int) $result->mn,
            'total_revenue' => round((float) $result->total_revenue, 2),
        ];
    }
}
