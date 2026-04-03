<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\CsrDashboardService;
use Illuminate\Contracts\View\View;

class CsrDashboardController extends Controller
{
    public function __construct(
        private readonly CsrDashboardService $dashboardService,
    ) {}

    public function __invoke(): View
    {
        $data = $this->dashboardService->generate();

        return view('admin.reports.csr_dashboard', $data);
    }
}
