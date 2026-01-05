<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\DashboardHelper;
use App\Models\Activity;
use App\Models\Locations;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\Dashboard\DashboardRevenueService;
use App\Services\Dashboard\DashboardChartService;
use App\HelperModule\ApiHelper;

class DashboardController extends Controller
{
    protected $statsService;
    protected $revenueService;
    protected $chartService;

    public function __construct(
        DashboardStatsService $statsService,
        DashboardRevenueService $revenueService,
        DashboardChartService $chartService
    ) {
        $this->statsService = $statsService;
        $this->revenueService = $revenueService;
        $this->chartService = $chartService;
    }

    /**
     * Get dashboard stats (consultancies, treatments, revenue, collection)
     */
    public function getStats(Request $request)
    {
        $userCentres = DashboardHelper::getUserCentres();
        [$start_date, $end_date] = DashboardHelper::getDateRangeFromRequest($request);
        
        $data = $this->statsService->getConsultancies($start_date, $end_date, $userCentres);
        $data = array_merge($data, $this->statsService->getTreatments($start_date, $end_date, $userCentres));
        $data = array_merge($data, $this->revenueService->getSalesByCentre($userCentres, $start_date, $end_date));
        $data = array_merge($data, $this->revenueService->getCollectionStats($userCentres, $request->type, $request));
        
        $dateTimeInfo = DashboardHelper::getDateTimeInfo();
        $data['today'] = $dateTimeInfo['today'];
        $data['startWeek'] = $dateTimeInfo['startWeek'];
        $data['month'] = $dateTimeInfo['month'];
        $data['currentTime'] = $dateTimeInfo['currentTime'];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get activities with pagination for infinite scroll
     */
    public function getActivities(Request $request)
    {
        if (!Gate::allows('dashboard_recent_activities')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'data' => [],
                'has_more' => false,
                'total' => 0
            ], 403);
        }

        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);
        $centres = DashboardHelper::getUserCentres();
        $todayStart = Carbon::today();
        $todayEnd = Carbon::tomorrow();
        
        $totalCount = Activity::whereIn('centre_id', $centres)
            ->whereIn('action', ['received', 'consumed', 'refunded'])
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<', $todayEnd)
            ->count();
        
        $activities = Activity::with([
            'plan' => fn ($q) => $q->select('id', 'name'),
            'centre' => fn ($q) => $q->select('id', 'name')
        ])
            ->whereIn('centre_id', $centres)
            ->whereIn('action', ['received', 'consumed', 'refunded'])
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<', $todayEnd)
            ->latest()
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activities,
            'has_more' => ($page * $perPage) < $totalCount,
            'total' => $totalCount,
            'current_page' => (int) $page
        ]);
    }

    /**
     * Get collection by centre chart data
     */
    public function collectionByCentre(Request $request)
    {
        try {
            $result = $this->revenueService->getCollectionByCentre($request->type ?? '', $request);
            $data = $result['data'] ?? [];
            $total = $result['total'] ?? 0;
            
            $day = $request->type ?: 'today';
            $dataArray = isset($data[$day]) ? array_values($data[$day]) : [];

            if (count($dataArray) > 1) {
                $totalValue = array_sum(array_column(array_slice($dataArray, 1), 1));

                // Calculate the percentage for each slice
                for ($i = 1; $i < count($dataArray); $i++) {
                    if (isset($dataArray[$i][1])) {
                        $percentage = $totalValue != 0 ? ($dataArray[$i][1] / $totalValue) * 100 : 0;
                        $dataArray[$i][0] = $dataArray[$i][0] . " (" . number_format($percentage, 1) . "%)";
                    }
                }
            }
            
            // Ensure all period arrays are properly indexed for JSON
            foreach ($data as $key => $value) {
                $data[$key] = is_array($value) ? array_values($value) : [];
            }
            $data[$day] = $dataArray;
            
            return ApiHelper::apiResponse(200, 'collection by centre data', true, [
                'pie' => $data,
                'total' => number_format($total, 2),
            ]);
        } catch (\Exception $e) {
            \Log::error('collectionByCentre Error: ' . $e->getMessage());
            return ApiHelper::apiResponse(500, $e->getMessage(), false, []);
        }
    }

    /**
     * Get revenue by centre chart data
     */
    public function revenueByCentre(Request $request)
    {
        $result = $this->revenueService->getRevenueByCentre($request->type ?? '', $request);
        $data = $result['data'];
        $total = $result['total'];

        // Calculate percentages
        $totalValue = array_sum(array_column(array_slice($data, 1), 1));
        for ($i = 1; $i < count($data); $i++) {
            $percentage = $totalValue != 0 ? ($data[$i][1] / $totalValue) * 100 : 0;
            $data[$i][0] = $data[$i][0] . " (" . number_format($percentage ?? 0, 1) . "%)";
        }

        return ApiHelper::apiResponse(200, 'revenue by centre data', true, [
            'pie' => $data,
            'total' => number_format($total ?? 0, 2),
        ]);
    }

    /**
     * Get collection by service category
     */
    public function collectionByServiceCategory(Request $request)
    {
        $result = $this->revenueService->getCollectionByServiceCategory($request->type ?? '');
        
        return ApiHelper::apiResponse(200, 'service data', true, [
            'pie' => $result['data'],
            'colors' => $result['colors'] ?? [],
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    /**
     * Get revenue by service category
     */
    public function revenueByServiceCategory(Request $request)
    {
        $result = $this->revenueService->getRevenueByServiceCategory($request->type ?? '', $request);
        $data = $result['data'];
        $total = $result['total'];
        $colors = $result['colors'] ?? [];
        $day = $request->type ?: 'today';
        $dataArray = $data[$day] ?? [];

        // Calculate percentage for each slice
        if (count($dataArray) > 1) {
            $totalValue = array_sum(array_column(array_slice($dataArray, 1), 1));
            for ($i = 1; $i < count($dataArray); $i++) {
                $percentage = $totalValue != 0 ? ($dataArray[$i][1] / $totalValue) * 100 : 0;
                $dataArray[$i][0] = $dataArray[$i][0] . " (" . number_format($percentage, 1) . "%)";
            }
        }
        $data[$day] = $dataArray;

        return ApiHelper::apiResponse(200, 'service data', true, [
            'pie' => $data,
            'colors' => $colors,
            'total' => number_format($total ?? 0, 2),
        ]);
    }

    /**
     * Get revenue by service
     */
    public function revenueByService(Request $request)
    {
        $result = $this->revenueService->getRevenueByService($request->type ?? '', $request);
        
        return ApiHelper::apiResponse(200, 'service data', true, [
            'pie' => $result['data'],
            'colors' => $result['colors'] ?? [],
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    /**
     * Get appointment by status chart data
     */
    public function appointmentByStatus(Request $request)
    {
        $period = $request->period ?? 'today';
        $appointmentTypeId = $request->type ?? config('constants.appointment_type_consultancy');
        $performance = $request->performance ?? false;
        
        $result = $this->chartService->getAppointmentByStatus($period, $appointmentTypeId, $performance);
        
        // Format response to match JS expectations (pie[period])
        return ApiHelper::apiResponse(200, 'appointment status data', true, [
            'pie' => [$period => $result['chartData'] ?? []],
            'colors' => $result['colors'] ?? [],
        ]);
    }

    /**
     * Get appointment by type chart data
     */
    public function appointmentByType(Request $request)
    {
        $period = $request->period ?? 'today';
        $appointmentTypeId = $request->type ?? config('constants.appointment_type_consultancy');
        $performance = $request->performance ?? false;
        
        $result = $this->chartService->getAppointmentByType($period, $appointmentTypeId, $performance);
        
        // Format response to match JS expectations (pie[period])
        return ApiHelper::apiResponse(200, 'appointment type data', true, [
            'pie' => [$period => $result['chartData'] ?? []],
            'colors' => $result['colors'] ?? [],
        ]);
    }

    /**
     * Get centre wise arrival data
     */
    public function centreWiseArrival(Request $request)
    {
        $period = $request->period ?? 'today';
        $centreId = $request->centre_id ?? 'All';
        $result = $this->chartService->getCentreWiseArrival($period, $centreId);
        
        return ApiHelper::apiResponse(200, 'centre wise arrival data', true, [
            'bar' => $result['labels'] ?? [],
            'total' => $result['data']['total'] ?? [],
            'arrived' => $result['data']['arrived'] ?? [],
            'walkin' => $result['data']['walkin'] ?? [],
        ]);
    }

    /**
     * Get CSR wise arrival data
     */
    public function csrWiseArrival(Request $request)
    {
        $period = $request->period ?? 'today';
        $userId = $request->user_id ?? 'All';
        $result = $this->chartService->getCSRWiseArrival($period, $userId);
        
        return ApiHelper::apiResponse(200, 'csr wise arrival data', true, [
            'bar' => $result['labels'] ?? [],
            'total' => $result['data']['total'] ?? [],
            'arrived' => $result['data']['arrived'] ?? [],
        ]);
    }

    /**
     * Get call wise arrival data
     */
    public function callWiseArrival(Request $request)
    {
        $result = $this->chartService->getCallWiseArrival($request);
        
        return ApiHelper::apiResponse(200, 'call wise arrival data', true, $result);
    }

    /**
     * Get doctor wise conversion data
     */
    public function doctorWiseConversion(Request $request)
    {
        try {
            $period = $request->period ?? 'today';
            $centreId = $request->centre_id ?? 'All';
            $docId = $request->doc_id ?? null;
            $result = $this->chartService->getDoctorWiseConversion($period, $centreId, $docId);
            
            // Format categories for table display
            $categories = [];
            $labels = $result['labels'] ?? [];
            $appointmentsInfo = $result['appointments_info'] ?? [];
            
            foreach ($appointmentsInfo as $index => $info) {
                $categories[] = [
                    'service' => $labels[$index] ?? '',
                    'total_arrival' => $info['total'] ?? 0,
                    'total_conversion' => $info['converted'] ?? 0,
                    'avg' => $info['converted'] > 0 ? ($info['conversion_spend'] / $info['converted']) : 0,
                ];
            }
            
            return ApiHelper::apiResponse(200, 'doctor wise conversion data', true, [
                'labels' => $labels,
                'total_appointments' => $result['data']['total_appointments'] ?? [],
                'converted_appointments' => $result['data']['converted_appointments'] ?? [],
                'categories' => $categories,
                'sum_val' => $result['sum_val'] ?? 0,
            ]);
        } catch (\Exception $e) {
            \Log::error('doctorWiseConversion Error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return ApiHelper::apiResponse(500, $e->getMessage(), false, []);
        }
    }

    /**
     * Get doctor wise feedback data
     */
    public function doctorWiseFeedback(Request $request)
    {
        $period = $request->period ?? 'today';
        $centreId = $request->centre_id ?? 'All';
        $docId = $request->doc_id ?? null;
        $result = $this->chartService->getDoctorWiseFeedback($period, $centreId, $docId);
        
        return ApiHelper::apiResponse(200, 'doctor wise feedback data', true, [
            'labels' => $result['labels'] ?? [],
            'rating' => $result['data']['rating'] ?? [],
            'total' => $result['data']['total'] ?? [],
            'feedback_stats' => $result['feedback_stats'] ?? null,
        ]);
    }

    /**
     * Get dashboard config data (centres, roles, etc.)
     */
    public function getConfig()
    {
        $userCentres = DashboardHelper::getUserCentres();
        $centresExclude = ['All South Region', 'All Central Region', 'All Centres'];
        
        $centres = Locations::whereIn('id', $userCentres)
            ->whereNotIn('name', $centresExclude)
            ->where('active', 1)
            ->select('id', 'name')
            ->get();
        
        $user = Auth::user();
        $isAdmin = $user->hasRole('Administrator') || 
            $user->hasRole('Super-Admin') || 
            $user->hasRole('Head of Operations') || 
            $user->hasRole('Finance') ||
            $user->hasRole('HRM');
        $isCSRRole = $user->hasRole('CSR Supervisor') || 
            $user->hasRole('Social Lead') || 
            $user->hasRole('CSR');
        
        $csrUsers = collect();
        if ($isCSRRole) {
            $csrRoleIds = \App\Models\RoleHasUsers::whereIn('role_id', [2, 3, 24])->pluck('user_id');
            $csrUsers = User::whereIn('id', $csrRoleIds)
                ->where('active', 1)
                ->select('id', 'name')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'centres' => $centres,
                'firstCentre' => $centres->first(),
                'isAdmin' => $isAdmin,
                'isCSRRole' => $isCSRRole,
                'hasMultipleCentres' => $isAdmin || count($centres) > 1,
                'csrUsers' => $csrUsers,
                'permissions' => [
                    'dashboard_recent_activities' => Gate::allows('dashboard_recent_activities'),
                    'dashboard_upcomings' => Gate::allows('dashboard_upcomings'),
                    'dashboard_unattended_report' => Gate::allows('dashboard_unattended_report'),
                    'dashboard_overdue_treatments' => Gate::allows('dashboard_overdue_treatments'),
                    'dashboard_staff_wise_arrival' => Gate::allows('dashboard_staff_wise_arrival'),
                    'dashboard_doctor_wise_conversion' => Gate::allows('dashboard_doctor_wise_conversion'),
                    'dashboard_doctor_wise_feedback' => Gate::allows('dashboard_doctor_wise_feedback'),
                    'dashboard_upselling_report' => Gate::allows('dashboard_upselling_report'),
                ]
            ]
        ]);
    }

    /**
     * Get unattended payments with pagination (lazy loading)
     * Shows patients where:
     * - First payment is 7+ days old
     * - No treatment appointment booked
     * - Balance >= 500 PKR
     */
    public function unattendedPayments(Request $request)
    {
        try {
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 10);
            $offset = ($page - 1) * $perPage;
            
            $centerIds = DashboardHelper::getUserCentres();
            $centerIdsStr = implode(',', array_map('intval', $centerIds));
            $sevenDaysAgo = Carbon::now()->subDays(7)->format('Y-m-d H:i:s');
            $today = Carbon::now()->format('Y-m-d');
            
            // Get patients with:
            // 1. Arrived consultation appointment
            // 2. First payment >= 7 days ago
            // 3. Balance >= 500
            // 4. No treatment appointments booked (appointment_type_id = 2)
            $sql = "
                SELECT 
                    u.id as patient_id,
                    u.name,
                    COALESCE(SUM(CASE WHEN pa.cash_flow = 'in' AND pa.is_cancel = 0 AND pa.is_tax = 0 AND pa.is_adjustment = 0 AND pa.is_refund = 0 THEN pa.cash_amount ELSE 0 END), 0) as cash_in,
                    COALESCE(SUM(CASE WHEN pa.cash_flow = 'out' AND pa.is_cancel = 0 THEN pa.cash_amount ELSE 0 END), 0) as cash_out,
                    MIN(CASE WHEN pa.cash_flow = 'in' AND pa.cash_amount > 0 AND pa.is_tax = 0 THEN pa.created_at END) as conversion_date
                FROM users u
                INNER JOIN appointments a ON u.id = a.patient_id
                    AND a.appointment_type_id = 1 
                    AND a.base_appointment_status_id = 2 
                    AND a.location_id IN ({$centerIdsStr})
                LEFT JOIN package_advances pa ON u.id = pa.patient_id
                WHERE u.user_type_id = 3 AND u.active = 1
                    AND NOT EXISTS (
                        SELECT 1 FROM appointments t 
                        WHERE t.patient_id = u.id 
                        AND t.appointment_type_id = 2
                        AND t.location_id IN ({$centerIdsStr})
                    )
                GROUP BY u.id, u.name
                HAVING (cash_in - cash_out) >= 500
                    AND conversion_date IS NOT NULL
                    AND conversion_date <= ?
                ORDER BY conversion_date DESC
                LIMIT ? OFFSET ?
            ";

            $patients = \DB::select($sql, [$sevenDaysAgo, $perPage + 1, $offset]);
            
            $hasMore = count($patients) > $perPage;
            if ($hasMore) array_pop($patients);

            $patientData = [];
            foreach ($patients as $p) {
                $patientData[] = [
                    'patient_id' => $p->patient_id,
                    'name' => $p->name,
                    'is_treatment' => 0,
                    'balance' => (float) ($p->cash_in - $p->cash_out),
                    'created_at' => $p->conversion_date ? Carbon::parse($p->conversion_date)->format('Y-m-d') : null,
                ];
            }

            return ApiHelper::apiResponse(200, 'unattended payments', true, [
                'patient_data' => $patientData,
                'current_page' => $page,
                'has_more' => $hasMore,
            ]);
        } catch (\Exception $e) {
            \Log::error('unattendedPayments: ' . $e->getMessage());
            return ApiHelper::apiResponse(500, $e->getMessage(), false, []);
        }
    }

    /**
     * Get overdue treatments with pagination (lazy loading)
     * Shows patients where:
     * - Has treatment appointments (appointment_type_id = 2)
     * - At least one treatment arrived (base_appointment_status_id = 2)
     * - Last treatment >= 31 days ago
     * - No future treatments scheduled
     * - Balance > 500 PKR
     */
    public function overdueTreatments(Request $request)
    {
        try {
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 10);
            $offset = ($page - 1) * $perPage;
            
            $centerIds = DashboardHelper::getUserCentres();
            $centerIdsStr = implode(',', array_map('intval', $centerIds));
            $thirtyOneDaysAgo = Carbon::now()->subDays(31)->format('Y-m-d');
            $today = Carbon::now()->format('Y-m-d');

            // Get patients with:
            // 1. Treatment appointments (appointment_type_id = 2) that arrived (status = 2)
            // 2. Last treatment scheduled_date >= 31 days ago
            // 3. No future treatment appointments scheduled
            // 4. Balance > 500
            $sql = "
                SELECT 
                    u.id as patient_id,
                    u.name,
                    MAX(a.scheduled_date) as last_arrived,
                    COALESCE(SUM(CASE WHEN pa.cash_flow = 'in' AND pa.is_cancel = 0 AND pa.is_tax = 0 AND pa.is_adjustment = 0 AND pa.is_refund = 0 THEN pa.cash_amount ELSE 0 END), 0) as cash_in,
                    COALESCE(SUM(CASE WHEN pa.cash_flow = 'out' AND pa.is_cancel = 0 THEN pa.cash_amount ELSE 0 END), 0) as cash_out
                FROM users u
                INNER JOIN appointments a ON u.id = a.patient_id
                    AND a.appointment_type_id = 2
                    AND a.base_appointment_status_id = 2 
                    AND a.location_id IN ({$centerIdsStr})
                LEFT JOIN package_advances pa ON u.id = pa.patient_id
                WHERE u.user_type_id = 3 AND u.active = 1
                    AND NOT EXISTS (
                        SELECT 1 FROM appointments f 
                        WHERE f.patient_id = u.id 
                        AND f.appointment_type_id = 2
                        AND f.scheduled_date >= ?
                        AND f.location_id IN ({$centerIdsStr})
                    )
                GROUP BY u.id, u.name
                HAVING last_arrived <= ?
                    AND (cash_in - cash_out) > 500
                ORDER BY last_arrived DESC
                LIMIT ? OFFSET ?
            ";

            $patients = \DB::select($sql, [$today, $thirtyOneDaysAgo, $perPage + 1, $offset]);
            
            $hasMore = count($patients) > $perPage;
            if ($hasMore) array_pop($patients);

            $patientData = [];
            foreach ($patients as $p) {
                $patientData[] = [
                    'patient_id' => $p->patient_id,
                    'name' => $p->name,
                    'balance' => (float) ($p->cash_in - $p->cash_out),
                    'scheduled_date' => $p->last_arrived,
                ];
            }

            return ApiHelper::apiResponse(200, 'overdue treatments', true, [
                'patient_data' => $patientData,
                'current_page' => $page,
                'has_more' => $hasMore,
            ]);
        } catch (\Exception $e) {
            \Log::error('overdueTreatments: ' . $e->getMessage());
            return ApiHelper::apiResponse(500, $e->getMessage(), false, []);
        }
    }

    /**
     * Get doctor upselling data - proxies to UpsellingReportController
     */
    public function doctorUpsellingData(Request $request)
    {
        $controller = app(\App\Http\Controllers\UpsellingReportController::class);
        return $controller->getDoctorUpsellingData($request);
    }
}
