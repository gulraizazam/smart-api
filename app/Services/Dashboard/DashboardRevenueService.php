<?php

namespace App\Services\Dashboard;

use App\Helpers\DashboardHelper;
use App\Models\Invoices;
use App\Models\Locations;
use App\Models\Services;
use App\Reports\dashboardreport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Carbon;
use App\Models\PackageAdvances;

/**
 * Dashboard Revenue Service
 * 
 * Handles all dashboard revenue and collection related operations including:
 * - Collection by centre
 * - Revenue by centre
 * - Revenue by service
 * - Collection by service category
 */
class DashboardRevenueService
{
    /**
     * Get collection statistics for dashboard
     *
     * @param array|null $userCentres
     * @param string $period
     * @param object $request
     * @return array
     */
    public function getCollectionStats($userCentres = null, $period = 'today', $request = null)
    {
        $data = [
            'collection' => 0,
            'todaycollection' => [],
        ];
        
        if (!Gate::allows('dashboard_states')) {
            $data['collection'] = null;
            return $data;
        }

        $userCentres = $userCentres ?? DashboardHelper::getUserCentres();
        $mappedPeriod = DashboardHelper::mapPeriod($period);
        
        [$total] = dashboardreport::collectionbycenter($userCentres, Auth::User()->account_id, $mappedPeriod, $request);
        
        $data['todaycollection'][] = $total;
        
        return $data;
    }

    /**
     * Get sales by centre statistics
     *
     * @param array|null $userCentres
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public function getSalesByCentre($userCentres = null, $start_date = null, $end_date = null)
    {
        if (!Gate::allows('dashboard_states')) {
            return ['revenue' => null];
        }

        $userCentres = $userCentres ?? DashboardHelper::getUserCentres();
        $invoiceStatusId = DashboardHelper::getPaidInvoiceStatusId();

        if (!$start_date || !$end_date) {
            [$start_date, $end_date] = DashboardHelper::getDateRange('today');
        }

        $revenue = Invoices::whereIn('location_id', $userCentres)
            ->where('invoice_status_id', $invoiceStatusId)
            ->whereBetween('created_at', [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])
            ->sum('total_price');

        return ['revenue' => $revenue ?? 0];
    }

    /**
     * Get collection by centre for different periods
     *
     * @param string $type Period type (today, yesterday, last7days, week, thismonth, lastmonth)
     * @param object|null $request
     * @return array
     */
    public function getCollectionByCentre($type = '', $request = null)
    {
        $data = [
            'today' => [],
            'yesterday' => [],
            'last7days' => [],
            'week' => [],
            'thismonth' => [],
            'lastmonth' => [],
        ];

        if (!Gate::allows('dashboard_collection_by_centre')) {
            return ['data' => $data, 'total' => 0];
        }

        $locationInfo = DashboardHelper::getUserCentres();
        
        // Map request type to report period parameter
        $periodMap = [
            '' => 'today',
            'today' => 'today',
            'yesterday' => 'yesterday',
            'last7days' => 'last7day',
            'week' => 'week',
            'thismonth' => 'thisMonth',
            'lastmonth' => 'lastMonth',
        ];

        $dataKey = $type ?: 'today';
        $period = $periodMap[$type] ?? 'today';
        
        [$report_data, $total] = dashboardreport::collectionByRevenueWidgets($locationInfo, Auth::User()->account_id, $period, $request);
        $data[$dataKey] = $report_data;

        return [
            'data' => $data,
            'total' => $total,
        ];
    }

