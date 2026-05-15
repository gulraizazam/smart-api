<?php

declare(strict_types=1);
namespace App\Services\DoctorDashboard;

use App\Enums\AppointmentType;
use App\Helpers\DoctorDashboardHelper;
use App\Models\DoctorGoogleReview;
use App\Support\OperatingDays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BenchmarkCalculator
{
    /**
     * Minimum consultations required to qualify for benchmark pool.
     */
    const MIN_CONSULTATIONS = 5;

    /**
     * Cache TTL in seconds (1 hour).
     */
    const CACHE_TTL = 3600;

    public function __construct(
        private readonly DoctorIdentifier $doctorIdentifier,
        private readonly ConversionCalculator $conversionCalculator,
        private readonly RevenueCalculator $revenueCalculator,
        private readonly UpsellCalculator $upsellCalculator,
        private readonly FeedbackCalculator $feedbackCalculator,
        private readonly ProductRevenueCalculator $productRevenueCalculator,
        private readonly MembershipCalculator $membershipCalculator,
        private readonly PatientReturnCalculator $patientReturnCalculator,
    ) {}

    /**
     * Calculate best-in-network benchmarks for all KPIs.
     *
     * Returns the best (max) value for each metric across all active doctors,
     * plus gap/message info relative to the viewing doctor's own values.
     *
     * @param int $doctorId The viewing doctor's user ID
     * @param array $doctorKpis The viewing doctor's current KPI values
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @param int $accountId
     * @return array
     */
    public function calculate(int $doctorId, array $doctorKpis, string $startDate, string $endDate, int $accountId): array
    {
        $cacheKey = $this->buildCacheKey($startDate, $accountId);

        // Get or compute the raw best values (shared across all doctors)
        $bestValues = Cache::remember($cacheKey, self::CACHE_TTL, fn() => $this->computeAllBestValues($startDate, $endDate, $accountId));

        if (empty($bestValues) || ($bestValues['doctor_count'] ?? 0) === 0) {
            return $this->emptyResult();
        }

        // Build per-metric response with gap and message relative to this doctor
        return $this->buildResponse($doctorId, $doctorKpis, $bestValues);
    }

    /**
     * Compute best values for all metrics across all active doctors.
     * This is the expensive operation that gets cached.
     */
    private function computeAllBestValues(string $startDate, string $endDate, int $accountId): array
    {
        $allDoctorIds = $this->doctorIdentifier->getAllActiveDoctorIds($accountId);

        if (empty($allDoctorIds)) {
            return ['doctor_count' => 0];
        }

        // --- Complex metrics (require per-doctor calculation) ---

        // Conversion data (filters by min consultations to qualify)
        $conversionData = $this->conversionCalculator->calculateForDoctors(
            $allDoctorIds, $startDate, $endDate, $accountId, self::MIN_CONSULTATIONS
        );

        $qualifiedDoctorIds = array_keys($conversionData);

        if (empty($qualifiedDoctorIds)) {
            return ['doctor_count' => 0];
        }

        // Revenue (uses validated conversion spend per doctor)
        $revenueData = $this->revenueCalculator->calculateForDoctors(
            $qualifiedDoctorIds, $startDate, $endDate, $accountId
        );

        // Upsell revenue per doctor
        $upsellRevenueData = $this->upsellCalculator->calculateForDoctors(
            $qualifiedDoctorIds, $startDate, $endDate, $accountId
        );

        // Feedback scores per doctor
        $feedbackData = $this->feedbackCalculator->calculateForDoctors(
            $qualifiedDoctorIds, $startDate, $endDate
        );

        // Product revenue per doctor
        $productData = $this->productRevenueCalculator->calculateForDoctors(
            $qualifiedDoctorIds, $startDate, $endDate
        );

        // Gold memberships per doctor
        $membershipData = $this->membershipCalculator->calculateForDoctors(
            $qualifiedDoctorIds, $startDate, $endDate, $accountId
        );

        // Avg client values (revenue / converted)
        $avgClientValues = [];
        foreach ($conversionData as $docId => $conv) {
            $rev = $revenueData[$docId] ?? 0;
            if ($conv['total_converted'] > 0) {
                $avgClientValues[$docId] = round($rev / $conv['total_converted'], 2);
            }
        }

        // --- Simple metrics (direct aggregate SQL) ---

        $consultationStatusIds = DoctorDashboardHelper::getConsultationStatusIds();
        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();

        // Patients seen per doctor (consultations + treatments)
        $patientsSeen = $this->getPatientSeenPerDoctor($qualifiedDoctorIds, $consultationStatusIds, $treatmentStatusIds, $startDate, $endDate);

        // Upsell rate per doctor (unique upsold / unique treated × 100)
        $upsellRates = $this->getUpsellRatePerDoctor($qualifiedDoctorIds, $treatmentStatusIds, $startDate, $endDate);

        // Return rate per doctor — always rolling 45 days
        $rolling45Start = now()->subDays(45)->format('Y-m-d');
        $rolling45End = now()->format('Y-m-d');
        $returnRates = $this->getReturnRatePerDoctor($qualifiedDoctorIds, $treatmentStatusIds, $rolling45Start, $rolling45End);

        // Avg procedures per doctor — always trailing 3 months
        $avgProcedures = $this->getAvgProceduresPerDoctor($qualifiedDoctorIds, $treatmentStatusIds, $accountId);

        // Google reviews per doctor (last month per spec)
        $googleReviews = $this->getGoogleReviewsPerDoctor($qualifiedDoctorIds, $accountId);

        // Revenue per working day per doctor
        $revenuePerDay = $this->getRevenuePerDayPerDoctor($revenueData, $startDate, $endDate, $accountId);

        return [
            'doctor_count' => count($qualifiedDoctorIds),
            'total_revenue' => $this->bestMax($revenueData),
            'conversion_rate' => $this->bestMax(array_combine(array_keys($conversionData), array_column($conversionData, 'conversion_rate'))),
            'avg_client_value' => $this->bestMax($avgClientValues),
            'product_revenue' => $this->bestMax($productData),
            'upsell_revenue' => $this->bestMax($upsellRevenueData),
            'upsell_rate' => $this->bestMax($upsellRates),
            'gold_memberships' => $this->bestMax($membershipData),
            'feedback_score' => $this->bestMax($feedbackData),
            'google_reviews' => $this->bestMax($googleReviews),
            'patient_return_rate' => $this->bestMax($returnRates),
            'avg_procedures' => $this->bestMax($avgProcedures),
            'patients_seen' => $this->bestMax($patientsSeen),
            'revenue_per_day' => $this->bestMax($revenuePerDay),
        ];
    }

    /**
     * Get patients seen (consultations + treatments) per doctor via aggregate SQL.
     */
    private function getPatientSeenPerDoctor(array $doctorIds, array $consultationStatusIds, array $treatmentStatusIds, string $startDate, string $endDate): array
    {
        // Consultations count per doctor
        $consultations = DB::table('appointments')
            ->whereIn('doctor_id', $doctorIds)
            ->where('appointment_type_id', 1)
            ->whereIn('appointment_status_id', $consultationStatusIds)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->select('doctor_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('doctor_id')
            ->pluck('cnt', 'doctor_id')
            ->toArray();

        // Treatments count per doctor
        $treatments = DB::table('appointments')
            ->whereIn('doctor_id', $doctorIds)
            ->where('appointment_type_id', 2)
            ->whereIn('appointment_status_id', $treatmentStatusIds)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->select('doctor_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('doctor_id')
            ->pluck('cnt', 'doctor_id')
            ->toArray();

        // Sum per doctor
        $result = [];
        foreach ($doctorIds as $docId) {
            $total = ($consultations[$docId] ?? 0) + ($treatments[$docId] ?? 0);
            if ($total > 0) {
                $result[$docId] = $total;
            }
        }

        return $result;
    }

    /**
     * Get upsell rate per doctor.
     * Upsell Rate = (unique upsold patients / unique treated patients) × 100
     */
    private function getUpsellRatePerDoctor(array $doctorIds, array $treatmentStatusIds, string $startDate, string $endDate): array
    {
        // Unique treated patients per doctor
        $treatedPerDoc = DB::table('appointments')
            ->whereIn('doctor_id', $doctorIds)
            ->where('appointment_type_id', 2)
            ->whereIn('appointment_status_id', $treatmentStatusIds)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->select('doctor_id', DB::raw('COUNT(DISTINCT patient_id) as cnt'))
            ->groupBy('doctor_id')
            ->pluck('cnt', 'doctor_id')
            ->toArray();

        // Unique upsold patients per doctor
        $upsoldPerDoc = DB::table('package_services as ps')
            ->join('packages as p', 'ps.package_id', '=', 'p.id')
            ->join('appointments as a', 'p.appointment_id', '=', 'a.id')
            ->whereIn('ps.sold_by', $doctorIds)
            // Exclude only self-consultation sales (type=1 where seller == consulting doctor)
            ->where(function ($q) {
                $q->where('a.appointment_type_id', '!=', AppointmentType::Consultancy->value)
                  ->orWhereColumn('a.doctor_id', '!=', 'ps.sold_by');
            })
            ->whereBetween('ps.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select('ps.sold_by as doctor_id', DB::raw('COUNT(DISTINCT a.patient_id) as cnt'))
            ->groupBy('ps.sold_by')
            ->pluck('cnt', 'doctor_id')
            ->toArray();

        $result = [];
        foreach ($doctorIds as $docId) {
            $treated = $treatedPerDoc[$docId] ?? 0;
            $upsold = $upsoldPerDoc[$docId] ?? 0;
            if ($treated > 0) {
                $result[$docId] = round(($upsold / $treated) * 100, 1);
            }
        }

        return $result;
    }

    /**
     * Get return rate per doctor via efficient aggregate SQL.
     * Return rate = unique patients with >1 arrived treatment with the SAME doctor
     *               / total unique treated patients per doctor.
     * Uses rolling 45-day window.
     */
    private function getReturnRatePerDoctor(array $doctorIds, array $treatmentStatusIds, string $startDate, string $endDate): array
    {
        // Total unique patients per doctor
        $totalPerDoc = DB::table('appointments')
            ->whereIn('doctor_id', $doctorIds)
            ->where('appointment_type_id', 2)
            ->whereIn('appointment_status_id', $treatmentStatusIds)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->select('doctor_id', DB::raw('COUNT(DISTINCT patient_id) as total_unique'))
            ->groupBy('doctor_id')
            ->pluck('total_unique', 'doctor_id')
            ->toArray();

        // Patients with >1 treatment with the SAME doctor (returned to same doctor)
        $returnedPerDoc = DB::table('appointments')
            ->whereIn('doctor_id', $doctorIds)
            ->where('appointment_type_id', 2)
            ->whereIn('appointment_status_id', $treatmentStatusIds)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->select('doctor_id', 'patient_id', DB::raw('COUNT(*) as visit_count'))
            ->groupBy('doctor_id', 'patient_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->groupBy('doctor_id')
            ->map(fn($rows) => $rows->count())
            ->toArray();

        $result = [];
        foreach ($doctorIds as $docId) {
            $total = $totalPerDoc[$docId] ?? 0;
            $returned = $returnedPerDoc[$docId] ?? 0;
            if ($total > 0) {
                $result[$docId] = round(($returned / $total) * 100, 1);
            }
        }

        return $result;
    }

    /**
     * Get average procedures per converted patient per doctor (trailing 3 months).
     * Same logic as PatientReturnCalculator::calculateAvgProcedures.
     */
    private function getAvgProceduresPerDoctor(array $doctorIds, array $treatmentStatusIds, int $accountId): array
    {
        $convertedStatusId = DoctorDashboardHelper::getConvertedStatusId($accountId);
        if (!$convertedStatusId) {
            return [];
        }

        $trailing3Start = now()->subMonths(3)->startOfMonth()->format('Y-m-d');
        $today = now()->format('Y-m-d');

        $result = [];

        foreach ($doctorIds as $docId) {
            // Get patients converted by this doctor in trailing 3 months
            $conversions = DB::table('appointments')
                ->where('doctor_id', $docId)
                ->where('appointment_type_id', 1)
                ->where('appointment_status_id', $convertedStatusId)
                ->whereBetween('scheduled_date', [$trailing3Start, $today])
                ->select('patient_id', DB::raw('MIN(scheduled_date) as conversion_date'))
                ->groupBy('patient_id')
                ->get();

            if ($conversions->isEmpty()) {
                continue;
            }

            $totalProcedures = 0;
            foreach ($conversions as $conv) {
                $totalProcedures += DB::table('appointments')
                    ->where('patient_id', $conv->patient_id)
                    ->where('appointment_type_id', 2)
                    ->whereIn('appointment_status_id', $treatmentStatusIds)
                    ->where('scheduled_date', '>=', $conv->conversion_date)
                    ->where('scheduled_date', '<=', $today)
                    ->count();
            }

            $convertedCount = $conversions->count();
            if ($convertedCount > 0) {
                $result[$docId] = round($totalProcedures / $convertedCount, 1);
            }
        }

        return $result;
    }

    /**
     * Get Google reviews per doctor (last month per spec).
     */
    private function getGoogleReviewsPerDoctor(array $doctorIds, int $accountId): array
    {
        $lastMonth = now()->subMonthNoOverflow();
        $month = $lastMonth->month;
        $year = $lastMonth->year;

        $result = [];
        foreach ($doctorIds as $docId) {
            $review = DoctorGoogleReview::getForDoctorMonth($docId, $month, $year, $accountId);
            if ($review && (int) $review->review_count > 0) {
                $result[$docId] = (int) $review->review_count;
            }
        }

        return $result;
    }

    /**
     * Get revenue per operating day per doctor. Operating days are the
     * shared denominator across the doctor pool — closures and per-date
     * exceptions narrow the count via OperatingDays (the same primitive
     * the per-doctor `getRevenuePerDay` and invoicing use).
     */
    private function getRevenuePerDayPerDoctor(array $revenueData, string $startDate, string $endDate, int $accountId): array
    {
        $workingDays = count(OperatingDays::datesInRange(
            $accountId,
            [],
            CarbonImmutable::parse($startDate),
            CarbonImmutable::parse($endDate),
        ));

        if ($workingDays === 0) {
            return [];
        }

        $result = [];
        foreach ($revenueData as $docId => $revenue) {
            $result[$docId] = round((float) $revenue / $workingDays, 0);
        }

        return $result;
    }

    /**
     * Find the best (max) value from a [doctorId => value] array.
     *
     * @param array $data [doctorId => value]
     * @return array ['value' => best_value, 'doctor_id' => best_doctor_id]
     */
    private function bestMax(array $data): array
    {
        if (empty($data)) {
            return ['value' => 0, 'doctor_id' => null];
        }

        $bestDocId = null;
        $bestValue = 0;

        foreach ($data as $docId => $val) {
            $val = (float) $val;
            if ($bestDocId === null || $val > $bestValue) {
                $bestValue = $val;
                $bestDocId = $docId;
            }
        }

        return ['value' => round($bestValue, 2), 'doctor_id' => $bestDocId];
    }

    /**
     * Build the final response with gap% and encouraging message per metric.
     */
    private function buildResponse(int $doctorId, array $doctorKpis, array $bestValues): array
    {
        $metricMap = [
            'total_revenue'      => $doctorKpis['total_revenue'] ?? 0,
            'conversion_rate'    => $doctorKpis['conversion_rate'] ?? 0,
            'avg_client_value'   => $doctorKpis['avg_client_value'] ?? 0,
            'product_revenue'    => $doctorKpis['product_revenue'] ?? 0,
            'upsell_revenue'     => $doctorKpis['upsell_revenue'] ?? 0,
            'upsell_rate'        => $doctorKpis['upsell_rate'] ?? 0,
            'gold_memberships'   => $doctorKpis['gold_memberships'] ?? 0,
            'feedback_score'     => $doctorKpis['feedback_score'] ?? 0,
            'google_reviews'     => $doctorKpis['google_reviews'] ?? 0,
            'patient_return_rate' => $doctorKpis['patient_return_rate'] ?? 0,
            'avg_procedures'     => $doctorKpis['avg_procedures'] ?? 0,
            'patients_seen'      => $doctorKpis['patients_seen'] ?? 0,
            'revenue_per_day'    => $doctorKpis['revenue_per_day'] ?? 0,
        ];

        $result = [
            'doctor_count' => $bestValues['doctor_count'],
        ];

        foreach ($metricMap as $metric => $doctorValue) {
            $best = $bestValues[$metric] ?? ['value' => 0, 'doctor_id' => null];
            $bestValue = (float) $best['value'];
            $bestDocId = $best['doctor_id'];
            $doctorValue = (float) $doctorValue;

            $isBest = ($bestDocId !== null && $bestDocId == $doctorId);
            $gapPercent = $this->calculateGapPercent($doctorValue, $bestValue);
            $message = $this->getEncouragingMessage($gapPercent, $isBest);

            $result[$metric] = [
                'best_value' => $bestValue,
                'is_best' => $isBest,
                'gap_percent' => $gapPercent,
                'message' => $message,
            ];
        }

        return $result;
    }

    /**
     * Calculate gap percentage between doctor's value and network best.
     */
    private function calculateGapPercent(float $doctorValue, float $bestValue): float
    {
        if ($bestValue == 0) {
            return 0;
        }

        $gap = (($bestValue - $doctorValue) / $bestValue) * 100;

        return round(max(0, $gap), 1);
    }

    /**
     * Get dynamic encouraging message based on gap to network best.
     */
    private function getEncouragingMessage(float $gapPercent, bool $isBest): string
    {
        if ($isBest) {
            return 'You are the best in the network! Keep it up!';
        }

        if ($gapPercent <= 10) {
            return 'You are almost there! Push a little harder to reach the top.';
        }

        if ($gapPercent <= 30) {
            return 'You are improving! Stay focused on your targets.';
        }

        return 'Every step forward counts. Stay consistent and results will follow.';
    }

    /**
     * Build cache key for the benchmark data.
     *
     * The trailing `OperatingDays::version($accountId)` segment makes the
     * cache effectively self-invalidating: any closure / working-day
     * exception / weekly-pattern setting change for this account bumps
     * the counter via OperatingDaysVersionObserver, which changes the
     * cache key and forces a fresh recompute on the next read. No cron,
     * no manual flush, no waiting for the 1-hour TTL.
     */
    private function buildCacheKey(string $startDate, int $accountId): string
    {
        $yearMonth = substr($startDate, 0, 7); // e.g. "2026-03"
        $version = OperatingDays::version($accountId);
        return "doctor_benchmark_v3_{$accountId}_{$yearMonth}_v{$version}";
    }

    /**
     * @return array
     */
    private function emptyResult(): array
    {
        $emptyMetric = ['best_value' => 0, 'is_best' => false, 'gap_percent' => 0, 'message' => ''];

        return [
            'doctor_count' => 0,
            'total_revenue' => $emptyMetric,
            'conversion_rate' => $emptyMetric,
            'avg_client_value' => $emptyMetric,
            'product_revenue' => $emptyMetric,
            'upsell_revenue' => $emptyMetric,
            'upsell_rate' => $emptyMetric,
            'gold_memberships' => $emptyMetric,
            'feedback_score' => $emptyMetric,
            'google_reviews' => $emptyMetric,
            'patient_return_rate' => $emptyMetric,
            'avg_procedures' => $emptyMetric,
            'patients_seen' => $emptyMetric,
            'revenue_per_day' => $emptyMetric,
        ];
    }
}
