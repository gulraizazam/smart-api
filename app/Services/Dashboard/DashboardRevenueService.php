<?php

namespace App\Services\Dashboard;

use App\Enums\DashboardPeriod;
use App\Helpers\DashboardHelper;
use App\Models\Invoices;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Services;
use App\Reports\dashboardreport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardRevenueService
{
    private const EMPTY_PERIODS = [
        'today' => [], 'yesterday' => [], 'last7days' => [],
        'week' => [], 'thismonth' => [], 'lastmonth' => [],
    ];

    private const CHART_HEADER = [['Task', 'Hours per Day']];

    private const VALID_PAYMENT_MODES = ['Cash', 'Card', 'Bank/Wire Transfer'];

    public function getCollectionStats(
        ?array $userCentres = null,
        ?string $period = 'today',
        ?Request $request = null,
    ): array {
        if (!Gate::allows('dashboard_states')) {
            return ['collection' => null, 'todaycollection' => []];
        }

        $userCentres ??= DashboardHelper::getUserCentres();
        $reportPeriod = DashboardPeriod::fromRequest($period)->toReportPeriod();

        [$total] = dashboardreport::collectionbycenter(
            $userCentres,
            Auth::user()?->account_id,
            $reportPeriod,
            $request,
        );

        return ['collection' => 0, 'todaycollection' => [$total]];
    }

    public function getSalesByCentre(
        ?array $userCentres = null,
        ?string $startDate = null,
        ?string $endDate = null,
    ): array {
        if (!Gate::allows('dashboard_states')) {
            return ['revenue' => null];
        }

        $userCentres ??= DashboardHelper::getUserCentres();
        $invoiceStatusId = DashboardHelper::getPaidInvoiceStatusId();

        if (!$startDate || !$endDate) {
            [$startDate, $endDate] = DashboardPeriod::Today->dateRange();
        }

        $revenue = Invoices::query()
            ->whereIn('location_id', $userCentres)
            ->where('invoice_status_id', $invoiceStatusId)
            ->whereBetween('created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->sum('total_price');

        return ['revenue' => $revenue ?? 0];
    }

    public function getCollectionByCentre(string $type = '', ?Request $request = null): array
    {
        $data = self::EMPTY_PERIODS;

        if (!Gate::allows('dashboard_collection_by_centre')) {
            return ['data' => $data, 'total' => 0];
        }

        $period = DashboardPeriod::fromRequest($type);
        $dataKey = $period->value;
        $locationInfo = DashboardHelper::getUserCentres();

        [$reportData, $total] = dashboardreport::collectionByRevenueWidgets(
            $locationInfo,
            Auth::user()?->account_id,
            $period->toReportPeriod(),
            $request,
        );

        $data[$dataKey] = $reportData;

        return ['data' => $data, 'total' => $total];
    }

    public function getMyCollectionByCentre(string $type = '', ?Request $request = null): array
    {
        $data = self::EMPTY_PERIODS;

        if (!Gate::allows('dashboard_my_collection_by_centre')) {
            return ['data' => $data, 'total' => 0];
        }

        $period = DashboardPeriod::fromRequest($type);
        $dataKey = $period->value;
        $locationInfo = Locations::getActiveSorted(DashboardHelper::getUserCentres());

        [$reportData, $total] = dashboardreport::MyCollectionByRevenueWidgets(
            $locationInfo,
            Auth::user()?->account_id,
            $period->toReportPeriod(),
            $request,
        );

        $data[$dataKey] = $reportData;

        return ['data' => $data, 'total' => $total];
    }

    public function getRevenueByCentre(string $type = '', ?Request $request = null): array
    {
        return $this->buildRevenueByLocation(
            permission: 'dashboard_revenue_by_centre',
            type: $type,
            request: $request,
            locationResolver: fn (array $ids) => Locations::with('city')->whereIn('id', $ids)->get()->keyBy('id'),
        );
    }

    public function getMyRevenueByCentre(string $type = '', ?Request $request = null): array
    {
        return $this->buildRevenueByLocation(
            permission: 'dashboard_my_revenue_by_centre',
            type: $type,
            request: $request,
            locationResolver: fn (array $ids) => Locations::getActiveSortedLocations($ids),
        );
    }

    public function getRevenueByServiceCategory(string $type = '', ?Request $request = null): array
    {
        $period = DashboardPeriod::fromRequest($type);
        $dataKey = $period->value;
        $emptyResult = ['data' => [], 'total' => 0, 'colors' => []];

        if (!Gate::allows('dashboard_revenue_by_service')) {
            return $emptyResult;
        }

        $invoiceStatusId = DashboardHelper::getPaidInvoiceStatusId();
        $userCentres = DashboardHelper::getUserCentres();
        [$startDate, $endDate] = $period->dateRange();

        $query = Invoices::query()
            ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->whereBetween('invoices.created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->where('invoices.invoice_status_id', $invoiceStatusId)
            ->whereIn('invoices.location_id', $userCentres);

        if ($request?->get('performance')) {
            $query->where('invoices.created_by', Auth::id());
        }

        $records = $query
            ->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
            ->groupBy('invoice_details.service_id')
            ->get();

        if ($records->isEmpty()) {
            return ['data' => [$dataKey => self::CHART_HEADER], 'total' => 0, 'colors' => []];
        }

        $services = Services::with('parent')
            ->whereIn('id', $records->pluck('service_id')->filter())
            ->get()
            ->keyBy('id');

        $grouped = [];
        $total = 0;
        $colors = [];

        foreach ($records as $record) {
            $service = $services->get($record->service_id);
            if (!$service) continue;

            $parent = $service->parent ?? $service;
            $grouped[$parent->id] ??= ['name' => $parent->name, 'total' => 0, 'color' => $parent->color];
            $grouped[$parent->id]['total'] += $record->total_price;
            $total += $record->total_price;
        }

        $chartData = self::CHART_HEADER;
        foreach ($grouped as $item) {
            $chartData[] = [$item['name'], (int) $item['total']];
            $colors[] = $item['color'];
        }

        return ['data' => [$dataKey => $chartData], 'total' => $total, 'colors' => $colors];
    }

    public function getRevenueByService(
        string $type = '',
        ?Request $request = null,
        string $permission = 'dashboard_revenue_by_service',
    ): array {
        $period = DashboardPeriod::fromRequest($type);
        $dataKey = $period->value;
        $emptyResult = ['data' => [], 'total' => 0, 'colors' => []];

        if (!Gate::allows($permission)) {
            return $emptyResult;
        }

        $invoiceStatusId = DashboardHelper::getPaidInvoiceStatusId();
        $userCentres = DashboardHelper::getUserCentres();
        [$startDate, $endDate] = $period->dateRange();

        $query = Invoices::query()
            ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->whereBetween('invoices.created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->where('invoices.invoice_status_id', $invoiceStatusId)
            ->whereIn('invoices.location_id', $userCentres);

        if ($request?->get('performance')) {
            $query->where('invoices.created_by', Auth::id());
        }

        $records = $query
            ->select('invoice_details.service_id', DB::raw('SUM(invoices.total_price) AS total_price'))
            ->groupBy('invoice_details.service_id')
            ->get();

        if ($records->isEmpty()) {
            return ['data' => [$dataKey => self::CHART_HEADER], 'total' => 0, 'colors' => []];
        }

        $services = Services::whereIn('id', $records->pluck('service_id')->filter())
            ->get()
            ->keyBy('id');

        $chartData = self::CHART_HEADER;
        $total = 0;
        $colors = [];

        foreach ($records as $record) {
            $service = $services->get($record->service_id);
            if (!$service) continue;

            $chartData[] = [$service->name, (int) $record->total_price];
            $colors[] = $service->color;
            $total += $record->total_price;
        }

        return ['data' => [$dataKey => $chartData], 'total' => $total, 'colors' => $colors];
    }

    public function getCollectionByServiceCategory(string $type = '', ?Request $request = null): array
    {
        $period = DashboardPeriod::fromRequest($type);
        $dataKey = $period->value;
        $emptyResult = ['data' => [], 'total' => 0, 'colors' => []];

        $accountId = Auth::user()?->account_id;

        $parentServices = Services::where([
            'account_id' => $accountId,
            'active'     => '1',
            'parent_id'  => '0',
        ])->get();

        if ($parentServices->isEmpty()) {
            return $emptyResult;
        }

        [$startDate, $endDate] = $period->dateRange();

        // Collect all child IDs grouped by parent
        $parentChildMap = [];
        $allChildIds = [];
        foreach ($parentServices as $service) {
            $childIds = Services::where('parent_id', $service->id)->pluck('id')->toArray();
            $parentChildMap[$service->id] = $childIds;
            $allChildIds = array_merge($allChildIds, $childIds);
        }

        if (empty($allChildIds)) {
            return $emptyResult;
        }

        $advancesByService = PackageAdvances::query()
            ->join('appointments', 'appointments.id', '=', 'package_advances.appointment_id')
            ->with('paymentmode')
            ->whereBetween('package_advances.created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->where('package_advances.account_id', $accountId)
            ->whereIn('appointments.service_id', $allChildIds)
            ->where('package_advances.cash_flow', 'in')
            ->where('package_advances.is_adjustment', '0')
            ->where('package_advances.is_tax', '0')
            ->where('package_advances.is_cancel', '0')
            ->where('package_advances.cash_amount', '!=', 0)
            ->select('package_advances.*', 'appointments.service_id')
            ->get()
            ->groupBy('service_id');

        $chartData = self::CHART_HEADER;
        $total = 0;
        $colors = [];

        foreach ($parentServices as $service) {
            $serviceTotal = 0;

            foreach ($parentChildMap[$service->id] ?? [] as $childId) {
                foreach ($advancesByService->get($childId, collect()) as $advance) {
                    if (in_array($advance->paymentmode?->name, self::VALID_PAYMENT_MODES, true)) {
                        $serviceTotal += $advance->cash_amount;
                    }
                }
            }

            if ($serviceTotal > 0) {
                $chartData[] = [$service->name, (int) $serviceTotal];
                $colors[] = $service->color;
                $total += $serviceTotal;
            }
        }

        $data = count($chartData) > 1 ? [$dataKey => $chartData] : [];

        return ['data' => $data, 'total' => $total, 'colors' => $colors];
    }

    /**
     * Shared logic for getRevenueByCentre / getMyRevenueByCentre.
     */
    private function buildRevenueByLocation(
        string $permission,
        string $type,
        ?Request $request,
        \Closure $locationResolver,
    ): array {
        $empty = ['data' => self::CHART_HEADER, 'total' => 0];

        if (!Gate::allows($permission)) {
            return $empty;
        }

        $locationIds = DashboardHelper::getUserCentres();
        if (empty($locationIds)) {
            return $empty;
        }

        $locations = $locationResolver($locationIds);
        $invoiceStatusId = DashboardHelper::getPaidInvoiceStatusId();
        [$startDate, $endDate] = DashboardHelper::getDateRange($type ?: 'today');

        $query = Invoices::query()
            ->whereBetween('created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->whereIn('location_id', $locationIds)
            ->where('invoice_status_id', $invoiceStatusId);

        if ($request?->get('performance')) {
            $query->where('created_by', Auth::id());
        }

        $records = $query
            ->select('location_id', DB::raw('SUM(total_price) AS total_price'))
            ->groupBy('location_id')
            ->get()
            ->keyBy('location_id');

        $data = self::CHART_HEADER;
        $total = 0;

        foreach ($locations as $location) {
            $locationTotal = $records->get($location->id)?->total_price ?? 0;
            $total += $locationTotal;
            $data[] = [$location->name, (int) $locationTotal];
        }

        return ['data' => $data, 'total' => $total];
    }
}
