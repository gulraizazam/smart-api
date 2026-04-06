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

    public function inventoryReport()
    {
        $data = $this->service->getInventoryReportPageData();
        $Users = $data['Users'];
        $locations = $data['locations'];
        $brands = $data['brands'];

        return view('admin.reports.inventory_report', get_defined_vars());

    }

    public function loadInventoryReport(Request $request)
    {

        $validated = $request->validate([
            'centre_id' => 'nullable|integer|exists:locations,id',
        ]);

        $params = $request->all();

        if ($request->report_type == "stock_report") {
            $result = $this->service->loadStockReport($params);
            $report = $result['report'];

            return view('admin.reports.inventoryReport', compact('report'));
        }
        if ($request->report_type == "doctor_sales_report") {
            $result = $this->service->loadDoctorSalesReport($params);
            $report = $result['report'];
            $overallTotal = $result['overallTotal'];

            return view('admin.reports.doctor_wise_sales', get_defined_vars());
        }

        if ($request->report_type == "sales_report") {
            $result = $this->service->loadSalesReport($params);
            $reportData = $result['reportData'];
            $cashTotal = $result['cashTotal'];
            $cardTotal = $result['cardTotal'];
            $bankTransferTotal = $result['bankTransferTotal'];
            $overallTotal = $result['overallTotal'];

            return view('admin.reports.inventory_sales', get_defined_vars());
        }
        if ($request->report_type == "addition_report") {
            $result = $this->service->loadAdditionReport($params);
            $stocks = $result['stocks'];

            return view('admin.reports.addition_report', get_defined_vars());
        }

    }

    public function getSalesReport(Request $request)
    {
        // Validate filters
        $request->validate([
            'location_id' => 'nullable|exists:locations,id',
        ]);

        $result = $this->service->getSalesReportData($request->all());
        $reportData = $result['reportData'];
        $overallTotal = $result['overallTotal'];

        return view('admin.reports.inventory_sales', get_defined_vars());
    }
}
