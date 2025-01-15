<?php

namespace App\Http\Controllers;

use App\Helpers\ACL;
use App\Models\Locations;
use App\Models\Order;
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
        if($request->report_type=="stock_report"){
            $products = Product::with([
                'inventories' => function ($query) use ($locationId) {
                    if ($locationId) {
                        $query->where('location_id', $locationId);  // Apply the location filter on the inventories table
                    }
                },
                'orderDetails.order' => function ($query) use ($locationId, $startDate, $endDate) {
                    if ($locationId) {
                        $query->whereHas('centre', function ($centreQuery) use ($locationId) {
                            $centreQuery->where('location_id', $locationId);  // Ensure filtering on the `centre` table related to `location_id`
                        });
                    }
            
                    if ($startDate && $endDate) {
                        $query->whereBetween('created_at', [$startDate, $endDate]);  // Filter orders by the date range
                    }
                }
            ])->get();
            $report = $products->map(function ($product) use ($locationId) {
                // Filter inventories based on location (if provided)
                $inventories = $locationId
                    ? $product->inventories->where('location_id', $locationId)
                    : $product->inventories;
        
                $locationData = $inventories->groupBy('location_id')->map(function ($inventory, $locationId) use ($product) {
                    $locationName = $inventory->first()->centre->name ?? 'Unknown Location'; // Fetch location name
                    $totalStock = $inventory->sum('quantity');
        
                    // Calculate sold stock at this location
                    $soldStock = $product->orderDetails
                        ->where('order.location_id', $locationId) // Using related `order.location_id`
                        ->sum('quantity');
        
                    $remainingStock = $totalStock - $soldStock;
        
                    return [
                        'location_name' => $locationName,
                        'location_id' => $locationId,
                        'total_stock' => $totalStock,
                        'sold_stock' => $soldStock,
                        'remaining_stock' => $remainingStock,
                    ];
                });
        
                return [
                    'product_name' => $product->name,
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
            
    }
}