    /**
     * Get my collection by centre for different periods
     *
     * @param string $type Period type
     * @param object|null $request
     * @return array
     */
    public function getMyCollectionByCentre($type = '', $request = null)
    {
        $data = [
            'today' => [],
            'yesterday' => [],
            'last7days' => [],
            'week' => [],
            'thismonth' => [],
            'lastmonth' => [],
        ];

        if (!Gate::allows('dashboard_my_collection_by_centre')) {
            return ['data' => $data, 'total' => 0];
        }

        $locationInfo = Locations::getActiveSorted(DashboardHelper::getUserCentres());

        // Map request type to report period parameter
        $periodMap = [
            '' => 'today',
            'today' => 'today',
            'yesterday' => 'yesterday',
            'last7days' => 'last7day',
            'week' => 'week',
            'thismonth' => 'thisMonth',
            'lastmonth' => 'lastMonth',
        ];

        $dataKey = $type ?: 'today';
        $period = $periodMap[$type] ?? 'today';
        
        [$report_data, $total] = dashboardreport::MyCollectionByRevenueWidgets($locationInfo, Auth::User()->account_id, $period, $request);
        $data[$dataKey] = $report_data;

        return [
            'data' => $data,
            'total' => $total,
        ];
    }

    /**
     * Get revenue by centre
     *
     * @param string $type Period type
     * @param object|null $request
     * @return array
     */
    public function getRevenueByCentre($type = '', $request = null)
    {
        $data = [['Task', 'Hours per Day']];
        $total = 0;

        if (!Gate::allows('dashboard_revenue_by_centre')) {
            return ['data' => $data, 'total' => $total];
        }

        $locationIds = DashboardHelper::getUserCentres();
        
        if (empty($locationIds)) {
            return ['data' => $data, 'total' => $total];
        }

        $locations = Locations::with('city')->whereIn('id', $locationIds)->get()->keyBy('id');
        $invoiceStatusId = DashboardHelper::getPaidInvoiceStatusId();
        [$start_date, $end_date] = DashboardHelper::getDateRange($type ?: 'today');

        $query = Invoices::where('created_at', '>=', $start_date . ' 00:00:00')
            ->where('created_at', '<=', $end_date . ' 23:59:59')
            ->whereIn('location_id', $locationIds)
            ->where('invoice_status_id', $invoiceStatusId);

        if ($request && $request->get('performance')) {
            $query->where('created_by', Auth::User()->id);
        }

        $records = $query->select('location_id', DB::raw('SUM(total_price) AS total_price'))
            ->groupBy('location_id')
            ->get()
            ->keyBy('location_id');

        foreach ($locations as $location) {
            $record = $records->get($location->id);
            $locationTotal = $record ? $record->total_price : 0;
            $total += $locationTotal;
            $data[] = [$location->name, (int)$locationTotal];
        }

        return ['data' => $data, 'total' => $total];
    }

    /**
     * Get my revenue by centre
     *
     * @param string $type Period type
     * @param object|null $request
     * @return array
     */
    public function getMyRevenueByCentre($type = '', $request = null)
    {
        $data = [['Task', 'Hours per Day']];
        $total = 0;

        if (!Gate::allows('dashboard_my_revenue_by_centre')) {
            return ['data' => $data, 'total' => $total];
        }

        $locationIds = DashboardHelper::getUserCentres();
        
        if (empty($locationIds)) {
            return ['data' => $data, 'total' => $total];
        }

        $locations = Locations::getActiveSortedLocations($locationIds);
        $invoiceStatusId = DashboardHelper::getPaidInvoiceStatusId();
        [$start_date, $end_date] = DashboardHelper::getDateRange($type ?: 'today');

        $query = Invoices::where('created_at', '>=', $start_date . ' 00:00:00')
            ->where('created_at', '<=', $end_date . ' 23:59:59')
            ->whereIn('location_id', $locationIds)
            ->where('invoice_status_id', $invoiceStatusId);

        if ($request && $request->get('performance')) {
            $query->where('created_by', Auth::User()->id);
        }

        $records = $query->select('location_id', DB::raw('SUM(total_price) AS total_price'))
            ->groupBy('location_id')
            ->get()
            ->keyBy('location_id');

        foreach ($locations as $location) {
            $record = $records->get($location->id);
            $locationTotal = $record ? $record->total_price : 0;
            $total += $locationTotal;
            $data[] = [$location->name, (int)$locationTotal];
        }

        return ['data' => $data, 'total' => $total];
    }

