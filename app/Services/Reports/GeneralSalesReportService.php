<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Services\Reports\Enums\ReportType;
use App\Services\Reports\Revenue\CollectionByServiceReport;
use App\Services\Reports\Revenue\ConversionReport;
use App\Services\Reports\Revenue\DailyEmployeeStatsReport;
use App\Services\Reports\Revenue\GenderWiseRevenueReport;
use App\Services\Reports\Revenue\GeneralRevenueDetailReport;
use App\Services\Reports\Revenue\GeneralRevenueSummaryReport;
use App\Services\Reports\Revenue\ServicesSoldReport;
use Illuminate\Support\Facades\Auth;

class GeneralSalesReportService
{
    public function __construct(
        private readonly CollectionByServiceReport $collectionByService,
        private readonly DailyEmployeeStatsReport $dailyEmployeeStats,
        private readonly GeneralRevenueDetailReport $revenueDetail,
        private readonly GeneralRevenueSummaryReport $revenueSummary,
        private readonly ConversionReport $conversion,
        private readonly ServicesSoldReport $servicesSold,
        private readonly GenderWiseRevenueReport $genderWiseRevenue,
    ) {}

    public function generate(ReportType $reportType, array $requestData, ?string $startDate, ?string $endDate): array
    {
        $accountId = Auth::user()->account_id;

        return match ($reportType) {
            ReportType::CollectionByService => $this->collectionByService->generate(
                startDate: $startDate,
                endDate: $endDate,
                locationIds: $this->extractLocationIds($requestData),
                regionId: $requestData['region_id'] ?? null,
                accountId: $accountId,
            ),
            ReportType::DailyEmployeeStats => $this->dailyEmployeeStats->generate(
                startDate: $startDate,
                endDate: $endDate,
                requestData: $requestData,
            ),
            ReportType::GeneralRevenueDetail => $this->revenueDetail->generate(
                startDate: $startDate,
                endDate: $endDate,
                locationIds: $requestData['_resolved_location_com_ids'] ?? [],
                accountId: $accountId,
                genderId: $requestData['gender_id'] ?? 'all',
            ),
            ReportType::GeneralRevenueSummary => $this->revenueSummary->generate(
                startDate: $startDate,
                endDate: $endDate,
                locationIds: $this->extractLocationIds($requestData),
                regionId: $requestData['region_id'] ?? null,
                accountId: $accountId,
            ),
            ReportType::ConversionReport => $this->conversion->generate(
                startDate: $startDate,
                endDate: $endDate,
                requestData: $requestData,
                accountId: $accountId,
            ),
            ReportType::ServicesSold => $this->servicesSold->generate(
                startDate: $startDate,
                endDate: $endDate,
                locationId: $this->extractLocationIds($requestData),
                serviceId: $requestData['service_id'] ?? null,
            ),
            ReportType::GenderWiseRevenue => $this->genderWiseRevenue->generate(
                startDate: $startDate,
                endDate: $endDate,
                locationIds: $this->extractLocationIds($requestData),
            ),
        };
    }

    /**
     * Get the report instance for Excel generation.
     */
    public function getReportInstance(ReportType $reportType): object
    {
        return match ($reportType) {
            ReportType::CollectionByService => $this->collectionByService,
            ReportType::DailyEmployeeStats => $this->dailyEmployeeStats,
            ReportType::GeneralRevenueDetail => $this->revenueDetail,
            ReportType::GeneralRevenueSummary => $this->revenueSummary,
            ReportType::ConversionReport => $this->conversion,
            ReportType::ServicesSold => $this->servicesSold,
            ReportType::GenderWiseRevenue => $this->genderWiseRevenue,
        };
    }

    private function extractLocationIds(array $data): ?array
    {
        $locationId = $data['location_id'] ?? null;

        if ($locationId === null || $locationId === '' || $locationId === []) {
            return null;
        }

        if (is_array($locationId)) {
            $filtered = array_filter($locationId, fn ($val) => $val !== '' && $val !== null);

            return empty($filtered) ? null : array_values($filtered);
        }

        return [$locationId];
    }
}
