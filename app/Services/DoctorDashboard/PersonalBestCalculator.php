<?php

namespace App\Services\DoctorDashboard;

use App\Helpers\DoctorDashboardHelper;
use App\Models\DoctorGoogleReview;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PersonalBestCalculator
{
    /**
     * Rolling lookback period in months.
     */
    const LOOKBACK_MONTHS = 6;

    private ConversionCalculator $conversionCalculator;
    private RevenueCalculator $revenueCalculator;
    private UpsellCalculator $upsellCalculator;
    private FeedbackCalculator $feedbackCalculator;
    private StreakCalculator $streakCalculator;

    public function __construct(
        ConversionCalculator $conversionCalculator,
        RevenueCalculator $revenueCalculator,
        UpsellCalculator $upsellCalculator,
        FeedbackCalculator $feedbackCalculator,
        StreakCalculator $streakCalculator
    ) {
        $this->conversionCalculator = $conversionCalculator;
        $this->revenueCalculator = $revenueCalculator;
        $this->upsellCalculator = $upsellCalculator;
        $this->feedbackCalculator = $feedbackCalculator;
        $this->streakCalculator = $streakCalculator;
    }

    /**
     * Calculate all 7 personal bests for a doctor (rolling last 6 months).
     *
     * 1. Highest revenue month
     * 2. Highest conversion rate month
     * 3. Highest upsell amount month
     * 4. Most patients seen in a single day
     * 5. Longest streak ever (in last 6 months)
     * 6. Highest feedback score month
     * 7. Most Google reviews in a single month
     *
     * @param int $doctorId
     * @param int $accountId
     * @return array
     */
    public function calculate(int $doctorId, int $accountId): array
    {
        $months = $this->getMonthRanges(self::LOOKBACK_MONTHS);

        return [
            'highest_revenue' => $this->highestRevenueMonth($doctorId, $months, $accountId),
            'highest_conversion' => $this->highestConversionMonth($doctorId, $months, $accountId),
            'highest_upsell' => $this->highestUpsellMonth($doctorId, $months, $accountId),
            'most_patients_day' => $this->mostPatientsSingleDay($doctorId, $accountId),
            'longest_streak' => $this->longestStreak($doctorId, $accountId),
            'highest_feedback' => $this->feedbackCalculator->getHighestScoreMonth($doctorId, self::LOOKBACK_MONTHS),
            'most_google_reviews' => $this->mostGoogleReviewsMonth($doctorId, $accountId),
        ];
    }

    /**
     * Highest revenue month in last 6 months.
     */
    private function highestRevenueMonth(int $doctorId, array $months, int $accountId): ?array
    {
        $best = null;

        foreach ($months as $m) {
            $result = $this->revenueCalculator->calculate($doctorId, $m['start'], $m['end'], $accountId);

            if ($result['total_revenue'] > 0 && (!$best || $result['total_revenue'] > $best['value'])) {
                $best = [
                    'value' => $result['total_revenue'],
                    'label' => Carbon::parse($m['start'])->format('M Y'),
                    'month' => (int) Carbon::parse($m['start'])->format('m'),
                    'year' => (int) Carbon::parse($m['start'])->format('Y'),
                ];
            }
        }

        return $best;
    }

    /**
     * Highest conversion rate month in last 6 months.
     */
    private function highestConversionMonth(int $doctorId, array $months, int $accountId): ?array
    {
        $best = null;

        foreach ($months as $m) {
            $result = $this->conversionCalculator->calculate($doctorId, $m['start'], $m['end'], $accountId);

            if ($result['total_arrived'] > 0 && (!$best || $result['conversion_rate'] > $best['value'])) {
                $best = [
                    'value' => $result['conversion_rate'],
                    'label' => Carbon::parse($m['start'])->format('M Y'),
                    'month' => (int) Carbon::parse($m['start'])->format('m'),
                    'year' => (int) Carbon::parse($m['start'])->format('Y'),
                ];
            }
        }

        return $best;
    }

    /**
     * Highest upsell amount month in last 6 months.
     */
    private function highestUpsellMonth(int $doctorId, array $months, int $accountId): ?array
    {
        $best = null;

        foreach ($months as $m) {
            $result = $this->upsellCalculator->calculate($doctorId, $m['start'], $m['end'], $accountId);

            if ($result['upsell_revenue'] > 0 && (!$best || $result['upsell_revenue'] > $best['value'])) {
                $best = [
                    'value' => $result['upsell_revenue'],
                    'label' => Carbon::parse($m['start'])->format('M Y'),
                    'month' => (int) Carbon::parse($m['start'])->format('m'),
                    'year' => (int) Carbon::parse($m['start'])->format('Y'),
                ];
            }
        }

        return $best;
    }

    /**
     * Most patients seen in a single day (consultations + treatments arrived) in last 6 months.
     */
    private function mostPatientsSingleDay(int $doctorId, int $accountId): ?array
    {
        $startDate = Carbon::now()->subMonths(self::LOOKBACK_MONTHS)->startOfMonth()->format('Y-m-d');
        $arrivedStatusId = DoctorDashboardHelper::getArrivedStatusId($accountId);
        $convertedStatusId = DoctorDashboardHelper::getConvertedStatusId($accountId);

        if (!$arrivedStatusId) {
            return null;
        }

        $statusIds = array_filter([$arrivedStatusId, $convertedStatusId]);

        // Count unique patients per day (consultations type=1 arrived + treatments type=2 arrived)
        $result = DB::table('appointments')
            ->where('doctor_id', $doctorId)
            ->whereIn('appointment_type_id', [1, 2])
            ->whereIn('base_appointment_status_id', $statusIds)
            ->where('scheduled_date', '>=', $startDate)
            ->select(
                'scheduled_date',
                DB::raw('COUNT(DISTINCT patient_id) as patient_count')
            )
            ->groupBy('scheduled_date')
            ->orderByDesc('patient_count')
            ->first();

        if (!$result || (int) $result->patient_count === 0) {
            return null;
        }

        return [
            'value' => (int) $result->patient_count,
            'label' => Carbon::parse($result->scheduled_date)->format('d M Y'),
            'date' => $result->scheduled_date,
        ];
    }

    /**
     * Longest streak in last 6 months.
     */
    private function longestStreak(int $doctorId, int $accountId): ?array
    {
        $streakData = $this->streakCalculator->calculate($doctorId, $accountId);

        if ($streakData['best_streak'] === 0) {
            return null;
        }

        return [
            'value' => $streakData['best_streak'],
            'label' => $streakData['best_streak'] . ' Weeks',
        ];
    }

    /**
     * Most Google reviews in a single month in last 6 months.
     */
    private function mostGoogleReviewsMonth(int $doctorId, int $accountId): ?array
    {
        $startDate = Carbon::now()->subMonths(self::LOOKBACK_MONTHS)->startOfMonth();

        $result = DB::table('doctor_google_reviews')
            ->where('doctor_id', $doctorId)
            ->where('account_id', $accountId)
            ->where(function ($q) use ($startDate) {
                $q->where('year', '>', $startDate->year)
                    ->orWhere(function ($q2) use ($startDate) {
                        $q2->where('year', $startDate->year)
                            ->where('month', '>=', $startDate->month);
                    });
            })
            ->where('review_count', '>', 0)
            ->orderByDesc('review_count')
            ->first();

        if (!$result) {
            return null;
        }

        return [
            'value' => (int) $result->review_count,
            'label' => Carbon::createFromDate($result->year, $result->month, 1)->format('M Y'),
            'month' => (int) $result->month,
            'year' => (int) $result->year,
        ];
    }

    /**
     * Generate month ranges for the last N months.
     *
     * @param int $months
     * @return array [['start' => 'Y-m-d', 'end' => 'Y-m-d'], ...]
     */
    private function getMonthRanges(int $months): array
    {
        $ranges = [];
        for ($i = $months; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $ranges[] = [
                'start' => $date->copy()->startOfMonth()->format('Y-m-d'),
                'end' => $i === 0 ? Carbon::now()->format('Y-m-d') : $date->copy()->endOfMonth()->format('Y-m-d'),
            ];
        }
        return $ranges;
    }
}