    /**
     * Get revenue by service category
     *
     * @param string $type Period type
     * @param object|null $request
     * @return array
     */
    public function getRevenueByServiceCategory($type = '', $request = null)
    {
        $dataKey = $type ?: 'today';
        $data = [];
        $total = 0;
        $chartData = [];
        $colors = [];

        if (!Gate::allows('dashboard_revenue_by_service')) {
            return ['data' => $data, 'total' => $total, 'colors' => $colors];
        }

        $invoiceStatusId = DashboardHelper::getPaidInvoiceStatusId();
        $userCentres = DashboardHelper::getUserCentres();
        [$start_date, $end_date] = DashboardHelper::getDateRange($dataKey);

        $query = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->whereBetween('invoices.created_at', [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])
            ->where('invoices.invoice_status_id', $invoiceStatusId)
            ->whereIn('invoices.location_id', $userCentres);

        if ($request && $request->get('performance')) {
            $query->where('invoices.created_by', Auth::User()->id);
        }

        $records = $query->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
            ->groupBy('invoice_details.service_id')
            ->get();

        if ($records->isEmpty()) {
            $data[$dataKey] = [['Task', 'Hours per Day']];
            return ['data' => $data, 'total' => 0, 'colors' => $colors];
        }

        // Fetch all services with parent in ONE query
        $serviceIds = $records->pluck('service_id')->filter()->toArray();
        $services = Services::with('parent')->whereIn('id', $serviceIds)->get()->keyBy('id');

        // Group by parent service category
        $prepareData = [];
        foreach ($records as $record) {
            $service = $services->get($record->service_id);
            if (!$service) continue;
            
            $service_id = $service->parent ? $service->parent->id : $service->id;
            $service_name = $service->parent ? $service->parent->name : $service->name;
            $service_color = $service->parent ? $service->parent->color : $service->color;

            if (isset($prepareData[$service_id])) {
                $prepareData[$service_id]['total'] += $record->total_price;
            } else {
                $prepareData[$service_id] = [
                    'id' => $service_id,
                    'name' => $service_name,
                    'total' => $record->total_price,
                    'color' => $service_color,
                ];
            }
            $total += $record->total_price;
        }

        // Build chart data array
        $chartData = [['Task', 'Hours per Day']];
        foreach ($prepareData as $item) {
            $chartData[] = [$item['name'], (int)$item['total']];
            $colors[] = $item['color'];
        }

        $data[$dataKey] = $chartData;

        return ['data' => $data, 'total' => $total, 'colors' => $colors];
    }

    /**
     * Get revenue by service (individual services, not grouped by category)
     *
     * @param string $type Period type
     * @param object|null $request
     * @param string $permission Permission to check
     * @return array
     */
    public function getRevenueByService($type = '', $request = null, $permission = 'dashboard_revenue_by_service')
    {
        $dataKey = $type ?: 'today';
        $data = [];
        $total = 0;
        $chartData = [];
        $colors = [];

        if (!Gate::allows($permission)) {
            return ['data' => $data, 'total' => $total, 'colors' => $colors];
        }

        $invoiceStatusId = DashboardHelper::getPaidInvoiceStatusId();
        $userCentres = DashboardHelper::getUserCentres();
        [$start_date, $end_date] = DashboardHelper::getDateRange($dataKey);

        $query = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->whereBetween('invoices.created_at', [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])
            ->where('invoices.invoice_status_id', $invoiceStatusId)
            ->whereIn('invoices.location_id', $userCentres);

        if ($request && $request->get('performance')) {
            $query->where('invoices.created_by', Auth::User()->id);
        }

        $records = $query->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
            ->groupBy('invoice_details.service_id')
            ->get();

        if ($records->isEmpty()) {
            $data[$dataKey] = [['Task', 'Hours per Day']];
            return ['data' => $data, 'total' => 0, 'colors' => $colors];
        }

        // Fetch all services in ONE query
        $serviceIds = $records->pluck('service_id')->filter()->toArray();
        $services = Services::whereIn('id', $serviceIds)->get()->keyBy('id');

        // Build chart data
        $chartData = [['Task', 'Hours per Day']];
        foreach ($records as $record) {
            $service = $services->get($record->service_id);
            if (!$service) continue;
            
            $chartData[] = [$service->name, (int)$record->total_price];
            $colors[] = $service->color;
            $total += $record->total_price;
        }

        $data[$dataKey] = $chartData;

        return ['data' => $data, 'total' => $total, 'colors' => $colors];
    }

