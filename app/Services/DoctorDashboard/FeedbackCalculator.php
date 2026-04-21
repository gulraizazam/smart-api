<?php

declare(strict_types=1);

namespace App\Services\DoctorDashboard;

use App\Models\Feedback;
use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Support\Facades\DB;

class FeedbackCalculator
{
    use ParsesDateRange;

    /**
     * Calculate average feedback score for a doctor in a date range.
     * Rating scale: 1-10, stored per treatment/procedure.
     * Excludes today's data to match dashboard behavior.
     */
    public function calculate(int $doctorId, string $startDate, string $endDate): array
    {
        $endDate = $this->excludeToday($endDate);

        $result = Feedback::where('doctor_id', $doctorId)
            ->whereBetween('created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->selectRaw('ROUND(AVG(CAST(rating AS DECIMAL(4,2))), 2) as avg_rating, COUNT(*) as total_feedback')
            ->first();

        return [
            'avg_rating' => $result?->avg_rating ? (float) $result->avg_rating : 0.0,
            'total_feedback' => $result ? (int) $result->total_feedback : 0,
        ];
    }

    /**
     * Calculate feedback scores for multiple doctors (benchmark).
     *
     * @param  array<int>  $doctorIds
     * @return array<int, float> [doctorId => avg_rating]
     */
    public function calculateForDoctors(array $doctorIds, string $startDate, string $endDate): array
    {
        if (empty($doctorIds)) {
            return [];
        }

        $endDate = $this->excludeToday($endDate);

        return Feedback::whereIn('doctor_id', $doctorIds)
            ->whereBetween('created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->select('doctor_id')
            ->selectRaw('ROUND(AVG(CAST(rating AS DECIMAL(4,2))), 2) as avg_rating')
            ->groupBy('doctor_id')
            ->pluck('avg_rating', 'doctor_id')
            ->map(fn (mixed $v): float => (float) $v)
            ->toArray();
    }

    /**
     * Get the highest feedback score month in the last N months.
     * Excludes today to match dashboard and reports behavior.
     */
    public function getHighestScoreMonth(int $doctorId, int $months = 6): ?array
    {
        $startDate = now()->subMonths($months)->startOfMonth()->format('Y-m-d');
        $endDate = now()->subDay()->format('Y-m-d');

        $result = Feedback::where('doctor_id', $doctorId)
            ->whereBetween('created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->select(
                DB::raw('YEAR(created_at) as yr'),
                DB::raw('MONTH(created_at) as mn'),
            )
            ->selectRaw('ROUND(AVG(CAST(rating AS DECIMAL(4,2))), 2) as avg_rating, COUNT(*) as total_feedback')
            ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
            ->having('total_feedback', '>=', 1)
            ->orderByDesc('avg_rating')
            ->first();

        if (! $result) {
            return null;
        }

        return [
            'year' => (int) $result->yr,
            'month' => (int) $result->mn,
            'avg_rating' => (float) $result->avg_rating,
            'total_feedback' => (int) $result->total_feedback,
        ];
    }

    /**
     * Adjust end date to exclude today's data. Thin wrapper around the
     * shared {@see ParsesDateRange::excludeTodayFromRange()} helper so
     * the feedback chart / report / lifetime calculator all clamp the
     * same way.
     */
    private function excludeToday(string $endDate): string
    {
        return self::excludeTodayFromRange([null, $endDate])[1];
    }
}
