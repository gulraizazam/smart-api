<?php

declare(strict_types=1);

namespace App\Services\Reports\Operations;

use App\Reports\Operations;
use App\Services\Reports\Operations\Concerns\GeneratesOperationsExcel;

class DarReport
{
    use GeneratesOperationsExcel;

    public function generate(
        ?string $startDate,
        ?string $endDate,
        array $requestData,
        int $accountId,
    ): array {
        [$reportData, $locationData] = Operations::dar_report($requestData, $accountId);

        return [
            'reportData' => $reportData,
            'locationData' => $locationData,
            'newtreatment' => 0,
            'newconsultant' => 0,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    public function generateExcel(array $result): void
    {
        $this->buildExcel(
            reportData: $result['reportData'],
            startDate: $result['start_date'] ?? '',
            endDate: $result['end_date'] ?? '',
            locationData: $result['locationData'],
            reportName: 'DAR Report',
            type: 'dar',
        );
    }
}