    /**
     * Get collection by service category (grouped by parent service)
     * Optimized version - single method handles all time periods
     *
     * @param string $type Period type (today, yesterday, last7days, thismonth, lastmonth)
     * @param object|null $request
     * @return array
     */
    public function getCollectionByServiceCategory($type = '', $request = null)
    {
        $dataKey = $type ?: 'today';
        $data = [];
        $total = 0;
        $chartData = [];
        $colors = [];

        // Get parent services
        $services = Services::where([
            'account_id' => Auth::User()->account_id,
            'active' => '1',
            'parent_id' => '0',
        ])->get();

        if ($services->isEmpty()) {
            return ['data' => $data, 'total' => 0, 'colors' => $colors];
        }

        // Get date range
        [$start_date, $end_date] = DashboardHelper::getDateRange($dataKey);

        // Get all child service IDs grouped by parent
        $parentChildMap = [];
        $allChildIds = [];
        foreach ($services as $service) {
            $childIds = Services::where('parent_id', $service->id)->pluck('id')->toArray();
            $parentChildMap[$service->id] = $childIds;
            $allChildIds = array_merge($allChildIds, $childIds);
        }

        if (empty($allChildIds)) {
            return ['data' => $data, 'total' => 0, 'colors' => $colors];
        }

        // Fetch all package advances in ONE query with eager loading
        $packagesAdvances = PackageAdvances::join('appointments', 'appointments.id', '=', 'package_advances.appointment_id')
            ->with('paymentmode')
            ->whereBetween('package_advances.created_at', [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])
            ->where('package_advances.account_id', Auth::User()->account_id)
            ->whereIn('appointments.service_id', $allChildIds)
            ->where('package_advances.cash_flow', 'in')
            ->where('package_advances.is_adjustment', '0')
            ->where('package_advances.is_tax', '0')
            ->where('package_advances.is_cancel', '0')
            ->where('package_advances.cash_amount', '!=', 0)
            ->select('package_advances.*', 'appointments.service_id')
            ->get();

        // Group advances by service_id
        $advancesByService = $packagesAdvances->groupBy('service_id');

        // Calculate totals per parent service
        $chartData[0] = ['Task', 'Hours per Day'];
        
        foreach ($services as $service) {
            $serviceTotal = 0;
            $childIds = $parentChildMap[$service->id] ?? [];
            
            foreach ($childIds as $childId) {
                $advances = $advancesByService->get($childId, collect());
                
                foreach ($advances as $advance) {
                    $paymentMode = $advance->paymentmode->name ?? '';
                    
                    if (in_array($paymentMode, ['Cash', 'Card', 'Bank/Wire Transfer'])) {
                        $serviceTotal += $advance->cash_amount;
                    }
                }
            }
            
            if ($serviceTotal > 0) {
                $chartData[] = [$service->name, (int)$serviceTotal];
                $colors[] = $service->color;
                $total += $serviceTotal;
            }
        }

        if (count($chartData) > 1) {
            $data[$dataKey] = $chartData;
        }

        return ['data' => $data, 'total' => $total, 'colors' => $colors];
    }
}
