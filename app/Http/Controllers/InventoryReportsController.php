<?php

namespace App\Http\Controllers;

use App\Helpers\ACL;
use App\Models\Locations;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\User;
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
        return view('admin.reports.inventory_report', get_defined_vars());
    
    }
    public function loadInventoryReport(Request $request)
    {
        $validated = $request->validate([
            'centre_id' => 'nullable|integer|exists:locations,id', // Assuming locations table exists
            
        ]);
        
        $locationId = $validated['centre_id'] ?? null;
        $dates = explode(' - ', $request->input('date_range'));
        $startDate = date('Y-m-d 00:00:00', strtotime($dates[0]));
        $endDate = date('Y-m-d 23:59:59', strtotime($dates[1]));
        if ($request->report_type == "stock_report") {
            // Load products with their inventories, orders, and order details
            $products = Product::with([
                'inventories' => function ($query) use ($locationId) {
                    if ($locationId) {
                        $query->where('location_id', $locationId); // Filter inventories by location
                    }
                },
                'orderDetails' => function ($query) use ($endDate) {
                    if ($endDate) {
                        $query->whereHas('order', function ($orderQuery) use ($endDate) {
                            $orderQuery->whereDate('created_at', '<=', $endDate); // Filter orders by date
                        });
                    }
                },
                'orderDetails.order.centre' // Ensure `centre` is loaded for location filtering
            ])->get();
        
            // Process the product data for the report
            $report = $products->map(function ($product) use ($locationId, $endDate) {
                // Calculate total inventory till the selected date
                $totalInventory = $product->inventories
                    ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate)) // Filter inventories by date
                    ->sum('quantity');
        
                    $soldStock = OrderDetail::where('product_id', $product->id)
                    ->whereHas('order', function ($query) use ($locationId, $endDate) {
                        if ($locationId) {
                            $query->where('location_id', $locationId);
                        }
                        if ($endDate) {
                            $query->whereDate('created_at', '<=', $endDate);
                        }
                    })
                    ->sum('quantity');
                   
        
                // Calculate remaining stock
                $remainingStock = $totalInventory - $soldStock;
        
                // Location-specific stock details
                $locationData = $product->inventories->groupBy('location_id')->map(function ($inventoryGroup, $locationId) use ($product, $endDate) {
                    $locationName = $inventoryGroup->first()->centre->name ?? 'Unknown Location'; // Fetch location name
        
                    // Total inventory for this location
                    $locationTotal = $inventoryGroup->where('created_at', '<=', $endDate)->sum('quantity');
        
                    // Sold stock at this location (make sure we filter by location_id and product_id)
                    $locationSold = $product->orderDetails
                        ->filter(function ($orderDetail) use ($locationId, $endDate, $product) {
                            $order = $orderDetail->order;
                            return $order?->location_id == $locationId &&
                                   (!$endDate || $order?->created_at <= $endDate) &&
                                   $orderDetail->product_id == $product->id; // Ensure product_id is matched
                        })
                        ->sum('quantity'); // Sum the sold quantities
        
                    $locationRemaining = $locationTotal - $locationSold;
        
                    return [
                        'location_name' => $locationName,
                        'location_id' => $locationId,
                        'total_stock' => $locationTotal,
                        'sold_stock' => $locationSold,
                        'remaining_stock' => $locationRemaining,
                    ];
                });
        
                return [
                    'product_name' => $product->name,
                    'total_inventory' => $totalInventory,
                    'sold_stock' => $soldStock,
                    'remaining_stock' => $remainingStock,
                    'locations' => $locationData,
                ];
            });
        
            return view('admin.reports.inventoryReport', compact('report'));
        }
        if($request->report_type=="doctor_sales_report"){
            $locationId = $validated['centre_id'];
            $startDate = $validated['start_date'] ?? null;
            $endDate = $validated['end_date'] ?? null;

            // Get doctors associated with the location
            $doctorIds = DB::table('doctor_has_locations')
            ->where('location_id', $locationId)
            ->pluck('user_id');

            $ordersQuery = Order::with(['doctor', 'orderDetail.product'])  // Load order details with the product
                ->whereIn('prescribed_by', $doctorIds)  // Filter orders by doctor
                ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('orders.created_at', [$startDate, $endDate]);  // Apply date range filter
                });

                $orders = $ordersQuery->get();

            // Process orders into report structure
            $report = $orders->groupBy('prescribed_by')->map(function ($doctorOrders) {
                
                $doctorName = $doctorOrders->first()->doctor->name ?? 'Unknown Doctor';
                $productSales = $doctorOrders->flatMap(function ($order) {
                    return $order->orderDetail;  // Access orderDetails (which is a collection)
                })->groupBy('product_id')->map(function ($orderDetails, $productId) {
                
                    $productName = $orderDetails->first()->product->name ?? 'Unknown Product';
                    $totalQuantity = $orderDetails->sum('quantity');  // Sum the quantities of the product sold

                    return [
                        'product_name' => $productName,
                        'total_quantity' => $totalQuantity,
                    ];
                });

                return [
                    'doctor_name' => $doctorName,
                    'product_sales' => $productSales,
                ];
            });
            return view('admin.reports.doctor_wise_sales', compact('report'));
        }
        if($request->report_type=="sales_report"){
            $dates = explode(' - ', $request->input('date_range'));
        $startDate = date('Y-m-d 00:00:00', strtotime($dates[0]));
        $endDate = date('Y-m-d 23:59:59', strtotime($dates[1]));
        // Get filters
        $locationId = $request->input('location_id');
       

        // Build query
        $query = Order::query()
            ->with(['orderDetail.product', 'centre','patients']) // Include related models
            ->when($locationId, function ($q) use ($locationId) {
                $q->where('location_id', $locationId);
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
            })->unique()->join(', '); // Join multiple product names if needed
            return [
                'order_id' => $order->id,
                'location_name' => $order->centre->name ?? 'N/A',
                'order_date' => $order->created_at,
                'total_revenue' => $totalRevenue,
                'purchased_by'=>$order->patients->name??'N/A',
                'product_name'=>$productNames??'N/A',
                'quantity'=>$quantity,
                'payment_mode'=>$order->payment_mode
            ];
        });

        // Calculate overall totals
        $overallTotal = $reportData->sum('total_revenue');

        return view('admin.reports.inventory_sales',get_defined_vars());
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
