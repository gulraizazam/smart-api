<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ArrivedNotConvertedRequest;
use App\Http\Resources\Reports\ArrivedNotConvertedResource;
use App\Services\Reports\ArrivedNotConvertedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ArrivedNotConvertedController extends Controller
{
    public function __construct(
        private readonly ArrivedNotConvertedService $reportService,
    ) {}

    public function __invoke(ArrivedNotConvertedRequest $request): JsonResponse
    {
        if (! Gate::allows('non_converted_customers_manage')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        try {
            $data = $this->reportService->generate(
                startDate: $request->startDate(),
                endDate: $request->endDate(),
                locationId: $request->locationId(),
                doctorId: $request->doctorId(),
                serviceId: $request->serviceId(),
            );

            return $this->successResponse('Arrived not converted report generated successfully', [
                'rows' => ArrivedNotConvertedResource::collection($data),
                'meta' => [
                    'total' => $data->count(),
                    'start_date' => $request->startDate(),
                    'end_date' => $request->endDate(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'ArrivedNotConverted');
        }
    }
}
