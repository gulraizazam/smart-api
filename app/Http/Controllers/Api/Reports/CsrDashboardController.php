<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\CsrDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CsrDashboardController extends Controller
{
    public function __construct(
        private readonly CsrDashboardService $dashboardService,
    ) {}

    public function __invoke(): JsonResponse
    {
        if (! Gate::allows('csr_dashboard_report')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        try {
            $data = $this->dashboardService->generate();

            return $this->successResponse('CSR dashboard generated successfully', [
                'location_stats' => $data['locationStats'],
                'date_range' => $data['dateRange'],
                'total_appointments' => $data['totalAppointments'],
                'total_by_date' => $data['totalByDate'],
                'csr_stats' => $data['csrStats'],
                'today' => $data['today']->format('Y-m-d'),
                'csr_target' => $data['csrTarget'],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'CsrDashboard');
        }
    }
}
