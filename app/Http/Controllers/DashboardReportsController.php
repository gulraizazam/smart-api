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
use Illuminate\View\View;

class DashboardReportsController extends Controller
{
    public function __construct(
        protected readonly DashboardRevenueService $revenueService,
        protected readonly DashboardChartService $chartService,
    ) {}

    public function myCollectionByCentre(Request $request): JsonResponse
    {
        $result = $this->revenueService->getMyCollectionByCentre($request->type ?? '', $request);

        return $this->successResponse('Pie chart data.', [
            'pie' => $result['data'],
            'total' => number_format($result['total'] ?? 0, 2),
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

    public function myRevenueByService(Request $request): JsonResponse
    {
        $result = $this->revenueService->getRevenueByService($request->period ?? '', $request, 'dashboard_my_revenue_by_service');

        return $this->successResponse('Service data.', [
            'pie' => $result['data'],
            'colors' => $result['colors'],
            'total' => number_format($result['total'] ?? 0, 2),
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

    public function allDoctorsWiseConversion(Request $request): JsonResponse
    {
        $result = $this->chartService->getDoctorWiseConversion(
            $request->period ?? 'today',
            $request->centre_id ?? 'All',
            $request->doc_id,
        );

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
            $where[] = ['created_at', '>=', $startDate.' 00:00:00'];
            $where[] = ['created_at', '<=', $endDate.' 23:59:00'];
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
}
