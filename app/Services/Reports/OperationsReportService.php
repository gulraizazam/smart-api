<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Services\Reports\Enums\OperationsReportType;
use App\Services\Reports\Operations\AgentReport;
use App\Services\Reports\Operations\DarReport;
use App\Services\Reports\Operations\WalkingReport;
use Illuminate\Support\Facades\Auth;

class OperationsReportService
{
    public function __construct(
        private readonly DarReport $darReport,
        private readonly WalkingReport $walkingReport,
        private readonly AgentReport $agentReport,
    ) {}

    public function generate(
        OperationsReportType $reportType,
        array $requestData,
        ?string $startDate,
        ?string $endDate,
    ): array {
        $accountId = Auth::user()->account_id;

        return match ($reportType) {
            OperationsReportType::DarReport => $this->darReport->generate(
                startDate: $startDate,
                endDate: $endDate,
                requestData: $requestData,
                accountId: $accountId,
            ),
            OperationsReportType::WalkingReport => $this->walkingReport->generate(
                startDate: $startDate,
                endDate: $endDate,
                requestData: $requestData,
                accountId: $accountId,
            ),
            OperationsReportType::AgentReport => $this->agentReport->generate(
                startDate: $startDate,
                endDate: $endDate,
                requestData: $requestData,
                accountId: $accountId,
            ),
        };
    }

    public function getReportInstance(OperationsReportType $reportType): object
    {
        return match ($reportType) {
            OperationsReportType::DarReport => $this->darReport,
            OperationsReportType::WalkingReport => $this->walkingReport,
            OperationsReportType::AgentReport => $this->agentReport,
        };
    }
}
