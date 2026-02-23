<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$locationId = 56;
$startDate = '2026-02-01 00:00:00';
$endDate = '2026-02-28 23:59:59';

$product = DB::table('products')->where('name', 'like', '%Depiderm%')->first();

if (!$product) {
    echo "Product not found!\n";
    exit;
}

echo "=== Analyzing '{$product->name}' (ID: {$product->id}) at Location $locationId ===\n\n";

// Total stock IN
$totalStockIn = DB::table('stocks')
    ->where('product_id', $product->id)
    ->where('location_id', $locationId)
    ->where('stock_type', 'in')
    ->sum('quantity');

// Total sales
$totalSales = DB::table('order_details')
    ->join('orders', 'orders.id', '=', 'order_details.order_id')
    ->where('order_details.product_id', $product->id)
    ->where('orders.location_id', $locationId)
    ->sum('order_details.quantity');

// Before start date
$additionsBeforeStart = DB::table('stocks')
    ->where('product_id', $product->id)
    ->where('location_id', $locationId)
    ->where('stock_type', 'in')
    ->where('created_at', '<', $startDate)
    ->sum('quantity');

$salesBeforeStart = DB::table('order_details')
    ->join('orders', 'orders.id', '=', 'order_details.order_id')
    ->where('order_details.product_id', $product->id)
    ->where('orders.location_id', $locationId)
    ->where('orders.created_at', '<', $startDate)
    ->sum('order_details.quantity');

$openingStock = $additionsBeforeStart - $salesBeforeStart;

// In range
$additionInRange = DB::table('stocks')
    ->where('product_id', $product->id)
    ->where('location_id', $locationId)
    ->where('stock_type', 'in')
    ->whereBetween('created_at', [$startDate, $endDate])
    ->sum('quantity');

$soldInRange = DB::table('order_details')
    ->join('orders', 'orders.id', '=', 'order_details.order_id')
    ->where('order_details.product_id', $product->id)
    ->where('orders.location_id', $locationId)
    ->whereBetween('orders.created_at', [$startDate, $endDate])
    ->sum('order_details.quantity');

$remainingStock = $openingStock + $additionInRange - $soldInRange;

echo "Total Stock IN: $totalStockIn\n";
echo "Total Sales: $totalSales\n\n";
echo "Opening Stock (Feb 1): $openingStock\n";
echo "Additions in Feb: $additionInRange\n";
echo "Sales in Feb: $soldInRange\n";
echo "Remaining Stock (Feb 28): $remainingStock\n";
