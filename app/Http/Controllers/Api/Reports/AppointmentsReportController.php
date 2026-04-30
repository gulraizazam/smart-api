<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\AppointmentsReportRequest;
use App\Http\Resources\Reports\AppointmentsReportResource;
use App\Services\Reports\AppointmentsReportService;
use Illuminate\Http\JsonResponse;

class AppointmentsReportController extends Controller
{
    public function __construct(
        private readonly AppointmentsReportService $reportService,
    ) {}

    public function __invoke(AppointmentsReportRequest $request): JsonResponse
    {
        try {
            $data = $this->reportService->generate(
                startDate: $request->startDate(),
                endDate: $request->endDate(),
                timeInterval: $request->timeInterval(),
                centreIds: $request->centreIds(),
                createdBy: $request->createdBy(),
            );

            return $this->successResponse('Appointments report generated successfully', [
                'rows' => AppointmentsReportResource::collection($data),
                'meta' => [
                    'total' => $data->count(),
                    'start_date' => $request->startDate(),
                    'end_date' => $request->endDate(),
                    'time_interval' => $request->timeInterval(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'AppointmentsReport');
        }
    }
}
