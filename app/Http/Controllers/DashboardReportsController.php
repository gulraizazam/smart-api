<?php

declare(strict_types=1);
namespace App\Http\Controllers;

use App\Helpers\DashboardHelper;
use App\Helpers\GeneralFunctions;
use App\Models\Locations;
use App\Models\Services;
use App\Models\User;
use App\Services\Dashboard\DashboardChartService;
use App\Services\Dashboard\DashboardRevenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DashboardReportsController extends Controller
{
    public function __construct(
        protected readonly DashboardRevenueService $revenueService,
        protected readonly DashboardChartService $chartService,
    ) {}

    public function collectionByCentre(Request $request): JsonResponse
    {
        $day = $request->type ?? 'today';
        $result = $this->revenueService->getCollectionByCentre($day, $request);

        $data = $result['data'];
        $data[$day] = $this->appendPercentages($data[$day] ?? []);

        return $this->successResponse('Pie chart data.', [
            'pie' => $data,
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    public function collectionByServiceCategory(Request $request): JsonResponse
    {
        $type = match (true) {
            (bool) $request->today => 'today',
            (bool) $request->yesterday => 'yesterday',
            (bool) $request->last7days => 'last7days',
            (bool) $request->thismonth => 'thismonth',
            (bool) $request->lastmonth => 'lastmonth',
            default => '',
        };

        $result = $this->revenueService->getCollectionByServiceCategory($type, $request);

        return $this->successResponse('Service data.', [
            'pie' => $result['data'],
            'colors' => $result['colors'],
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    public function revenueByServiceCategory(Request $request): JsonResponse
    {
        $result = $this->revenueService->getRevenueByServiceCategory($request->type ?? '', $request);
        $day = $request->type ?: 'today';

        $data = $result['data'];
        $data[$day] = $this->appendPercentages($data[$day] ?? []);

        return $this->successResponse('Service data.', [
            'pie' => $data,
            'colors' => $result['colors'],
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    public function myCollectionByCentre(Request $request): JsonResponse
    {
        $result = $this->revenueService->getMyCollectionByCentre($request->type ?? '', $request);

        return $this->successResponse('Pie chart data.', [
            'pie' => $result['data'],
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    public function revenueByCentre(Request $request): JsonResponse
    {
        $result = $this->revenueService->getRevenueByCentre($request->type ?? '', $request);

        return $this->successResponse('Bar chart data.', [
            'pie' => $this->appendPercentages($result['data']),
            'total' => number_format($result['total'], 2),
        ]);
    }

    public function myRevenueByCentre(Request $request): JsonResponse
    {
        $result = $this->revenueService->getMyRevenueByCentre($request->type ?? '', $request);

        return $this->successResponse('Bar chart data.', [
            'pie' => $result['data'],
            'total' => number_format($result['total'], 2),
        ]);
    }

    public function revenueByService(Request $request): JsonResponse
    {
        $result = $this->revenueService->getRevenueByService($request->type ?? '', $request, 'dashboard_revenue_by_service');
        $day = $request->type ?: 'today';

        $data = $result['data'];
        $data[$day] = $this->appendPercentages($data[$day] ?? []);

        return $this->successResponse('Service data.', [
            'pie' => $data,
            'colors' => $result['colors'],
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    public function myRevenueByService(Request $request): JsonResponse
    {
        $result = $this->revenueService->getRevenueByService($request->period ?? '', $request, 'dashboard_my_revenue_by_service');

        return $this->successResponse('Service data.', [
            'pie' => $result['data'],
            'colors' => $result['colors'],
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    public function appointmentByStatus(Request $request): JsonResponse
    {
        $day = $request->period ?: 'today';

        if (!Gate::allows('dashboard_appointment_by_status')) {
            return $this->successResponse('Service data.', [
                'pie' => [], 'colors' => [], 'total' => 0,
            ]);
        }

        $result = $this->chartService->getAppointmentByStatus($day, $request->type, $request->get('performance'));
        $chartData = $this->appendPercentages($result['chartData']);

        return $this->successResponse('Service data.', [
            'pie' => [$day => $chartData],
            'colors' => $result['colors'],
            'total' => 0,
        ]);
    }

    public function appointmentByType(Request $request): JsonResponse
    {
        $period = $request->period ?: 'today';
        $result = $this->chartService->getAppointmentByType($period, null, $request->get('performance'));

        return $this->successResponse('Service data.', [
            'pie' => [$period => $result['chartData']],
            'colors' => $result['colors'] ?: ['#3375de', '#c8cf19', '#cf7a19', '#cf1931', '#19cf43', '#a119cf'],
            'total' => $result['total'],
        ]);
    }

    public function getChild(Request $request): JsonResponse
    {
        $child = $request->child_id
            ? (Services::find($request->child_id)?->name ?? 'N/A')
            : 'N/A';

        return $this->successResponse('Service data.', [
            'child' => $child,
        ]);
    }

    public function centreWiseArrival(Request $request): JsonResponse
    {
        try {
            $result = $this->chartService->getCentreWiseArrival(
                $request->period ?: 'thismonth',
                $request->centre_id ?? 'All',
            );

            return $this->successResponse('Centre wise arrival data.', [
                'bar' => $result['labels'] ?? [],
                'total' => $result['data']['total'] ?? [],
                'arrived' => $result['data']['arrived'] ?? [],
                'walkin' => $result['data']['walkin'] ?? [],
            ]);
        } catch (\Exception $e) {
            Log::error('CentreWiseArrival Error: ' . $e->getMessage());

            return $this->successResponse('Centre wise arrival data.', [
                'bar' => [], 'total' => [], 'arrived' => [], 'walkin' => [],
            ]);
        }
    }

    public function csrWiseArrival(Request $request): JsonResponse
    {
        $result = $this->chartService->getCSRWiseArrivalStats(
            $request->period ?: 'thismonth',
            $request->user_id ?? 'All',
        );

        return $this->successResponse('Centre wise arrival data.', [
            'bar' => $result['labels'],
            'total' => $result['total'],
            'arrived' => $result['arrived'],
        ]);
    }

    public function callWiseArrival(Request $request): JsonResponse
    {
        $result = $this->chartService->getCallWiseArrival($request->period ?: 'today', $request->user_id);

        return $this->successResponse('CSR wise arrival data.', [
            'bar' => $result['labels'],
            'total' => $result['total'],
            'arrived' => $result['arrived'],
        ]);
    }

    public function doctorWiseFeedback(Request $request): JsonResponse
    {
        $result = $this->chartService->getDoctorWiseFeedback(
            $request->period ?? 'today',
            $request->centre_id ?? 'all',
            $request->doc_id,
        );

        return $this->successResponse('Doctor wise feedback data.', [
            'labels' => $result['labels'] ?? [],
            'rating' => $result['data']['rating'] ?? [],
            'total' => $result['data']['total'] ?? [],
        ]);
    }

    public function allDoctorsWiseConversion(Request $request): JsonResponse
    {
        $result = $this->chartService->getDoctorWiseConversion(
            $request->period ?? 'today',
            $request->centre_id ?? 'All',
            $request->doc_id,
        );

        // Build categories for table display
        $categories = [];
        $labels = $result['labels'] ?? [];
        $appointmentsInfo = $result['appointments_info'] ?? [];

        foreach ($appointmentsInfo as $index => $info) {
            $categories[] = [
                'service' => $labels[$index] ?? '',
                'total_arrival' => $info['total'] ?? 0,
                'total_conversion' => $info['converted'] ?? 0,
                'avg' => ($info['converted'] ?? 0) > 0
                    ? ($info['conversion_spend'] / $info['converted'])
                    : 0,
            ];
        }

        return $this->successResponse('Doctor wise conversion data.', [
            'labels' => $labels,
            'total_appointments' => $result['data']['total_appointments'] ?? [],
            'converted_appointments' => $result['data']['converted_appointments'] ?? [],
            'categories' => $categories,
            'category_total' => [],
            'sum_val' => $result['sum_val'] ?? 0,
        ]);
    }

    public function getCentreDoctors(Request $request): JsonResponse
    {
        $consultants = $this->chartService->getCentreDoctors($request->centre_id ?? 'All');

        return $this->successResponse('Doctors loaded.', ['doctors' => $consultants]);
    }

    public function followUpReport(): View
    {
        $accountId = Auth::user()->account_id;
        $locations = Locations::getActiveRecordsByCity('', DashboardHelper::getUserCentres(), $accountId);
        $Users = User::getAllRecords($accountId)->getDictionary();

        return view('admin.reports.followup', compact('locations', 'Users'));
    }

    public function followUpReportMonthly(): View
    {
        $accountId = Auth::user()->account_id;
        $locations = Locations::getActiveRecordsByCity('', DashboardHelper::getUserCentres(), $accountId);
        $Users = User::getAllRecords($accountId)->getDictionary();

        return view('admin.reports.followupmonthly', compact('locations', 'Users'));
    }

    public function loadFollowUpReport(Request $request): View
    {
        $startDate = null;
        $endDate = null;

        if ($request->date_range) {
            $dateRange = explode(' - ', $request->date_range);
            $startDate = date('Y-m-d', strtotime($dateRange[0]));
            $endDate = date('Y-m-d', strtotime($dateRange[1]));
        }

        $where = [];
        if ($startDate && $endDate) {
            $where[] = ['created_at', '>=', $startDate . ' 00:00:00'];
            $where[] = ['created_at', '<=', $endDate . ' 23:59:00'];
        }
        if ($request->patient_id) {
            $where[] = ['patient_id', '=', $request->patient_id];
        }

        $data = $request->all();

        if ($request->report_type === 'monthly') {
            $patient_data = GeneralFunctions::LoadPatientFollowUpReportMonthly($data, $where);
            return view('admin.reports.patients_follow_up_report_monthly', compact('patient_data', 'data', 'startDate', 'endDate'));
        }

        $patient_data = GeneralFunctions::PatientFollowUpReport($data, $where);
        return view('admin.reports.patients_follow_up_report', compact('patient_data', 'data', 'startDate', 'endDate'));
    }

    public function viewFeedback(int $doctorId): View
    {
        $feedbackData = $this->chartService->getDoctorFeedbackByService($doctorId);

        return view('admin.reports.feedbackBarChart', compact('feedbackData'));
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    protected function appendPercentages(array $dataArray): array
    {
        if (count($dataArray) <= 1) {
            return array_values($dataArray);
        }

        $dataArray = array_values($dataArray);
        $totalValue = array_sum(array_column(array_slice($dataArray, 1), 1));

        for ($i = 1; $i < count($dataArray); $i++) {
            if (isset($dataArray[$i][0], $dataArray[$i][1])) {
                $percentage = $totalValue != 0 ? ($dataArray[$i][1] / $totalValue) * 100 : 0;
                $dataArray[$i][0] .= ' (' . number_format($percentage, 1) . '%)';
            }
        }

        return $dataArray;
    }
}
