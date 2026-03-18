<?php

namespace App\Services\DoctorDashboard;

use App\Helpers\DoctorDashboardHelper;
use App\Models\DoctorGoogleReview;
use App\Models\SystemTarget;
use App\Models\CentertargetMeta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DoctorDashboardService
{
    private DoctorIdentifier $doctorIdentifier;
    private ConversionCalculator $conversionCalculator;
    private RevenueCalculator $revenueCalculator;
    private UpsellCalculator $upsellCalculator;
    private MembershipCalculator $membershipCalculator;
    private FeedbackCalculator $feedbackCalculator;
    private ProductRevenueCalculator $productRevenueCalculator;
    private PatientReturnCalculator $patientReturnCalculator;
    private PersonalBestCalculator $personalBestCalculator;
    private BenchmarkCalculator $benchmarkCalculator;

    public function __construct(
        DoctorIdentifier $doctorIdentifier,
        ConversionCalculator $conversionCalculator,
        RevenueCalculator $revenueCalculator,
        UpsellCalculator $upsellCalculator,
        MembershipCalculator $membershipCalculator,
        FeedbackCalculator $feedbackCalculator,
        ProductRevenueCalculator $productRevenueCalculator,
        PatientReturnCalculator $patientReturnCalculator,
        PersonalBestCalculator $personalBestCalculator,
        BenchmarkCalculator $benchmarkCalculator
    ) {
        $this->doctorIdentifier = $doctorIdentifier;
        $this->conversionCalculator = $conversionCalculator;
        $this->revenueCalculator = $revenueCalculator;
        $this->upsellCalculator = $upsellCalculator;
        $this->membershipCalculator = $membershipCalculator;
        $this->feedbackCalculator = $feedbackCalculator;
        $this->productRevenueCalculator = $productRevenueCalculator;
        $this->patientReturnCalculator = $patientReturnCalculator;
        $this->personalBestCalculator = $personalBestCalculator;
        $this->benchmarkCalculator = $benchmarkCalculator;
    }

    /**
     * Get all KPI data for the doctor dashboard.
     *
     * @param int $doctorId
     * @param int $accountId
     * @param string|null $period 'this_month'|'last_month' (default: this_month)
     * @return array
     */
    public function getKpiData(int $doctorId, int $accountId, ?string $period = 'this_month'): array
    {
        [$startDate, $endDate] = $this->getDateRange($period);
        // Full last calendar month — for ratio/rate KPIs (conversion, feedback, return rate, etc.)
        [$lastStartDate, $lastEndDate] = DoctorDashboardHelper::getLastMonthRange();
        // Same-date window in last month — for accumulating KPIs (revenue, counts)
        // e.g. if current = Mar 1–17 → last = Feb 1–17 (fair like-for-like)
        [$lastSameDateStart, $lastSameDateEnd] = DoctorDashboardHelper::getLastMonthSameDateRange($startDate, $endDate);

        // Current period KPIs
        $conversion = $this->conversionCalculator->calculate($doctorId, $startDate, $endDate, $accountId);
        $revenue = $this->revenueCalculator->calculate($doctorId, $startDate, $endDate, $accountId);
        $upsell = $this->upsellCalculator->calculate($doctorId, $startDate, $endDate, $accountId);
        $membership = $this->membershipCalculator->calculate($doctorId, $startDate, $endDate, $accountId);
        $feedback = $this->feedbackCalculator->calculate($doctorId, $startDate, $endDate);
        $productRevenue = $this->productRevenueCalculator->calculate($doctorId, $startDate, $endDate);
        $patientReturn = $this->patientReturnCalculator->calculate($doctorId, $startDate, $endDate, $accountId);
        $avgProcedures = $this->patientReturnCalculator->calculateAvgProcedures($doctorId, $startDate, $endDate, $accountId);
        $googleReviews = $this->getGoogleReviews($doctorId, $startDate, $accountId);
        $patientsSeen = $this->getPatientsSeen($doctorId, $startDate, $endDate, $accountId);
        $newVsReturning = $this->getNewVsReturning($doctorId, $startDate, $endDate, $accountId);

        $avgClientValue = $this->revenueCalculator->calculateAvgClientValue(
            $revenue['total_revenue'],
            $conversion['total_converted']
        );

        // Last month KPIs for MoM comparison
        // Ratios: use full last calendar month
        $lastConversion     = $this->conversionCalculator->calculate($doctorId, $lastStartDate, $lastEndDate, $accountId);
        $lastFeedback       = $this->feedbackCalculator->calculate($doctorId, $lastStartDate, $lastEndDate);
        $lastPatientReturn  = $this->patientReturnCalculator->calculate($doctorId, $lastStartDate, $lastEndDate, $accountId);
        $lastAvgProcedures  = $this->patientReturnCalculator->calculateAvgProcedures($doctorId, $lastStartDate, $lastEndDate, $accountId);
        $lastGoogleReviews  = $this->getGoogleReviews($doctorId, $lastStartDate, $accountId);

        // Accumulators: use same-date window in last month (e.g. Mar 1–17 vs Feb 1–17)
        $lastRevenue        = $this->revenueCalculator->calculate($doctorId, $lastSameDateStart, $lastSameDateEnd, $accountId);
        $lastUpsell         = $this->upsellCalculator->calculate($doctorId, $lastSameDateStart, $lastSameDateEnd, $accountId);
        $lastMembership     = $this->membershipCalculator->calculate($doctorId, $lastSameDateStart, $lastSameDateEnd, $accountId);
        $lastProductRevenue = $this->productRevenueCalculator->calculate($doctorId, $lastSameDateStart, $lastSameDateEnd);
        $lastPatientsSeen   = $this->getPatientsSeen($doctorId, $lastSameDateStart, $lastSameDateEnd, $accountId);

        // avg_client_value uses same-date revenue but full-month conversion (ratio)
        $lastAvgClientValue = $this->revenueCalculator->calculateAvgClientValue(
            $lastRevenue['total_revenue'],
            $lastConversion['total_converted']
        );

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'kpis' => [
                'total_revenue' => [
                    'value' => $revenue['total_revenue'],
                    'last_month' => $lastRevenue['total_revenue'],
                    'mom' => DoctorDashboardHelper::calculateMoM($revenue['total_revenue'], $lastRevenue['total_revenue']),
                    'formatted' => DoctorDashboardHelper::formatCurrency($revenue['total_revenue']),
                ],
                'conversion_rate' => [
                    'value' => $conversion['conversion_rate'],
                    'total_arrived' => $conversion['total_arrived'],
                    'total_converted' => $conversion['total_converted'],
                    'last_month' => $lastConversion['conversion_rate'],
                    'mom' => DoctorDashboardHelper::calculateMoM($conversion['conversion_rate'], $lastConversion['conversion_rate']),
                ],
                'avg_client_value' => [
                    'value' => $avgClientValue,
                    'last_month' => $lastAvgClientValue,
                    'mom' => DoctorDashboardHelper::calculateMoM($avgClientValue, $lastAvgClientValue),
                    'formatted' => DoctorDashboardHelper::formatCurrency($avgClientValue),
                ],
                'upsell_revenue' => [
                    'value' => $upsell['upsell_revenue'],
                    'last_month' => $lastUpsell['upsell_revenue'],
                    'mom' => DoctorDashboardHelper::calculateMoM($upsell['upsell_revenue'], $lastUpsell['upsell_revenue']),
                    'formatted' => DoctorDashboardHelper::formatCurrency($upsell['upsell_revenue']),
                ],
                'upsell_rate' => [
                    'value' => $upsell['upsell_rate'],
                    'unique_upsold' => $upsell['unique_upsold_patients'],
                    'unique_treated' => $upsell['unique_treated_patients'],
                    'last_month' => $lastUpsell['upsell_rate'],
                ],
                'gold_memberships' => [
                    'value' => $membership['gold_memberships_sold'],
                    'last_month' => $lastMembership['gold_memberships_sold'],
                    'mom' => DoctorDashboardHelper::calculateMoM($membership['gold_memberships_sold'], $lastMembership['gold_memberships_sold']),
                ],
                'feedback_score' => [
                    'value' => $feedback['avg_rating'],
                    'total_feedback' => $feedback['total_feedback'],
                    'last_month' => $lastFeedback['avg_rating'],
                    'mom' => DoctorDashboardHelper::calculateMoM($feedback['avg_rating'], $lastFeedback['avg_rating']),
                ],
                'google_reviews' => [
                    'value' => $googleReviews,
                    'last_month' => $lastGoogleReviews,
                    'mom' => DoctorDashboardHelper::calculateMoM($googleReviews, $lastGoogleReviews),
                ],
                'product_revenue' => [
                    'value' => $productRevenue['product_revenue'],
                    'total_orders' => $productRevenue['total_orders'],
                    'last_month' => $lastProductRevenue['product_revenue'],
                    'mom' => DoctorDashboardHelper::calculateMoM($productRevenue['product_revenue'], $lastProductRevenue['product_revenue']),
                    'formatted' => DoctorDashboardHelper::formatCurrency($productRevenue['product_revenue']),
                ],
                'patient_return_rate' => [
                    'value' => $patientReturn['return_rate'],
                    'patients_returned' => $patientReturn['patients_returned'],
                    'total_unique' => $patientReturn['total_unique_patients'],
                    'last_month' => $lastPatientReturn['return_rate'],
                    'mom' => DoctorDashboardHelper::calculateMoM($patientReturn['return_rate'], $lastPatientReturn['return_rate']),
                ],
                'avg_procedures' => [
                    'value' => $avgProcedures['avg_procedures'],
                    'total_procedures' => $avgProcedures['total_procedures'],
                    'unique_patients' => $avgProcedures['unique_patients'],
                    'last_month' => $lastAvgProcedures['avg_procedures'],
                    'mom' => DoctorDashboardHelper::calculateMoM($avgProcedures['avg_procedures'], $lastAvgProcedures['avg_procedures']),
                ],
                'patients_seen' => [
                    'value' => $patientsSeen['total'],
                    'consultations' => $patientsSeen['consultations'],
                    'treatments' => $patientsSeen['treatments'],
                    'last_month' => $lastPatientsSeen['total'],
                    'mom' => DoctorDashboardHelper::calculateMoM($patientsSeen['total'], $lastPatientsSeen['total']),
                ],
                'new_vs_returning' => [
                    'new' => $newVsReturning['new'],
                    'returning' => $newVsReturning['returning'],
                    'total' => $newVsReturning['total'],
                ],
            ],
        ];
    }

    /**
     * Get hero strip data (goal progress, personal bests).
     *
     * @param int $doctorId
     * @param int $accountId
     * @return array
     */
    public function getHeroData(int $doctorId, int $accountId): array
    {
        [$startDate, $endDate] = DoctorDashboardHelper::getThisMonthRange();

        // Goal progress bar
        $goalProgress = $this->getGoalProgress($doctorId, $startDate, $endDate, $accountId);

        // Personal bests
        $personalBests = $this->personalBestCalculator->calculate($doctorId, $accountId);

        return [
            'goal_progress' => $goalProgress,
            'personal_bests' => $personalBests,
        ];
    }

    /**
     * Get goal progress bar data.
     *
     * Formula: (This Doctor's Revenue This Month / Branch Monthly Target) × 100
     * Uses most active branch this month for target selection.
     *
     * @param int $doctorId
     * @param string $startDate
     * @param string $endDate
     * @param int $accountId
     * @return array
     */
    private function getGoalProgress(int $doctorId, string $startDate, string $endDate, int $accountId): array
    {
        $revenue = $this->revenueCalculator->calculate($doctorId, $startDate, $endDate, $accountId);
        $doctorRevenue = $revenue['total_revenue'];

        // Find the most active branch
        $locationIds = $this->doctorIdentifier->getDoctorLocationIds($doctorId);
        $revenueByLocation = $this->revenueCalculator->calculateByLocation(
            $doctorId, $startDate, $endDate, $accountId, $locationIds
        );

        $mostActiveBranchId = null;
        $maxRevenue = 0;
        foreach ($revenueByLocation as $locId => $locRevenue) {
            if ($locRevenue > $maxRevenue) {
                $maxRevenue = $locRevenue;
                $mostActiveBranchId = $locId;
            }
        }

        // Fallback to first location
        if (!$mostActiveBranchId && !empty($locationIds)) {
            $mostActiveBranchId = $locationIds[0];
        }

        // Get branch monthly target
        $month = (int) Carbon::parse($startDate)->format('m');
        $year = (int) Carbon::parse($startDate)->format('Y');

        $target = $this->getBranchTarget($mostActiveBranchId, $month, $year, $accountId);

        // Fallback to last month's target
        if (!$target) {
            $lastMonth = Carbon::parse($startDate)->subMonth();
            $target = $this->getBranchTarget(
                $mostActiveBranchId,
                (int) $lastMonth->format('m'),
                (int) $lastMonth->format('Y'),
                $accountId
            );
        }

        if (!$target) {
            return [
                'has_target' => false,
                'message' => 'No branch target set yet.',
            ];
        }

        $percentage = $target > 0 ? round(($doctorRevenue / $target) * 100, 1) : 0;
        $daysRemaining = Carbon::now()->diffInDays(Carbon::now()->endOfMonth()) + 1;

        // Color rules
        $color = 'red';
        if ($percentage >= 90) {
            $color = 'green';
        } elseif ($percentage >= 80) {
            $color = 'orange';
        }

        return [
            'has_target' => true,
            'doctor_revenue' => $doctorRevenue,
            'branch_target' => $target,
            'percentage' => $percentage,
            'days_remaining' => $daysRemaining,
            'color' => $color,
            'branch_id' => $mostActiveBranchId,
        ];
    }

    /**
     * Get branch monthly target (daily target × working days).
     *
     * @param int|null $locationId
     * @param int $month
     * @param int $year
     * @param int $accountId
     * @return float|null
     */
    private function getBranchTarget(?int $locationId, int $month, int $year, int $accountId): ?float
    {
        if (!$locationId) {
            return null;
        }

        $meta = CentertargetMeta::join('centertarget as ct', 'centretargetmeta.centertarget_id', '=', 'ct.id')
            ->where('centretargetmeta.location_id', $locationId)
            ->where('ct.month', $month)
            ->where('ct.year', $year)
            ->where('ct.account_id', $accountId)
            ->select('centretargetmeta.target_amount', 'ct.working_days')
            ->first();

        if (!$meta || !$meta->target_amount) {
            return null;
        }

        // target_amount is daily target; monthly = daily × working_days
        $workingDays = (int) ($meta->working_days ?: 22);
        return (float) $meta->target_amount * $workingDays;
    }

    /**
     * Get today's appointments for the doctor.
     *
     * @param int $doctorId
     * @param int $accountId
     * @return array
     */
    public function getTodaysAppointments(int $doctorId, int $accountId): array
    {
        $today = Carbon::now()->format('Y-m-d');

        $appointments = DB::table('appointments as a')
            ->leftJoin('users as p', 'a.patient_id', '=', 'p.id')
            ->leftJoin('services as s', 'a.service_id', '=', 's.id')
            ->leftJoin('locations as l', 'a.location_id', '=', 'l.id')
            ->leftJoin('appointment_statuses as ast', 'a.appointment_status_id', '=', 'ast.id')
            ->where('a.doctor_id', $doctorId)
            ->where('a.scheduled_date', $today)
            ->whereNull('a.deleted_at')
            ->select(
                'a.id',
                'a.appointment_type_id',
                'a.scheduled_time',
                'a.appointment_status_id',
                'ast.name as status_name',
                'ast.is_arrived',
                'ast.is_cancelled',
                'p.name as patient_name',
                's.name as service_name',
                'l.name as location_name'
            )
            ->orderBy('a.scheduled_time')
            ->get();

        $mapRow = function ($apt) {
            // Determine sort priority: 1=arrived, 2=scheduled(default), 3=other, 4=cancelled
            $sortPriority = 2;
            if ($apt->is_arrived) $sortPriority = 1;
            elseif ($apt->is_cancelled) $sortPriority = 4;
            elseif (!$apt->is_arrived && !$apt->is_cancelled && $apt->status_name && !in_array(strtolower($apt->status_name), ['scheduled', 'confirmed'])) $sortPriority = 3;

            return [
                'id' => $apt->id,
                'type' => $apt->appointment_type_id == 1 ? 'Consultation' : 'Treatment',
                'time' => $apt->scheduled_time ? Carbon::parse($apt->scheduled_time)->format('h:i A') : 'Unscheduled',
                'time_raw' => $apt->scheduled_time,
                'patient' => $apt->patient_name ?? 'N/A',
                'service' => $apt->service_name ?? 'N/A',
                'location' => $apt->location_name ?? 'N/A',
                'status' => $apt->status_name ?? 'N/A',
                'is_arrived' => (bool) $apt->is_arrived,
                'is_cancelled' => (bool) $apt->is_cancelled,
                'sort_priority' => $sortPriority,
            ];
        };

        $sortList = function ($list) {
            return collect($list)->sortBy([
                ['sort_priority', 'asc'],
                ['time_raw', 'asc'],
            ])->values()->toArray();
        };

        $treatments = $appointments->where('appointment_type_id', 2);
        $consultations = $appointments->where('appointment_type_id', 1);

        $treatmentsList = $sortList($treatments->map($mapRow));
        $consultationsList = $sortList($consultations->map($mapRow));

        return [
            'total' => $appointments->count(),
            'treatments' => [
                'count' => $treatments->count(),
                'arrived' => $treatments->where('is_arrived', 1)->count(),
                'list' => $treatmentsList,
            ],
            'consultations' => [
                'count' => $consultations->count(),
                'arrived' => $consultations->where('is_arrived', 1)->count(),
                'list' => $consultationsList,
            ],
        ];
    }

    /**
     * Get benchmark data with gap/message relative to the viewing doctor.
     *
     * @param int $doctorId
     * @param string $startDate
     * @param string $endDate
     * @param int $accountId
     * @return array
     */
    public function getBenchmarks(int $doctorId, string $startDate, string $endDate, int $accountId): array
    {
        // Compute the doctor's own KPI values for gap calculation
        $conversion = $this->conversionCalculator->calculate($doctorId, $startDate, $endDate, $accountId);
        $revenue = $this->revenueCalculator->calculate($doctorId, $startDate, $endDate, $accountId);
        $upsell = $this->upsellCalculator->calculate($doctorId, $startDate, $endDate, $accountId);
        $membership = $this->membershipCalculator->calculate($doctorId, $startDate, $endDate, $accountId);
        $feedback = $this->feedbackCalculator->calculate($doctorId, $startDate, $endDate);
        $productRevenue = $this->productRevenueCalculator->calculate($doctorId, $startDate, $endDate);
        $patientReturn = $this->patientReturnCalculator->calculate($doctorId, $startDate, $endDate, $accountId);
        $avgProcedures = $this->patientReturnCalculator->calculateAvgProcedures($doctorId, $startDate, $endDate, $accountId);
        $googleReviews = $this->getGoogleReviews($doctorId, $startDate, $accountId);
        $patientsSeen = $this->getPatientsSeen($doctorId, $startDate, $endDate, $accountId);

        $avgClientValue = $this->revenueCalculator->calculateAvgClientValue(
            $revenue['total_revenue'],
            $conversion['total_converted']
        );

        $doctorKpis = [
            'total_revenue'      => $revenue['total_revenue'],
            'conversion_rate'    => $conversion['conversion_rate'],
            'avg_client_value'   => $avgClientValue,
            'product_revenue'    => $productRevenue['product_revenue'],
            'upsell_revenue'     => $upsell['upsell_revenue'],
            'upsell_rate'        => $upsell['upsell_rate'] ?? 0,
            'gold_memberships'   => $membership['gold_memberships_sold'],
            'feedback_score'     => $feedback['avg_rating'],
            'google_reviews'     => $googleReviews,
            'patient_return_rate' => $patientReturn['return_rate'],
            'avg_procedures'     => $avgProcedures['avg_procedures'],
            'patients_seen'      => $patientsSeen['total'],
        ];

        return $this->benchmarkCalculator->calculate($doctorId, $doctorKpis, $startDate, $endDate, $accountId);
    }

    /**
     * Get system-wide targets.
     *
     * @param int $accountId
     * @return array
     */
    public function getTargets(int $accountId): array
    {
        return SystemTarget::getAllForAccount($accountId);
    }

    /**
     * Get Google reviews count for a doctor in a given month.
     */
    private function getGoogleReviews(int $doctorId, string $startDate, int $accountId): int
    {
        $month = (int) Carbon::parse($startDate)->format('m');
        $year = (int) Carbon::parse($startDate)->format('Y');

        $review = DoctorGoogleReview::getForDoctorMonth($doctorId, $month, $year, $accountId);

        return $review ? (int) $review->review_count : 0;
    }

    /**
     * Get unique patients seen (consultations + treatments arrived).
     */
    private function getPatientsSeen(int $doctorId, string $startDate, string $endDate, int $accountId): array
    {
        $consultationStatusIds = DoctorDashboardHelper::getConsultationStatusIds();
        $treatmentStatusIds = DoctorDashboardHelper::getTreatmentStatusIds();

        $consultations = DB::table('appointments')
            ->where('doctor_id', $doctorId)
            ->where('appointment_type_id', 1)
            ->whereIn('appointment_status_id', $consultationStatusIds)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->count();

        $treatments = DB::table('appointments')
            ->where('doctor_id', $doctorId)
            ->where('appointment_type_id', 2)
            ->whereIn('appointment_status_id', $treatmentStatusIds)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->count();

        return [
            'total' => $consultations + $treatments,
            'consultations' => $consultations,
            'treatments' => $treatments,
        ];
    }

    /**
     * Get new vs returning patient breakdown.
     */
    private function getNewVsReturning(int $doctorId, string $startDate, string $endDate, int $accountId): array
    {
        $consultationStatusIds = DoctorDashboardHelper::getConsultationStatusIds();

        // Get consultation patients in the period
        $patients = DB::table('appointments')
            ->where('doctor_id', $doctorId)
            ->where('appointment_type_id', 1)
            ->whereIn('appointment_status_id', $consultationStatusIds)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->distinct()
            ->pluck('patient_id')
            ->toArray();

        if (empty($patients)) {
            return ['new' => 0, 'returning' => 0, 'total' => 0];
        }

        // New patient = first-ever arrived consultation is in this period
        $newCount = 0;
        $returningCount = 0;

        // Get first consultation date for each patient
        $firstConsultations = DB::table('appointments')
            ->whereIn('patient_id', $patients)
            ->where('appointment_type_id', 1)
            ->whereIn('appointment_status_id', $consultationStatusIds)
            ->select('patient_id', DB::raw('MIN(scheduled_date) as first_date'))
            ->groupBy('patient_id')
            ->pluck('first_date', 'patient_id')
            ->toArray();

        foreach ($patients as $patientId) {
            $firstDate = $firstConsultations[$patientId] ?? null;
            if ($firstDate && $firstDate >= $startDate) {
                $newCount++;
            } else {
                $returningCount++;
            }
        }

        return [
            'new' => $newCount,
            'returning' => $returningCount,
            'total' => count($patients),
        ];
    }

    /**
     * Get date range based on period string.
     */
    private function getDateRange(?string $period): array
    {
        if ($period === 'last_month') {
            return DoctorDashboardHelper::getLastMonthRange();
        }

        return DoctorDashboardHelper::getThisMonthRange();
    }
}
