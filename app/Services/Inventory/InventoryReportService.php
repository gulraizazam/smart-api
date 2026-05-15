<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Helpers\ACL;
use App\Models\Brand;
use App\Models\DoctorHasLocations;
use App\Models\Locations;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\RoleHasUsers;
use App\Models\Stock;
use App\Models\User;
use App\Models\UserHasLocations;
use App\Models\Warehouse;
use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryReportService
{
    use ParsesDateRange;

    /**
     * Normalise a centre/location filter input into a clean int[] (or empty array when no filter).
     *
     * @return int[]
     */
    private function resolveCentreIds(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === [] || $raw === '0') {
            return [];
        }

        return array_values(array_filter(
            array_map('intval', (array) $raw),
            fn (int $id): bool => $id > 0,
        ));
    }

    public function getReportResultData(int $accountId): array
    {
        $centres = Locations::getAllRecordsDictionary($accountId, 'custom', 'id', 'desc', ACL::getUserCentres());
        $warehouse = Warehouse::getAllRecordsDictionary($accountId);

        return [
            'centres' => $centres,
            'warehouse' => $warehouse,
        ];
    }

    public function getStockReportResult(array $requestData): array
    {
        $where = [];
        [$start_date_time, $end_date_time] = self::parseDateRangeForFilter($requestData['date_range'] ?? null);

        if (isset($requestData['name'])) {
            $where[] = ['name', 'like', '%'.$requestData['name'].'%'];
        }
        if (isset($requestData['location_type']) && isset($requestData['location'])) {
            if ($requestData['location_type'] == 'branch') {
                $where[][] = ['location_id' => $requestData['location']];
            } elseif ($requestData['location_type'] == 'warehouse') {
                $where[][] = ['warehouse_id' => $requestData['location']];
            }
        }
        if (isset($requestData['date_range'])) {
            $where[] = ['created_at', '>=', $start_date_time];
            $where[] = ['created_at', '<=', $end_date_time];
        }

        $accountId = Auth::user()->account_id;
        $products = Product::with('order')->where(function ($query) {
            $query->whereIn('location_id', ACL::getUserCentres())
                ->orWhereIn('warehouse_id', ACL::getUserWarehouse());
        })
            ->withSum('productDetail', 'quantity')
            ->withSum('productDetail', 'total_purchase_price')
            ->withSum('transferProduct', 'quantity')
            ->where($where)
            ->get();

        $centres = Locations::getAllRecordsDictionary($accountId, 'custom', 'id', 'desc', ACL::getUserCentres());
        $warehouse = Warehouse::getAllRecordsDictionary($accountId, ACL::getUserWarehouse());

        $products = collect($products)->map(function ($product) use ($centres, $warehouse) {
            $product->transfer_product_sum_quantity = $product->transfer_product_sum_quantity == null ? 0 : $product->transfer_product_sum_quantity;
            $product->available_stock = $product->getAvailableStockAttribute();
            $product->order_quantity = $product['order']->filter(fn ($order) => $order['order_type'] === 'sale' && $order['refund_order_id'] == null)
                ->sum(fn ($order) => $order['orderDetail']['quantity']);
            $product->order_sale_price = $product['order']->filter(fn ($order) => $order['order_type'] === 'sale' && $order['refund_order_id'] == null)
                ->sum(fn ($order) => $order['orderDetail']['sale_price']);
            $product->location = ($product->location_id != null) ? ((array_key_exists($product->location_id, $centres)) ? $centres[$product->location_id]->name : 'N/A') : ((array_key_exists($product->warehouse_id, $warehouse)) ? $warehouse[$product->warehouse_id]->name : 'N/A');

            return $product;
        });

        return ['products' => $products];
    }

    public function getInventoryReportPageData(): array
    {
        $Users = User::getAllRecords(Auth::user()->account_id)->whereNotIn('user_type_id', 5)->where('active', 1)->getDictionary();
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::user()->account_id);
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::user()->account_id);
        $brands = Brand::where('status', 1)->get();

        return [
            'Users' => $Users,
            'locations' => $locations,
            'brands' => $brands,
        ];
    }

    public function loadStockReport(array $params): array
    {
        $centreIds = $this->resolveCentreIds($params['centre_id'] ?? null);
        $brandId = $params['brand_id'] ?? null;
        [$startDate, $endDate] = $this->parseDateRangeWithTimeBounds($params['date_range']);

        // Get location IDs for filtering
        $locationIds = ! empty($centreIds) ? $centreIds : ACL::getUserCentres();

        // Load products with their inventories at specified locations
        $products = Product::with([
            'inventories' => function ($query) use ($locationIds) {
                $query->whereIn('location_id', $locationIds);
            },
        ])
            ->whereHas('inventories', function ($query) use ($locationIds) {
                $query->whereIn('location_id', $locationIds);
            })
            ->when($brandId, function ($query) use ($brandId) {
                $query->where('brand_id', $brandId);
            })
            ->get();

        // Process the product data for the report
        $report = $products->map(function ($product) use ($locationIds, $startDate, $endDate) {

            // Opening Stock = Closing Stock of previous period
            // Closing Stock = All additions before start date - All sales before start date

            // Total stock additions (IN) before start date
            $additionsBeforeStart = Stock::where('product_id', $product->id)
                ->where('stock_type', 'in')
                ->whereIn('location_id', $locationIds)
                ->where('created_at', '<', $startDate)
                ->sum('quantity');

            // Total sales before start date
            $salesBeforeStart = OrderDetail::where('product_id', $product->id)
                ->whereHas('order', function ($query) use ($locationIds, $startDate) {
                    $query->where('created_at', '<', $startDate)
                        ->whereIn('location_id', $locationIds);
                })
                ->sum('quantity');

            // Opening Stock = Additions before - Sales before
            $openingStock = $additionsBeforeStart - $salesBeforeStart;

            // Addition in range = stock IN records within date range
            $additionInRange = Stock::where('product_id', $product->id)
                ->where('stock_type', 'in')
                ->whereIn('location_id', $locationIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('quantity');

            // Sold in the current range
            $soldInRange = OrderDetail::where('product_id', $product->id)
                ->whereHas('order', function ($query) use ($locationIds, $startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate])
                        ->whereIn('location_id', $locationIds);
                })
                ->sum('quantity');

            // Total Stock = Opening + Additions in range
            $totalStock = $openingStock + $additionInRange;

            // Remaining (Closing) = Total Stock - Sold in range
            $remainingStock = $totalStock - $soldInRange;

            return [
                'product_name' => $product->name,
                'opening_stock' => $openingStock,
                'addition' => $additionInRange,
                'total_stock' => $totalStock,
                'sold_stock' => $soldInRange,
                'remaining_stock' => $remainingStock,
            ];
        });

        return ['report' => $report];
    }

    public function loadDoctorSalesReport(array $params): array
    {
        $centreIds = $this->resolveCentreIds($params['centre_id'] ?? null);
        $locationId = ! empty($centreIds) ? $centreIds : ACL::getUserCentres();
        [$startDate, $endDate] = $this->parseDateRangeWithTimeBounds($params['date_range']);
        $doctorId = $params['doctor_id'] ?? null;

        // If a specific doctorId is provided, use it; otherwise, fetch all doctors for the location
        if ($doctorId) {
            $doctorIds = [$doctorId];
        } else {
            // Ensure 'from_id' is an array
            $locationIds = is_array($locationId) ? $locationId : [$locationId];
            if (is_array($locationId)) {
                $doctors = DoctorHasLocations::whereIn('location_id', $locationId)->pluck('user_id')->toArray();
            } else {
                $doctors = DoctorHasLocations::where('location_id', $locationId)->pluck('user_id')->toArray();
            }
            $users = User::whereIn('id', $doctors)
                ->where('active', 1)
                ->pluck('id') // Preserve user IDs
                ->toArray();
            // Fetch FDM users by getting the user_ids associated with the center (location_id)
            $findFDM = UserHasLocations::whereIn('location_id', $locationIds)->pluck('user_id')->toArray();

            // Fetch the 'FDM' role and get its user ids
            $findRole = DB::table('roles')->where('name', 'FDM')->first();
            $roleId = $findRole->id;

            // Get users who have the FDM role
            $roleHasUser = RoleHasUsers::where('role_id', $roleId)->pluck('user_id')->toArray();

            // Get the intersection of users who are both FDM and belong to the center
            $fdmUsers = array_intersect($findFDM, $roleHasUser);

            // Fetch FDM user details (id and name) from the users table
            $FDMUsers = User::whereIn('id', $fdmUsers)
                ->pluck('id') // Preserve user IDs
                ->toArray();

            // Merge the arrays while preserving keys
            $doctorIds = $users + $FDMUsers;
        }

        // Fetch orders based on doctor IDs and the date range (if provided)
        $ordersQuery = Order::with(['doctor', 'orderDetail.product'])
            ->whereIn('prescribed_by', $doctorIds)
            ->whereIn('location_id', is_array($locationId) ? $locationId : [$locationId])
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('orders.created_at', [$startDate, $endDate]);
            });

        $orders = $ordersQuery->get();

        // Process the orders to build the report
        $report = $orders->groupBy('prescribed_by')->map(function ($doctorOrders) {
            $doctorName = $doctorOrders->first()->doctor->name ?? 'Unknown Doctor';

            // Process each order detail to calculate sales data
            $productSales = $doctorOrders->flatMap(fn ($order) => $order->orderDetail->map(fn ($detail) => [
                'product_id' => $detail->product_id,
                'product_name' => $detail->product->name ?? 'Unknown Product',
                'total_quantity' => $detail->quantity,
                'subtotal' => $detail->quantity * ($detail->sale_price ?? $detail->product->sale_price ?? 0),
                'order_date' => $order->created_at->format('d M Y'), // Adding order date
            ]))->groupBy('product_id')->map(function ($orderDetails) {
                $firstDetail = $orderDetails->first();

                return [
                    'product_name' => $firstDetail['product_name'],
                    'total_quantity' => $orderDetails->sum('total_quantity'),
                    'subtotal' => $orderDetails->sum('subtotal'),
                    'order_dates' => $orderDetails->pluck('order_date')->unique()->values(), // Collecting unique order dates
                ];
            });

            $grandTotal = $productSales->sum('subtotal');

            return [
                'doctor_name' => $doctorName,
                'product_sales' => $productSales,
                'grand_total' => $grandTotal,  // Add grand total for the doctor
            ];
        });

        $overallTotal = $report->sum('grand_total');

        return [
            'report' => $report,
            'overallTotal' => $overallTotal,
        ];
    }

    /**
     * Product sales report.
     *
     * Returns one row per order with both the gross (pre-discount) and
     * net (post-discount) totals so the UI can show the impact of the
     * auto-discount system (employee 20% / membership 10%) introduced
     * by the 2026-05 inventory revamp.
     *
     * Employee buyers don't have a row in `patients` — the report falls
     * back to the user record on `orders.employee_id` for the display
     * name, with `buyer_type` flagging which kind of buyer the row is.
     */
    public function loadSalesReport(array $params): array
    {
        [$startDate, $endDate] = $this->parseDateRangeWithTimeBounds($params['date_range']);
        $centreIds = $this->resolveCentreIds($params['centre_id'] ?? null);
        $locationId = ! empty($centreIds) ? $centreIds : ACL::getUserCentres();

        $query = Order::query()
            ->with(['orderDetail.product', 'centre', 'patients'])
            ->where('orders.order_type', 'sale')
            ->when($locationId, function ($q) use ($locationId) {
                $q->whereIn('orders.location_id', $locationId);
            })
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('orders.created_at', [$startDate, $endDate]);
            });

        $orders = $query->get();

        // Resolve employee names in a single batched query rather than
        // N+1 lookups per row.
        $employeeIds = $orders->pluck('employee_id')->filter()->unique()->all();
        $employees = ! empty($employeeIds)
            ? \App\Models\User::whereIn('id', $employeeIds)->pluck('name', 'id')
            : collect();

        $reportData = $orders->map(function ($order) use ($employees) {
            $lines = $order->orderDetail;

            $gross = $lines->sum(fn ($d) => (float) $d->sale_price * (int) $d->quantity);
            $discount = $lines->sum(fn ($d) => (float) ($d->discount_price ?? 0) * (int) $d->quantity);
            $net = $lines->sum(fn ($d) => (float) ($d->sale_price_after_discount ?? $d->sale_price) * (int) $d->quantity);

            $productNames = $lines->map(fn ($d) => $d->product->name ?? 'N/A')->unique()->join(', ');
            $quantityList = $lines->map(fn ($d) => $d->quantity ?? 0)->join(', ');

            $buyerType = $order->buyer_type ?? 'patient';
            $purchasedBy = $buyerType === 'employee'
                ? ($employees[$order->employee_id] ?? 'Employee')
                : ($order->patients->name ?? 'N/A');

            return [
                'order_id' => $order->id,
                'location_name' => $order->centre->name ?? 'N/A',
                'order_date' => $order->created_at,
                'buyer_type' => $buyerType,
                'purchased_by' => $purchasedBy,
                'patient_id' => $order->patient_id,
                'employee_id' => $order->employee_id,
                'product_name' => $productNames ?: 'N/A',
                'quantity' => $quantityList,
                'gross_revenue' => round($gross, 2),
                'discount_total' => round($discount, 2),
                'net_revenue' => round($net, 2),
                'auto_discount_type' => $order->auto_discount_type ?? 'none',
                'auto_discount_percent' => (float) ($order->auto_discount_percent ?? 0),
                'payment_mode' => $order->payment_mode,
                'is_refunded' => (int) ($order->is_refunded ?? 0),
                // Legacy alias kept so existing exports / blade views that
                // reference `total_revenue` continue to work.
                'total_revenue' => round($net, 2),
            ];
        });

        $cashTotal = $reportData->where('payment_mode', 1)->sum('net_revenue');
        $cardTotal = $reportData->where('payment_mode', 2)->sum('net_revenue');
        $bankTransferTotal = $reportData->where('payment_mode', 3)->sum('net_revenue');
        $overallTotal = $reportData->sum('net_revenue');
        $grossTotal = $reportData->sum('gross_revenue');
        $discountTotal = $reportData->sum('discount_total');

        return [
            'reportData' => $reportData,
            'cashTotal' => round($cashTotal, 2),
            'cardTotal' => round($cardTotal, 2),
            'bankTransferTotal' => round($bankTransferTotal, 2),
            'overallTotal' => round($overallTotal, 2),
            'grossTotal' => round($grossTotal, 2),
            'discountTotal' => round($discountTotal, 2),
        ];
    }

    public function loadAdditionReport(array $params): array
    {
        [$startDate, $endDate] = $this->parseDateRangeWithTimeBounds($params['date_range']);

        // Get filters
        $centreIds = $this->resolveCentreIds($params['centre_id'] ?? null);
        if (empty($centreIds)) {
            $centreIds = array_map('intval', ACL::getUserCentres());
        }
        $brandId = $params['brand_id'] ?? null;

        $query = Stock::select(
            'products.name as product_name',
            'locations.name as location_name',
            'stocks.quantity',
            'stocks.created_at'
        )
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->join('locations', 'stocks.location_id', '=', 'locations.id')
            ->where('stocks.stock_type', 'in');

        // Apply location filter (falls back to user's permitted centres when none selected)
        $query->whereIn('stocks.location_id', $centreIds);

        // Apply brand filter if provided
        if ($brandId !== null && $brandId !== '') {
            $query->where('products.brand_id', $brandId);
        }

        // Apply date range filter
        if (! empty($startDate) && ! empty($endDate)) {
            $query->whereBetween('stocks.created_at', [$startDate, $endDate]);
        }

        $stocks = $query->get();

        return ['stocks' => $stocks];
    }

    public function getSalesReportData(array $params): array
    {
        [$startDate, $endDate] = $this->parseDateRangeWithTimeBounds($params['date_range']);
        // Get filters (falls back to user's permitted centres when none selected)
        $locationIds = $this->resolveCentreIds($params['location_id'] ?? null);
        if (empty($locationIds)) {
            $locationIds = array_map('intval', ACL::getUserCentres());
        }

        // Build query
        $query = Order::query()
            ->with(['orderDetails.product', 'location']) // Include related models
            ->whereIn('location_id', $locationIds)
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('order_date', [$startDate, $endDate]);
            });

        // Fetch data
        $orders = $query->get();

        // Aggregate data
        $reportData = $orders->map(function ($order) {
            $totalRevenue = $order->orderDetails->sum(fn ($detail) => $detail->quantity * $detail->price);

            return [
                'order_id' => $order->id,
                'location_name' => $order->location->name ?? 'N/A',
                'order_date' => $order->order_date,
                'total_revenue' => $totalRevenue,
            ];
        });

        // Calculate overall totals
        $overallTotal = $reportData->sum('total_revenue');

        return [
            'reportData' => $reportData,
            'overallTotal' => $overallTotal,
        ];
    }
}
