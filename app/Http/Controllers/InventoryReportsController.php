<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Inventory\InventoryReportService;
use Illuminate\Http\Request;

class InventoryReportsController extends Controller
{
    public function __construct(
        private readonly InventoryReportService $service,
    ) {}

    public function inventoryReport(): \Illuminate\View\View
    {
        $data = $this->service->getInventoryReportPageData();
        $Users = $data['Users'];
        $locations = $data['locations'];
        $brands = $data['brands'];

        return view('admin.reports.inventory_report', compact('Users', 'locations', 'brands'));

    }

    public function loadInventoryReport(Request $request): \Illuminate\View\View
    {

        $validated = $request->validate([
            'centre_id' => 'nullable|array',
            'centre_id.*' => 'integer|exists:locations,id',
        ]);

        $params = $request->all();
        if (isset($params['centre_id'])) {
            $params['centre_id'] = array_values(array_filter(
                array_map('intval', (array) $params['centre_id']),
                fn (int $id): bool => $id > 0,
            ));
        }

        if ($request->report_type == "stock_report") {
            $result = $this->service->loadStockReport($params);
            $report = $result['report'];

            return view('admin.reports.inventoryReport', compact('report'));
        }
        if ($request->report_type == "doctor_sales_report") {
            $result = $this->service->loadDoctorSalesReport($params);
            $report = $result['report'];
            $overallTotal = $result['overallTotal'];

            return view('admin.reports.doctor_wise_sales', compact('report', 'overallTotal'));
        }

        if ($request->report_type == "sales_report") {
            $result = $this->service->loadSalesReport($params);
            $reportData = $result['reportData'];
            $cashTotal = $result['cashTotal'];
            $cardTotal = $result['cardTotal'];
            $bankTransferTotal = $result['bankTransferTotal'];
            $overallTotal = $result['overallTotal'];

            return view('admin.reports.inventory_sales', compact('reportData', 'cashTotal', 'cardTotal', 'bankTransferTotal', 'overallTotal'));
        }
        if ($request->report_type == "addition_report") {
            $result = $this->service->loadAdditionReport($params);
            $stocks = $result['stocks'];

            return view('admin.reports.addition_report', compact('stocks'));
        }

    }

    public function getSalesReport(Request $request): \Illuminate\View\View
    {
        // Validate filters
        $request->validate([
            'location_id' => 'nullable|array',
            'location_id.*' => 'exists:locations,id',
        ]);

        $result = $this->service->getSalesReportData($request->all());
        $reportData = $result['reportData'];
        $overallTotal = $result['overallTotal'];

        return view('admin.reports.inventory_sales', compact('reportData', 'overallTotal'));
    }
}
