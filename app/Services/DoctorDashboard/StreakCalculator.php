<?php

declare(strict_types=1);
namespace App\Services\DoctorDashboard;

use Carbon\Carbon;

class StreakCalculator
{
    /**
     * Conversion threshold for streak qualification.
     */
    const CONVERSION_THRESHOLD = 50.0;

    /**
     * Number of months to look back for streak history.
     */
    const LOOKBACK_MONTHS = 6;

    /**
     * @var ConversionCalculator
     */
    public function __construct(
        private readonly ConversionCalculator $conversionCalculator,
    ) {}

    /**
     * Calculate current streak and best streak for a doctor.
     *
     * Streak = consecutive weeks (Sun-Sat) with >= 50% conversion rate.
     * No consultations in a week = pause (neither continues nor resets).
     * < 50% conversion with consultations = reset to 0.
     *
     * @param int $doctorId
     * @param int $accountId
     * @return array
     */
    public function calculate(int $doctorId, int $accountId): array
    {
        $weeks = $this->getWeekRanges(self::LOOKBACK_MONTHS);
        $weekResults = [];

        foreach ($weeks as $week) {
            $result = $this->conversionCalculator->calculateWeekly(
                $doctorId,
                $week['start'],
                $week['end'],
                $accountId
            );
            $weekResults[] = [
                'start' => $week['start'],
                'end' => $week['end'],
                'result' => $result, // null = no consultations
            ];
        }

        return $this->computeStreaks($weekResults);
    }

    /**
     * Compute current and best streaks from weekly results.
     *
     * @param array $weekResults
     * @return array
     */
    private function computeStreaks(array $weekResults): array
    {
        $currentStreak = 0;
        $bestStreak = 0;
        $tempStreak = 0;

        foreach ($weekResults as $week) {
            if ($week['result'] === null) {
                // No consultations — streak pauses, don't reset or continue
                continue;
            }

            if ($week['result']['conversion_rate'] >= self::CONVERSION_THRESHOLD) {
                $tempStreak++;
                if ($tempStreak > $bestStreak) {
                    $bestStreak = $tempStreak;
                }
            } else {
                $tempStreak = 0;
            }
        }

        // Current streak: count backwards from the most recent qualifying week
        $currentStreak = 0;
        for ($i = count($weekResults) - 1; $i >= 0; $i--) {
            $result = $weekResults[$i]['result'];

            if ($result === null) {
                // Pause — skip and continue counting backwards
                continue;
            }

            if ($result['conversion_rate'] >= self::CONVERSION_THRESHOLD) {
                $currentStreak++;
            } else {
                // Reset — stop counting
                break;
            }
        }

        return [
            'current_streak' => $currentStreak,
            'best_streak' => $bestStreak,
        ];
    }

    /**
     * Generate week ranges (Sunday to Saturday) for the last N months.
     *
     * @param int $months
     * @return array [['start' => 'Y-m-d', 'end' => 'Y-m-d'], ...]
     */
    private function getWeekRanges(int $months): array
    {
        $weeks = [];
        $start = Carbon::now()->subMonths($months)->startOfWeek(Carbon::SUNDAY);
        $end = Carbon::now();

        $current = $start->copy();

        while ($current->lte($end)) {
            $weekStart = $current->format('Y-m-d');
            $weekEnd = $current->copy()->addDays(6)->format('Y-m-d');

            // Don't include weeks that are entirely in the future
            if ($current->lte($end)) {
                $weeks[] = [
                    'start' => $weekStart,
                    'end' => min($weekEnd, $end->format('Y-m-d')),
                ];
            }

            $current->addWeek();
        }

        return $weeks;
    }
}
