<?php

namespace App\Services\DoctorDashboard;

use Illuminate\Support\Facades\DB;

class FeedbackCalculator
{
    /**
     * Calculate average feedback score for a doctor in a date range.
     *
     * Rating scale: 1-10, stored per treatment/procedure.
     *
     * @param int $doctorId
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @return array
     */
    public function calculate(int $doctorId, string $startDate, string $endDate): array
    {
        // Exclude today: if endDate is today or later, use yesterday
        $today = now()->format('Y-m-d');
        if ($endDate >= $today) {
            $endDate = now()->subDay()->format('Y-m-d');
        }

        $result = DB::table('feedback')
            ->where('doctor_id', $doctorId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                DB::raw('AVG(CAST(rating AS DECIMAL(4,2))) as avg_rating'),
                DB::raw('COUNT(*) as total_feedback')
            )
            ->first();

        $avgRating = $result && $result->avg_rating ? round((float) $result->avg_rating, 2) : 0;
        $totalFeedback = $result ? (int) $result->total_feedback : 0;

        return [
            'avg_rating' => $avgRating,
            'total_feedback' => $totalFeedback,
        ];
    }

    /**
     * Calculate feedback scores for multiple doctors (benchmark).
     *
     * @param array $doctorIds
     * @param string $startDate
     * @param string $endDate
     * @return array [doctorId => avg_rating]
     */
    public function calculateForDoctors(array $doctorIds, string $startDate, string $endDate): array
    {
        if (empty($doctorIds)) {
            return [];
        }

        // Exclude today: if endDate is today or later, use yesterday
        $today = now()->format('Y-m-d');
        if ($endDate >= $today) {
            $endDate = now()->subDay()->format('Y-m-d');
        }

        return DB::table('feedback')
            ->whereIn('doctor_id', $doctorIds)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select('doctor_id', DB::raw('AVG(CAST(rating AS DECIMAL(4,2))) as avg_rating'))
            ->groupBy('doctor_id')
            ->pluck('avg_rating', 'doctor_id')
            ->map(fn($v) => round((float) $v, 2))
            ->toArray();
    }

    /**
     * Get the highest feedback score month in the last N months.
     * Excludes today to match dashboard and reports behavior.
     *
     * @param int $doctorId
     * @param int $months
     * @return array|null
     */
    public function getHighestScoreMonth(int $doctorId, int $months = 6): ?array
    {
        $startDate = now()->subMonths($months)->startOfMonth()->format('Y-m-d');
        // Exclude today - use yesterday as the end date
        $endDate = now()->subDay()->format('Y-m-d');

        $result = DB::table('feedback')
            ->where('doctor_id', $doctorId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                DB::raw('YEAR(created_at) as yr'),
                DB::raw('MONTH(created_at) as mn'),
                DB::raw('AVG(CAST(rating AS DECIMAL(4,2))) as avg_rating'),
                DB::raw('COUNT(*) as total_feedback')
            )
            ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
            ->having('total_feedback', '>=', 1)
            ->orderByDesc('avg_rating')
            ->first();

        if (!$result) {
            return null;
        }

        return [
            'year' => (int) $result->yr,
            'month' => (int) $result->mn,
            'avg_rating' => round((float) $result->avg_rating, 2),
            'total_feedback' => (int) $result->total_feedback,
        ];
    }
}
