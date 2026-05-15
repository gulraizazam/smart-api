<?php

declare(strict_types=1);
namespace App\Services\DoctorDashboard;

use App\Helpers\DoctorDashboardHelper;
use App\Models\DoctorGoogleReview;
use App\Models\SystemTarget;
use App\Models\CentertargetMeta;
use App\Enums\AppointmentType;
use App\Support\OperatingDays;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DoctorDashboardService
{
    public function __construct(
        private readonly DoctorIdentifier $doctorIdentifier,
        private readonly ConversionCalculator $conversionCalculator,
        private readonly RevenueCalculator $revenueCalculator,
        private readonly UpsellCalculator $upsellCalculator,
        private readonly MembershipCalculator $membershipCalculator,
        private readonly FeedbackCalculator $feedbackCalculator,
        private readonly ProductRevenueCalculator $productRevenueCalculator,
        private readonly PatientReturnCalculator $patientReturnCalculator,
        private readonly PersonalBestCalculator $personalBestCalculator,
        private readonly BenchmarkCalculator $benchmarkCalculator,
    ) {}

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
        // Previous full calendar month for all MoM comparisons
        // Daily average normalization handles partial-month fairness for accumulating metrics
        $periodStart = Carbon::parse($startDate);
        $prevMonthStart = $periodStart->copy()->subMonthNoOverflow()->startOfMonth();
        $prevMonthEnd = $prevMonthStart->copy()->endOfMonth();
        [$lastStartDate, $lastEndDate] = [$prevMonthStart->format('Y-m-d'), $prevMonthEnd->format('Y-m-d')];

        // Current period KPIs
        $conversion = $this->conversionCalculator->calculate($doctorId, $startDate, $endDate, $accountId);
        $revenue = $this->revenueCalculator->calculate($doctorId, $startDate, $endDate, $accountId);
        $upsell = $this->upsellCalculator->calculate($doctorId, $startDate, $endDate, $accountId);
        $membership = $this->membershipCalculator->calculate($doctorId, $startDate, $endDate, $accountId);
        $feedback = $this->feedbackCalculator->calculate($doctorId, $startDate, $endDate);
        $productRevenue = $this->productRevenueCalculator->calculate($doctorId, $startDate, $endDate);
        // Return rate always uses rolling 45-day window regardless of period toggle
        $rolling45Start = Carbon::now()->subDays(45)->format('Y-m-d');
        $rolling45End = Carbon::now()->format('Y-m-d');
        $patientReturn = $this->patientReturnCalculator->calculate($doctorId, $rolling45Start, $rolling45End, $accountId);
        // Avg procedures always uses trailing 3 months (independent of period toggle)
        $avgProcedures = $this->patientReturnCalculator->calculateAvgProcedures($doctorId, $accountId);
        $googleReviews = $this->getGoogleReviews($doctorId, $startDate, $accountId);
        $patientsSeen = $this->getPatientsSeen($doctorId, $startDate, $endDate, $accountId);
        $revenuePerDay = $this->getRevenuePerDay($doctorId, $startDate, $endDate, $accountId, $revenue['total_revenue']);

        $avgClientValue = $this->revenueCalculator->calculateAvgClientValue(
            $revenue['total_revenue'],
            $conversion['total_converted']
        );

        // Last month KPIs for MoM comparison
        // Ratios: use full last calendar month
        $lastConversion     = $this->conversionCalculator->calculate($doctorId, $lastStartDate, $lastEndDate, $accountId);
        $lastFeedback       = $this->feedbackCalculator->calculate($doctorId, $lastStartDate, $lastEndDate);
        $lastGoogleReviews  = $this->getGoogleReviews($doctorId, $lastStartDate, $accountId);

        // Accumulators: use full last month (daily avg normalization handles partial-month fairness)
        $lastRevenue        = $this->revenueCalculator->calculate($doctorId, $lastStartDate, $lastEndDate, $accountId);
        $lastUpsell         = $this->upsellCalculator->calculate($doctorId, $lastStartDate, $lastEndDate, $accountId);
        $lastMembership     = $this->membershipCalculator->calculate($doctorId, $lastStartDate, $lastEndDate, $accountId);
        $lastProductRevenue = $this->productRevenueCalculator->calculate($doctorId, $lastStartDate, $lastEndDate);
        $lastPatientsSeen   = $this->getPatientsSeen($doctorId, $lastStartDate, $lastEndDate, $accountId);
        $lastRevenuePerDay  = $this->getRevenuePerDay($doctorId, $lastStartDate, $lastEndDate, $accountId, $lastRevenue['total_revenue']);

        // avg_client_value uses full last month revenue and conversion (both full month)
        $lastAvgClientValue = $this->revenueCalculator->calculateAvgClientValue(
            $lastRevenue['total_revenue'],
            $lastConversion['total_converted']
        );

        // Working days for daily-average MoM on accumulating metrics —
        // route through OperatingDays so closures and per-date exceptions
        // shrink the denominator the same way they do for invoicing.
        $currentWorkingDays = $this->countWorkingDays($startDate, $endDate, $accountId);
        $lastWorkingDays = $this->countWorkingDays($lastStartDate, $lastEndDate, $accountId);

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'kpis' => [
                'total_revenue' => [
                    'value' => $revenue['total_revenue'],
                    'last_month' => $lastRevenue['total_revenue'],
                    'mom' => $this->dailyAvgMoM($revenue['total_revenue'], $currentWorkingDays, $lastRevenue['total_revenue'], $lastWorkingDays),
                    'formatted' => DoctorDashboardHelper::formatCurrency($revenue['total_revenue']),
                ],
                'conversion_rate' => [
                    'value' => $conversion['conversion_rate'],
                    'total_arrived' => $conversion['total_arrived'],
                    'total_converted' => $conversion['total_converted'],
                    'last_month' => $lastConversion['conversion_rate'],
                    'mom' => DoctorDashboardHelper::calculatePointDiff($conversion['conversion_rate'], $lastConversion['conversion_rate']),
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
                    'mom' => $this->dailyAvgMoM($upsell['upsell_revenue'], $currentWorkingDays, $lastUpsell['upsell_revenue'], $lastWorkingDays),
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
                    'mom' => $this->dailyAvgMoM($membership['gold_memberships_sold'], $currentWorkingDays, $lastMembership['gold_memberships_sold'], $lastWorkingDays),
                ],
                'feedback_score' => [
                    'value' => $feedback['avg_rating'],
                    'total_feedback' => $feedback['total_feedback'],
                    'last_month' => $lastFeedback['avg_rating'],
                    'mom' => DoctorDashboardHelper::calculatePointDiff($feedback['avg_rating'], $lastFeedback['avg_rating']),
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
                    'mom' => $this->dailyAvgMoM($productRevenue['product_revenue'], $currentWorkingDays, $lastProductRevenue['product_revenue'], $lastWorkingDays),
                    'formatted' => DoctorDashboardHelper::formatCurrency($productRevenue['product_revenue']),
                ],
                'patient_return_rate' => [
                    'value' => $patientReturn['return_rate'],
                    'patients_returned' => $patientReturn['patients_returned'],
                    'total_unique' => $patientReturn['total_unique_patients'],
                ],
                'avg_procedures' => [
                    'value' => $avgProcedures['avg_procedures'],
                    'total_procedures' => $avgProcedures['total_procedures'],
                    'converted_patients' => $avgProcedures['converted_patients'],
                ],
                'patients_seen' => [
                    'value' => $patientsSeen['total'],
                    'consultations' => $patientsSeen['consultations'],
                    'treatments' => $patientsSeen['treatments'],
                    'last_month' => $lastPatientsSeen['total'],
                    'mom' => $this->dailyAvgMoM($patientsSeen['total'], $currentWorkingDays, $lastPatientsSeen['total'], $lastWorkingDays),
                ],
                'revenue_per_day' => [
                    'value' => $revenuePerDay['revenue_per_day'],
                    'working_days_elapsed' => $revenuePerDay['working_days_elapsed'],
                    'last_month' => $lastRevenuePerDay['revenue_per_day'],
                    'mom' => DoctorDashboardHelper::calculateMoM($revenuePerDay['revenue_per_day'], $lastRevenuePerDay['revenue_per_day']),
                ],
            ],
        ];
    }

    /**
     * Get hero strip data (goal progress, personal bests).
     *
     * @param int $doctorId
     * @param int $accountId
     * @param string $period 'this_month'|'last_month'
     * @return array
     */
    public function getHeroData(int $doctorId, int $accountId, string $period = 'this_month'): array
    {
        [$startDate, $endDate] = $this->getDateRange($period);

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
     * Formula: (Branch-wide Revenue This Month / Branch Monthly Target) × 100
     * Uses the doctor's most active branch for target selection.
     * Revenue is the total for the entire branch (team effort), not just this doctor.
     *
     * @param int $doctorId
     * @param string $startDate
     * @param string $endDate
     * @param int $accountId
     * @return array
     */
    private function getGoalProgress(int $doctorId, string $startDate, string $endDate, int $accountId): array
    {
        // Find the doctor's most active branch
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

        // Get branch monthly target and working days
        $month = (int) Carbon::parse($startDate)->format('m');
        $year = (int) Carbon::parse($startDate)->format('Y');

        $targetData = $this->getBranchTarget($mostActiveBranchId, $month, $year, $accountId);

        // Fallback to last month's target
        if (!$targetData) {
            $lastMonth = Carbon::parse($startDate)->subMonthNoOverflow();
            $targetData = $this->getBranchTarget(
                $mostActiveBranchId,
                (int) $lastMonth->format('m'),
                (int) $lastMonth->format('Y'),
                $accountId
            );
        }

        if (!$targetData) {
            return [
                'has_target' => false,
                'message' => 'No branch target set yet.',
            ];
        }

        $target = $targetData['target'];
        $totalWorkingDays = $targetData['working_days'];

        // Calculate branch-wide revenue using same logic as Operations::Monthlyachievedamount
        $branchRevenue = $this->getBranchRevenue($mostActiveBranchId, $startDate, $endDate, $accountId);

        $percentage = $target > 0 ? round(($branchRevenue / $target) * 100, 1) : 0;

        // Working days remaining: total working days - operating days elapsed
        // (closures/exceptions respected via OperatingDays). For past months, always 0.
        $daysRemaining = $this->getWorkingDaysRemaining($totalWorkingDays, $startDate, $endDate, $accountId);

        // Color rules
        $color = 'red';
        if ($percentage >= 90) {
            $color = 'green';
        } elseif ($percentage >= 80) {
            $color = 'orange';
        }

        return [
            'has_target' => true,
            'branch_revenue' => $branchRevenue,
            'branch_target' => $target,
            'percentage' => $percentage,
            'days_remaining' => $daysRemaining,
            'color' => $color,
            'branch_id' => $mostActiveBranchId,
        ];
    }

    /**
     * Get branch-wide revenue for a location.
     * Uses same logic as Operations::Monthlyachievedamount — direct query on
     * package_advances by location_id (revenue_in - refund_out).
     *
     * @param int $locationId
     * @param string $startDate
     * @param string $endDate
     * @param int $accountId
     * @return float
     */
    private function getBranchRevenue(int $locationId, string $startDate, string $endDate, int $accountId): float
    {
        $advances = DB::table('package_advances')
            ->where('account_id', $accountId)
            ->where('location_id', $locationId)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->get();

        $revenueIn = 0;
        $refundOut = 0;

        foreach ($advances as $adv) {
            if (
                ($adv->cash_flow === 'in' && $adv->is_adjustment == '0' && $adv->is_tax == '0' && $adv->is_cancel == '0')
                || ($adv->cash_flow === 'out' && $adv->is_refund == '1')
            ) {
                if ($adv->cash_amount != 0) {
                    if ($adv->cash_flow === 'in') {
                        $revenueIn += (float) $adv->cash_amount;
                    } else {
                        $refundOut += (float) $adv->cash_amount;
                    }
                }
            }
        }

        return $revenueIn - $refundOut;
    }

    /**
     * Calculate working days remaining for the given period.
     * Working days = Mon-Sat (Sunday is off).
     * For past months (endDate < today), returns 0.
     *
     * @param int $totalWorkingDays from centre target
     * @param string $startDate period start
     * @param string $endDate period end
     * @return int
     */
    private function getWorkingDaysRemaining(int $totalWorkingDays, string $startDate, string $endDate, int $accountId): int
    {
        $now = Carbon::now()->startOfDay();
        $periodEnd = Carbon::parse($endDate)->startOfDay();

        // Past month — no days remaining.
        if ($periodEnd->lt($now)) {
            return 0;
        }

        // Operating days elapsed from start to today (inclusive) — uses
        // OperatingDays so holidays and per-date exceptions don't inflate
        // the "elapsed" count.
        $elapsed = count(OperatingDays::datesInRange(
            $accountId,
            [],
            CarbonImmutable::parse($startDate),
            CarbonImmutable::parse($now->format('Y-m-d')),
        ));

        return max(0, $totalWorkingDays - $elapsed);
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
    private function getBranchTarget(?int $locationId, int $month, int $year, int $accountId): ?array
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
        return [
            'target' => (float) $meta->target_amount * $workingDays,
            'working_days' => $workingDays,
        ];
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
            elseif (!$apt->is_arrived && !$apt->is_cancelled && $apt->status_name && !in_array(strtolower($apt->status_name), ['scheduled', 'confirmed'], true)) $sortPriority = 3;

            return [
                'id' => $apt->id,
                'type' => $apt->appointment_type_id === AppointmentType::Consultancy->value ? 'Consultation' : 'Treatment',
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

        $sortList = fn($list) => collect($list)->sortBy([
                ['sort_priority', 'asc'],
                ['time_raw', 'asc'],
            ])->values()->toArray();

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
        // Return rate always uses rolling 45-day window
        $rolling45Start = Carbon::now()->subDays(45)->format('Y-m-d');
        $rolling45End = Carbon::now()->format('Y-m-d');
        $patientReturn = $this->patientReturnCalculator->calculate($doctorId, $rolling45Start, $rolling45End, $accountId);
        // Avg procedures always uses trailing 3 months
        $avgProcedures = $this->patientReturnCalculator->calculateAvgProcedures($doctorId, $accountId);
        $googleReviews = $this->getGoogleReviews($doctorId, $startDate, $accountId);
        $patientsSeen = $this->getPatientsSeen($doctorId, $startDate, $endDate, $accountId);

        $avgClientValue = $this->revenueCalculator->calculateAvgClientValue(
            $revenue['total_revenue'],
            $conversion['total_converted']
        );

        $revenuePerDay = $this->getRevenuePerDay($doctorId, $startDate, $endDate, $accountId, $revenue['total_revenue']);

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
            'revenue_per_day'    => $revenuePerDay['revenue_per_day'],
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
     * Calculate revenue per working day for a doctor.
     * Revenue / Mon-Sat days elapsed in the period.
     *
     * @param int $doctorId
     * @param string $startDate
     * @param string $endDate
     * @param int $accountId
     * @param float $revenue Pre-calculated revenue for the period
     * @return array
     */
    private function getRevenuePerDay(int $doctorId, string $startDate, string $endDate, int $accountId, float $revenue): array
    {
        // Denominator must be operating days, not Mon-Sat days — a clinic
        // closed for Eid still earns no revenue, but it shouldn't dilute
        // the per-day average. Single source of truth via OperatingDays.
        $workingDaysElapsed = $this->countWorkingDays($startDate, $endDate, $accountId);

        $revenuePerDay = $workingDaysElapsed > 0 ? round($revenue / $workingDaysElapsed, 0) : 0;

        return [
            'revenue_per_day' => $revenuePerDay,
            'working_days_elapsed' => $workingDaysElapsed,
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

    /**
     * Count operating days in a date range (inclusive). Delegates to
     * OperatingDays so closures + per-date exceptions are honoured —
     * pre-2026-05-15 this was a raw Mon-Sat loop which over-counted on
     * Eid / training / branch-closure weeks.
     */
    private function countWorkingDays(string $startDate, string $endDate, int $accountId): int
    {
        return count(OperatingDays::datesInRange(
            $accountId,
            [],
            CarbonImmutable::parse($startDate),
            CarbonImmutable::parse($endDate),
        ));
    }

    /**
     * MoM comparison using daily averages instead of raw totals.
     * Divides each period's value by its working days before comparing.
     */
    private function dailyAvgMoM(float $currentValue, int $currentDays, float $lastValue, int $lastDays): array
    {
        $currentAvg = $currentDays > 0 ? $currentValue / $currentDays : 0;
        $lastAvg = $lastDays > 0 ? $lastValue / $lastDays : 0;

        return DoctorDashboardHelper::calculateMoM($currentAvg, $lastAvg);
    }
}
