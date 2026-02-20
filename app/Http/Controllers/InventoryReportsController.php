<?php

namespace App\Http\Controllers;

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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryReportsController extends Controller
{
    public function inventoryReport()
    {

        $Users = User::getAllRecords(Auth::User()->account_id)->whereNotIn('user_type_id', 5)->where('active', 1)->getDictionary();
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::User()->account_id);
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::User()->account_id);
        $brands = Brand::where('status',1)->get();

        return view('admin.reports.inventory_report', get_defined_vars());

    }
    public function loadInventoryReport(Request $request)
    {

        $validated = $request->validate([
            'centre_id' => 'nullable|integer|exists:locations,id', // Assuming locations table exists

        ]);

        $locationId = $validated['centre_id'] ?? null;
        $brandId = $request->brand_id;
        $dates = explode(' - ', $request->input('date_range'));
        $startDate = date('Y-m-d 00:00:00', strtotime($dates[0]));
        $endDate = date('Y-m-d 23:59:59', strtotime($dates[1]));

        $doctorId = $request->input('doctor_id');
        if ($request->report_type == "stock_report") {
            // Get location IDs for filtering
            $locationIds = $locationId ? [$locationId] : ACL::getUserCentres();
            
            // Load products with their inventories at specified locations
            $products = Product::with([
                'inventories' => function ($query) use ($locationIds) {
                    $query->whereIn('location_id', $locationIds);
                }
            ])
            ->whereHas('inventories', function ($query) use ($locationIds, $brandId) {
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

            return view('admin.reports.inventoryReport', compact('report'));
        }
        if ($request->report_type == "doctor_sales_report") {
            $locationId = $validated['centre_id'] ? [$validated['centre_id']] : ACL::getUserCentres();
            $dates = explode(' - ', $request->input('date_range'));
            $startDate = date('Y-m-d 00:00:00', strtotime($dates[0]));
            $endDate = date('Y-m-d 23:59:59', strtotime($dates[1]));

            // If a specific doctorId is provided, use it; otherwise, fetch all doctors for the location
            if ($doctorId) {
                $doctorIds = [$doctorId];
            } else {
                // $doctorIds = DB::table('doctor_has_locations')
                //     ->whereIn('location_id', $locationId)
                //     ->pluck('user_id');

                    

                    // Fetch active doctors as an associative array
                   

                    // Ensure 'from_id' is an array
                    $locationIds = is_array($locationId) ? $locationId : [$locationId];
                    if(is_array($locationId)){
                        $doctors = DoctorHasLocations::whereIn('location_id',$locationId)->pluck('user_id')->toArray();
                    }else{
                        $doctors = DoctorHasLocations::where('location_id',$locationId)->pluck('user_id')->toArray();
                    }
                     $users = User::whereIn('id', $doctors)
                        ->where('active', 1)
                        ->pluck( 'id') // Preserve user IDs
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
                ->where('location_id', $locationId)
                ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('orders.created_at', [$startDate, $endDate]);
                });

            $orders = $ordersQuery->get();

            // Process the orders to build the report
            $report = $orders->groupBy('prescribed_by')->map(function ($doctorOrders) {
                $doctorName = $doctorOrders->first()->doctor->name ?? 'Unknown Doctor';

                // Process each order detail to calculate sales data
                $productSales = $doctorOrders->flatMap(function ($order) {
                    return $order->orderDetail->map(function ($detail) use ($order) {
                        return [
                            'product_id' => $detail->product_id,
                            'product_name' => $detail->product->name ?? 'Unknown Product',
                            'total_quantity' => $detail->quantity,
                            'subtotal' => $detail->quantity * ($detail->product->sale_price ?? 0),
                            'order_date' => $order->created_at->format('d M Y'), // Adding order date
                        ];
                    });
                })->groupBy('product_id')->map(function ($orderDetails) {
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

            return view('admin.reports.doctor_wise_sales', get_defined_vars());
        }

        if($request->report_type=="sales_report"){
            $dates = explode(' - ', $request->input('date_range'));
            $startDate = date('Y-m-d 00:00:00', strtotime($dates[0]));
            $endDate = date('Y-m-d 23:59:59', strtotime($dates[1]));
            // Get filters
            $locationId =$request->input('centre_id') ? [$request->input('centre_id')] : ACL::getUserCentres();


            // Build query
            $query = Order::query()
                ->with(['orderDetail.product', 'centre','patients']) // Include related models
                ->when($locationId, function ($q) use ($locationId) {
                    $q->whereIn('orders.location_id', $locationId);
                })
                ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('orders.created_at', [$startDate, $endDate]);
                });

            // Fetch data
            $orders = $query->get();

            // Aggregate data
            $reportData = $orders->map(function ($order) {
                $totalRevenue = $order->orderDetail->sum(function ($detail) {

                    return $detail->quantity * $detail->sale_price;
                });
                $productNames = $order->orderDetail->map(function ($detail) {
                    return $detail->product->name ?? 'N/A';
                })->unique()->join(', '); // Join multiple product names if needed
                $quantity = $order->orderDetail->map(function ($detail) {
                    return $detail->quantity ?? 'N/A';
                })->join(', '); // No unique() to avoid filtering out duplicate quantities
                return [
                    'order_id' => $order->id,
                    'location_name' => $order->centre->name ?? 'N/A',
                    'order_date' => $order->created_at,
                    'total_revenue' => $totalRevenue,
                    'purchased_by'=>$order->patients->name??'N/A',
                    'patient_id'=>$order->patient_id,
                    'product_name'=>$productNames??'N/A',
                    'quantity'=>$quantity,
                    'payment_mode'=>$order->payment_mode
                ];
            });
            $cashTotal = $reportData->where('payment_mode', 1)->sum('total_revenue');
            $cardTotal = $reportData->where('payment_mode', 2)->sum('total_revenue');
            $bankTransferTotal = $reportData->where('payment_mode', 3)->sum('total_revenue');
            // Calculate overall totals
            $overallTotal = $reportData->sum('total_revenue');

            return view('admin.reports.inventory_sales',get_defined_vars());
        }
        if ($request->report_type == "addition_report") {
            $dates = explode(' - ', $request->input('date_range'));
            $startDate = date('Y-m-d 00:00:00', strtotime($dates[0]));
            $endDate = date('Y-m-d 23:59:59', strtotime($dates[1]));

            // Get filters
            $locationId = $request->input('centre_id');
            $brandId = $request->input('brand_id'); // Corrected request method

            $query = Stock::select(
                'products.name as product_name',
                'locations.name as location_name',
                'stocks.quantity',
                'stocks.created_at'
            )
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->join('locations', 'stocks.location_id', '=', 'locations.id')
            ->where('stocks.stock_type', 'in');

            // Apply location filter if provided
            if (!is_null($locationId) && $locationId !== '') {
                $query->where('stocks.location_id', $locationId);
            }

            // Apply brand filter if provided
            if (!is_null($brandId) && $brandId !== '') {
                $query->where('products.brand_id', $brandId);
            }

            // Apply date range filter
            if (!empty($startDate) && !empty($endDate)) {
                $query->whereBetween('stocks.created_at', [$startDate, $endDate]);
            }

            $stocks = $query->get();

            return view('admin.reports.addition_report', get_defined_vars());
        }


    }
    public function getSalesReport(Request $request)
    {
        // Validate filters
        $request->validate([
            'location_id' => 'nullable|exists:locations,id',

        ]);
        $dates = explode(' - ', $request->input('date_range'));
        $startDate = date('Y-m-d 00:00:00', strtotime($dates[0]));
        $endDate = date('Y-m-d 23:59:59', strtotime($dates[1]));
        // Get filters
        $locationId = $request->input('location_id');


        // Build query
        $query = Order::query()
            ->with(['orderDetails.product', 'location']) // Include related models
            ->when($locationId, function ($q) use ($locationId) {
                $q->where('location_id', $locationId);
            })
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('order_date', [$startDate, $endDate]);
            });

        // Fetch data
        $orders = $query->get();

        // Aggregate data
        $reportData = $orders->map(function ($order) {
            $totalRevenue = $order->orderDetails->sum(function ($detail) {
                return $detail->quantity * $detail->price;
            });

            return [
                'order_id' => $order->id,
                'location_name' => $order->location->name ?? 'N/A',
                'order_date' => $order->order_date,
                'total_revenue' => $totalRevenue,
            ];
        });

        // Calculate overall totals
        $overallTotal = $reportData->sum('total_revenue');

        return view('admin.reports.inventory_sales',get_defined_vars());
    }
}
