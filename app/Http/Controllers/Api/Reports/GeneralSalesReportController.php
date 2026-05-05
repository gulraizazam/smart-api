<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\GeneralSalesReportRequest;
use App\Http\Resources\Reports\CollectionByServiceResource;
use App\Http\Resources\Reports\ConversionReportResource;
use App\Http\Resources\Reports\DailyEmployeeStatsResource;
use App\Http\Resources\Reports\GenderWiseRevenueResource;
use App\Http\Resources\Reports\GeneralRevenueDetailResource;
use App\Http\Resources\Reports\GeneralRevenueSummaryResource;
use App\Http\Resources\Reports\ServicesSoldResource;
use App\Services\Reports\Enums\ReportType;
use App\Services\Reports\GeneralSalesReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class GeneralSalesReportController extends Controller
{
    public function __construct(
        private readonly GeneralSalesReportService $reportService,
    ) {}

    public function __invoke(GeneralSalesReportRequest $request): JsonResponse
    {
        $reportType = $request->reportType();

        $gate = $reportType->gate();
        if ($gate && ! Gate::allows($gate)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        try {
            $requestData = $request->validated();

            if ($reportType === ReportType::GeneralRevenueDetail) {
                $requestData['_resolved_location_com_ids'] = $request->resolvedLocationComIds();
            }

            $result = $this->reportService->generate(
                reportType: $reportType,
                requestData: $requestData,
                startDate: $request->startDate(),
                endDate: $request->endDate(),
            );

            $rows = $this->transformRows($reportType, $result);

            return $this->successResponse('Report generated successfully', [
                'rows' => $rows,
                'totals' => $this->extractTotals($reportType, $result),
                'meta' => [
                    'report_type' => $reportType->value,
                    'start_date' => $request->startDate(),
                    'end_date' => $request->endDate(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'GeneralSalesReport');
        }
    }

    private function transformRows(ReportType $reportType, array $result): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        return match ($reportType) {
            ReportType::CollectionByService => CollectionByServiceResource::collection(
                collect($result['reportData'] ?? [])
            ),
            ReportType::DailyEmployeeStats => DailyEmployeeStatsResource::collection(
                collect($result['reportData'] ?? [])
            ),
            ReportType::GeneralRevenueDetail => GeneralRevenueDetailResource::collection(
                collect($result['report_data'] ?? [])
            ),
            ReportType::GeneralRevenueSummary => GeneralRevenueSummaryResource::collection(
                collect($result['report_data'] ?? [])
            ),
            ReportType::ConversionReport => ConversionReportResource::collection(
                collect($result['report_data'] ?? [])
            ),
            ReportType::ServicesSold => ServicesSoldResource::collection(
                $result['soldServices'] ?? collect()
            ),
            ReportType::GenderWiseRevenue => GenderWiseRevenueResource::collection(
                collect($result['reportData'] ?? [])
            ),
        };
    }

    private function extractTotals(ReportType $reportType, array $result): array
    {
        return match ($reportType) {
            ReportType::GeneralRevenueDetail, ReportType::GeneralRevenueSummary => [
                'total_revenue_cash_in' => $result['total_revenue_cash_in'] ?? 0,
                'total_revenue_card_in' => $result['total_revenue_card_in'] ?? 0,
                'total_revenue_bank_in' => $result['total_revenue_bank_in'] ?? 0,
                'total_refund' => $result['total_refund'] ?? 0,
                'total_revenue' => $result['total_revenue'] ?? 0,
            ],
            default => [],
        };
    }
}
