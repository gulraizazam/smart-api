<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\OperationsReportRequest;
use App\Http\Resources\Reports\OperationsReportResource;
use App\Services\Reports\OperationsReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OperationsReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OperationsReportService $reportService,
    ) {}

    public function __invoke(OperationsReportRequest $request): JsonResponse
    {
        $reportType = $request->reportType();

        if (! Gate::allows($reportType->gate())) {
            return $this->errorResponse('Unauthorized', 403);
        }

        try {
            $result = $this->reportService->generate(
                reportType: $reportType,
                requestData: $request->validated(),
                startDate: $request->startDate(),
                endDate: $request->endDate(),
            );

            return $this->successResponse('Report generated successfully', [
                'rows' => OperationsReportResource::collection(
                    collect($result['reportData'] ?? [])
                ),
                'location_data' => $result['locationData'] ?? [],
                'meta' => [
                    'report_type' => $reportType->value,
                    'start_date' => $request->startDate(),
                    'end_date' => $request->endDate(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'OperationsReport');
        }
    }
}
