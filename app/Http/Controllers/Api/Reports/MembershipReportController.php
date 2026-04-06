<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\MembershipReportRequest;
use App\Http\Resources\Reports\MembershipReportResource;
use App\Services\Reports\MembershipReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MembershipReportController extends Controller
{
    public function __construct(
        private readonly MembershipReportService $reportService,
    ) {}

    public function __invoke(MembershipReportRequest $request): JsonResponse
    {
        if (! Gate::allows('operations_reports_manage')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        try {
            $data = $this->reportService->generate(
                locationId: $request->locationId(),
                membershipTypeId: $request->membershipTypeId(),
                startDate: $request->startDate(),
                endDate: $request->endDate(),
            );

            return $this->successResponse('Membership report generated successfully', [
                'rows' => MembershipReportResource::collection($data),
                'meta' => [
                    'total' => $data->count(),
                    'start_date' => $request->startDate(),
                    'end_date' => $request->endDate(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'MembershipReport');
        }
    }
}
