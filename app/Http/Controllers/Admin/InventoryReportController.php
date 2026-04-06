<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryReportController extends Controller
{
    public function __construct(
        private readonly InventoryReportService $inventoryReportService,
    ) {}

    public function report()
    {
        return view('admin.reports.inventory.index');
    }

    public function reportResult(Request $request)
    {
        try {
            $data = $this->inventoryReportService->getReportResultData(Auth::user()->account_id);

            return $this->successResponse('Record found', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'InventoryReportController');
        }
    }

    public function stockReport(Request $request)
    {
        try {
            if ($request->report_type == null) {
                return $this->errorResponse('Please select report type', 500);
            }
            if ($request->has('report_type')) {
                if ($request->report_type == 'stock_report') {
                    $data = $this->inventoryReportService->getStockReportResult($request->all());

                    return $this->successResponse('Record found', $data);
                }
            }
        } catch (\Exception $e) {
            return $this->handleException($e, 'InventoryReportController');
        }
    }
}
