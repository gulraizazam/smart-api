<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\DashboardHelper;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\Dashboard\DashboardRevenueService;
use App\Services\Dashboard\DashboardChartService;
use App\Services\Dashboard\DashboardWidgetService;
use App\Services\Upselling\UpsellingService;
use App\HelperModule\ApiHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected string $success;
    protected string $error;

    public function __construct(
        protected readonly DashboardStatsService $statsService,
        protected readonly DashboardRevenueService $revenueService,
        protected readonly DashboardChartService $chartService,
        protected readonly DashboardWidgetService $widgetService,
        protected readonly UpsellingService $upsellingService,
    ) {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
    }

    public function getStats(Request $request): JsonResponse
    {
        $userCentres = DashboardHelper::getUserCentres();
        [$startDate, $endDate] = DashboardHelper::getDateRangeFromRequest($request);

        $data = array_merge(
            $this->statsService->getConsultancies($startDate, $endDate, $userCentres),
            $this->statsService->getTreatments($startDate, $endDate, $userCentres),
            $this->revenueService->getSalesByCentre($userCentres, $startDate, $endDate),
            $this->revenueService->getCollectionStats($userCentres, $request->type, $request),
            DashboardHelper::getDateTimeInfo(),
        );

        return ApiHelper::apiResponse($this->success, 'Dashboard stats.', true, $data);
    }

    public function getActivities(Request $request): JsonResponse
    {
        try {
            $data = $this->widgetService->getActivities(
                (int) $request->get('page', 1),
                (int) $request->get('per_page', 10),
            );

            return ApiHelper::apiResponse($this->success, 'Activities loaded.', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function collectionByCentre(Request $request): JsonResponse
    {
        try {
            $result = $this->revenueService->getCollectionByCentre($request->type ?? '', $request);
            $day = $request->type ?: 'today';

            $data = $result['data'];

            // Ensure all period arrays are properly indexed for JSON
            foreach ($data as $key => $value) {
                $data[$key] = is_array($value) ? array_values($value) : [];
            }
            $data[$day] = $this->appendPercentages($result['data'][$day] ?? []);

            return ApiHelper::apiResponse($this->success, 'Collection by centre data.', true, [
                'pie' => $data,
                'total' => number_format($result['total'], 2),
            ]);
        } catch (\Exception $e) {
            Log::error('collectionByCentre Error: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function revenueByCentre(Request $request): JsonResponse
    {
        $result = $this->revenueService->getRevenueByCentre($request->type ?? '', $request);

        return ApiHelper::apiResponse($this->success, 'Revenue by centre data.', true, [
            'pie' => $this->appendPercentages($result['data']),
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    public function collectionByServiceCategory(Request $request): JsonResponse
    {
        $result = $this->revenueService->getCollectionByServiceCategory($request->type ?? '');

        return ApiHelper::apiResponse($this->success, 'Service data.', true, [
            'pie' => $result['data'],
            'colors' => $result['colors'] ?? [],
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    public function revenueByServiceCategory(Request $request): JsonResponse
    {
        $result = $this->revenueService->getRevenueByServiceCategory($request->type ?? '', $request);
        $day = $request->type ?: 'today';

        $data = $result['data'];
        $data[$day] = $this->appendPercentages($data[$day] ?? []);

        return ApiHelper::apiResponse($this->success, 'Service data.', true, [
            'pie' => $data,
            'colors' => $result['colors'] ?? [],
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    public function revenueByService(Request $request): JsonResponse
    {
        $result = $this->revenueService->getRevenueByService($request->type ?? '', $request);

        return ApiHelper::apiResponse($this->success, 'Service data.', true, [
            'pie' => $result['data'],
            'colors' => $result['colors'] ?? [],
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    public function appointmentByStatus(Request $request): JsonResponse
    {
        $period = $request->period ?? 'today';
        $result = $this->chartService->getAppointmentByStatus(
            $period,
            $request->type ?? config('constants.appointment_type_consultancy'),
            $request->performance ?? false,
        );

        return ApiHelper::apiResponse($this->success, 'Appointment status data.', true, [
            'pie' => [$period => $result['chartData'] ?? []],
            'colors' => $result['colors'] ?? [],
        ]);
    }

    public function appointmentByType(Request $request): JsonResponse
    {
        $period = $request->period ?? 'today';
        $result = $this->chartService->getAppointmentByType(
            $period,
            $request->type ?? config('constants.appointment_type_consultancy'),
            $request->performance ?? false,
        );

        return ApiHelper::apiResponse($this->success, 'Appointment type data.', true, [
            'pie' => [$period => $result['chartData'] ?? []],
            'colors' => $result['colors'] ?? [],
        ]);
    }

    public function centreWiseArrival(Request $request): JsonResponse
    {
        $result = $this->chartService->getCentreWiseArrival(
            $request->period ?? 'today',
            $request->centre_id ?? 'All',
        );

        return ApiHelper::apiResponse($this->success, 'Centre wise arrival data.', true, [
            'bar' => $result['labels'] ?? [],
            'total' => $result['data']['total'] ?? [],
            'arrived' => $result['data']['arrived'] ?? [],
            'walkin' => $result['data']['walkin'] ?? [],
        ]);
    }

    public function csrWiseArrival(Request $request): JsonResponse
    {
        $result = $this->chartService->getCSRWiseArrival(
            $request->period ?? 'today',
            $request->user_id ?? 'All',
        );

        return ApiHelper::apiResponse($this->success, 'CSR wise arrival data.', true, [
            'bar' => $result['labels'] ?? [],
            'total' => $result['data']['total'] ?? [],
            'arrived' => $result['data']['arrived'] ?? [],
        ]);
    }

    public function callWiseArrival(Request $request): JsonResponse
    {
        $result = $this->chartService->getCallWiseArrival($request->period, $request->user_id);

        return ApiHelper::apiResponse($this->success, 'Call wise arrival data.', true, $result);
    }

    public function doctorWiseConversion(Request $request): JsonResponse
    {
        try {
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

            return ApiHelper::apiResponse($this->success, 'Doctor wise conversion data.', true, [
                'labels' => $labels,
                'total_appointments' => $result['data']['total_appointments'] ?? [],
                'converted_appointments' => $result['data']['converted_appointments'] ?? [],
                'categories' => $categories,
                'sum_val' => $result['sum_val'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('doctorWiseConversion Error: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function doctorWiseFeedback(Request $request): JsonResponse
    {
        $result = $this->chartService->getDoctorWiseFeedback(
            $request->period ?? 'today',
            $request->centre_id ?? 'All',
            $request->doc_id,
        );

        return ApiHelper::apiResponse($this->success, 'Doctor wise feedback data.', true, [
            'labels' => $result['labels'] ?? [],
            'rating' => $result['data']['rating'] ?? [],
            'total' => $result['data']['total'] ?? [],
            'feedback_stats' => $result['feedback_stats'] ?? null,
        ]);
    }

    public function getConfig(): JsonResponse
    {
        try {
            $data = $this->widgetService->getConfig();

            return ApiHelper::apiResponse($this->success, 'Config loaded.', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function unattendedPayments(Request $request): JsonResponse
    {
        try {
            $data = $this->widgetService->getUnattendedPayments(
                (int) $request->input('page', 1),
                (int) $request->input('per_page', 10),
            );

            return ApiHelper::apiResponse($this->success, 'Unattended payments.', true, $data);
        } catch (\Exception $e) {
            Log::error('unattendedPayments: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function overdueTreatments(Request $request): JsonResponse
    {
        try {
            $data = $this->widgetService->getOverdueTreatments(
                (int) $request->input('page', 1),
                (int) $request->input('per_page', 10),
            );

            return ApiHelper::apiResponse($this->success, 'Overdue treatments.', true, $data);
        } catch (\Exception $e) {
            Log::error('overdueTreatments: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function doctorUpsellingData(Request $request): JsonResponse
    {
        try {
            [$startDate, $endDate] = UpsellingService::resolvePeriodDates($request->period ?: 'thismonth');
            $data = $this->upsellingService->getDoctorUpsellingData($request->centre_id, $startDate, $endDate);

            return ApiHelper::apiResponse($this->success, 'Doctor upselling data.', true, $data);
        } catch (\Exception $e) {
            Log::error('Doctor Upselling Data Error: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Append percentage labels to pie chart data (skip header row at index 0).
     */
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
