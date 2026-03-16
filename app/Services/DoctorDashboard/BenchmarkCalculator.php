<?php

namespace App\Services\DoctorDashboard;

class BenchmarkCalculator
{
    /**
     * Minimum consultations required to qualify for benchmark pool.
     */
    const MIN_CONSULTATIONS = 5;

    private DoctorIdentifier $doctorIdentifier;
    private ConversionCalculator $conversionCalculator;
    private RevenueCalculator $revenueCalculator;
    private UpsellCalculator $upsellCalculator;
    private FeedbackCalculator $feedbackCalculator;
    private ProductRevenueCalculator $productRevenueCalculator;
    private MembershipCalculator $membershipCalculator;

    public function __construct(
        DoctorIdentifier $doctorIdentifier,
        ConversionCalculator $conversionCalculator,
        RevenueCalculator $revenueCalculator,
        UpsellCalculator $upsellCalculator,
        FeedbackCalculator $feedbackCalculator,
        ProductRevenueCalculator $productRevenueCalculator,
        MembershipCalculator $membershipCalculator
    ) {
        $this->doctorIdentifier = $doctorIdentifier;
        $this->conversionCalculator = $conversionCalculator;
        $this->revenueCalculator = $revenueCalculator;
        $this->upsellCalculator = $upsellCalculator;
        $this->feedbackCalculator = $feedbackCalculator;
        $this->productRevenueCalculator = $productRevenueCalculator;
        $this->membershipCalculator = $membershipCalculator;
    }

    /**
     * Calculate nationwide benchmarks for all KPIs.
     *
     * Benchmark pool: all active doctors with doctor roles, is_allocated=1,
     * minimum 5 consultations in the period.
     *
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @param int $accountId
     * @return array
     */
    public function calculate(string $startDate, string $endDate, int $accountId): array
    {
        $allDoctorIds = $this->doctorIdentifier->getAllActiveDoctorIds($accountId);

        if (empty($allDoctorIds)) {
            return $this->emptyResult();
        }

        // Get conversion data for all doctors (filters by min consultations)
        $conversionData = $this->conversionCalculator->calculateForDoctors(
            $allDoctorIds, $startDate, $endDate, $accountId, self::MIN_CONSULTATIONS
        );

        // Only include doctors who meet the min consultation threshold
        $qualifiedDoctorIds = array_keys($conversionData);

        if (empty($qualifiedDoctorIds)) {
            return $this->emptyResult();
        }

        // Get revenue for qualified doctors
        $revenueData = $this->revenueCalculator->calculateForDoctors(
            $qualifiedDoctorIds, $startDate, $endDate, $accountId
        );

        // Get upsell for qualified doctors
        $upsellData = $this->upsellCalculator->calculateForDoctors(
            $qualifiedDoctorIds, $startDate, $endDate, $accountId
        );

        // Get feedback for qualified doctors
        $feedbackData = $this->feedbackCalculator->calculateForDoctors(
            $qualifiedDoctorIds, $startDate, $endDate
        );

        // Get product revenue for qualified doctors
        $productData = $this->productRevenueCalculator->calculateForDoctors(
            $qualifiedDoctorIds, $startDate, $endDate
        );

        // Get membership counts for qualified doctors
        $membershipData = $this->membershipCalculator->calculateForDoctors(
            $qualifiedDoctorIds, $startDate, $endDate, $accountId
        );

        // Calculate averages
        $doctorCount = count($qualifiedDoctorIds);

        $conversionRates = array_column($conversionData, 'conversion_rate');
        $revenues = array_values($revenueData);
        $upsells = array_values($upsellData);
        $feedbacks = array_values($feedbackData);
        $products = array_values($productData);
        $memberships = array_values($membershipData);

        // Calculate avg client values
        $avgClientValues = [];
        foreach ($conversionData as $docId => $conv) {
            $rev = $revenueData[$docId] ?? 0;
            if ($conv['total_converted'] > 0) {
                $avgClientValues[] = $rev / $conv['total_converted'];
            }
        }

        return [
            'doctor_count' => $doctorCount,
            'conversion_rate' => $this->stats($conversionRates),
            'total_revenue' => $this->stats($revenues),
            'avg_client_value' => $this->stats($avgClientValues),
            'upsell_revenue' => $this->stats($upsells),
            'feedback_score' => $this->stats($feedbacks),
            'product_revenue' => $this->stats($products),
            'gold_memberships' => $this->stats($memberships),
        ];
    }

    /**
     * Calculate statistics (avg, median, min, max) for an array of values.
     *
     * @param array $values
     * @return array
     */
    private function stats(array $values): array
    {
        if (empty($values)) {
            return ['avg' => 0, 'median' => 0, 'min' => 0, 'max' => 0];
        }

        sort($values);
        $count = count($values);
        $sum = array_sum($values);

        $median = $count % 2 === 0
            ? ($values[$count / 2 - 1] + $values[$count / 2]) / 2
            : $values[floor($count / 2)];

        return [
            'avg' => round($sum / $count, 2),
            'median' => round($median, 2),
            'min' => round(min($values), 2),
            'max' => round(max($values), 2),
        ];
    }

    /**
     * @return array
     */
    private function emptyResult(): array
    {
        return [
            'doctor_count' => 0,
            'conversion_rate' => $this->stats([]),
            'total_revenue' => $this->stats([]),
            'avg_client_value' => $this->stats([]),
            'upsell_revenue' => $this->stats([]),
            'feedback_score' => $this->stats([]),
            'product_revenue' => $this->stats([]),
            'gold_memberships' => $this->stats([]),
        ];
    }
}
