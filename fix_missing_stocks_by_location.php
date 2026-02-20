<?php
/**
 * Script to find and fix missing stock records
 * Run this on live server: php fix_missing_stocks_by_location.php [location_id]
 * 
 * Examples:
 *   php fix_missing_stocks_by_location.php          # List all locations
 *   php fix_missing_stocks_by_location.php 53       # Fix location 53
 *   php fix_missing_stocks_by_location.php 53 --dry # Dry run (show SQL only, don't execute)
 *   php fix_missing_stocks_by_location.php all      # Fix all locations
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$locationId = $argv[1] ?? null;
$dryRun = in_array('--dry', $argv);

if (!$locationId) {
    // List all locations with missing stock records
    echo "=== LOCATIONS WITH MISSING STOCK RECORDS ===\n\n";
    
    $locations = DB::table('locations')->select('id', 'name')->get();
    
    echo sprintf("%-6s | %-30s | %-10s\n", "ID", "Name", "Missing");
    echo str_repeat("-", 55) . "\n";
    
    foreach ($locations as $loc) {
        $missing = countMissingRecords($loc->id);
        if ($missing > 0) {
            echo sprintf("%-6s | %-30s | %-10s\n", $loc->id, substr($loc->name, 0, 30), $missing);
        }
    }
    
    echo "\nUsage: php fix_missing_stocks_by_location.php [location_id]\n";
    echo "       php fix_missing_stocks_by_location.php [location_id] --dry\n";
    echo "       php fix_missing_stocks_by_location.php all\n";
    exit;
}

if ($locationId === 'all') {
    $locations = DB::table('locations')->pluck('id')->toArray();
} else {
    $locations = [$locationId];
}

foreach ($locations as $locId) {
    $locationName = DB::table('locations')->where('id', $locId)->value('name');
    echo "\n=== FIXING LOCATION $locId: $locationName ===\n\n";
    
    $sqlStatements = findMissingStockRecords($locId);
    
    if (empty($sqlStatements)) {
        echo "No missing records found for this location.\n";
        continue;
    }
    
    echo "Found " . count($sqlStatements) . " missing stock records.\n\n";
    
    if ($dryRun) {
        echo "=== DRY RUN - SQL to execute: ===\n";
        foreach ($sqlStatements as $sql) {
            echo $sql . "\n";
        }
    } else {
        echo "Executing SQL...\n";
        DB::beginTransaction();
        try {
            foreach ($sqlStatements as $sql) {
                DB::statement($sql);
            }
            DB::commit();
            echo "SUCCESS: " . count($sqlStatements) . " records inserted.\n";
        } catch (\Exception $e) {
            DB::rollBack();
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}

function countMissingRecords($locationId) {
    $count = 0;
    
    $inventories = DB::table('inventories')
        ->where('location_id', $locationId)
        ->get();
    
    foreach ($inventories as $inv) {
        $matchingStock = DB::table('stocks')
            ->where('product_id', $inv->product_id)
            ->where('location_id', $inv->location_id)
            ->where('stock_type', 'in')
            ->whereDate('created_at', date('Y-m-d', strtotime($inv->created_at)))
            ->exists();
        
        if (!$matchingStock) {
            $count++;
        }
    }
    
    return $count;
}

function findMissingStockRecords($locationId) {
    $sqlStatements = [];
    
    $inventories = DB::table('inventories')
        ->join('products', 'products.id', '=', 'inventories.product_id')
        ->where('inventories.location_id', $locationId)
        ->select('inventories.*', 'products.name as product_name')
        ->orderBy('inventories.created_at', 'asc')
        ->get();
    
    foreach ($inventories as $inv) {
        $matchingStock = DB::table('stocks')
            ->where('product_id', $inv->product_id)
            ->where('location_id', $inv->location_id)
            ->where('stock_type', 'in')
            ->whereDate('created_at', date('Y-m-d', strtotime($inv->created_at)))
            ->first();
        
        if (!$matchingStock) {
            // Calculate estimated initial quantity
            $salesAfterInv = DB::table('order_details')
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->where('order_details.product_id', $inv->product_id)
                ->where('orders.location_id', $inv->location_id)
                ->where('orders.created_at', '>=', $inv->created_at)
                ->sum('order_details.quantity');
            
            $stockAdditionsAfterInv = DB::table('stocks')
                ->where('product_id', $inv->product_id)
                ->where('location_id', $inv->location_id)
                ->where('stock_type', 'in')
                ->where('created_at', '>=', $inv->created_at)
                ->sum('quantity');
            
            // Estimated initial = current + sales after - additions after
            $estimatedInitial = $inv->quantity + $salesAfterInv - $stockAdditionsAfterInv;
            
            if ($estimatedInitial > 0) {
                echo "  {$inv->product_name}: Missing initial stock of $estimatedInitial (created: {$inv->created_at})\n";
                
                $sqlStatements[] = "INSERT INTO stocks (account_id, product_id, location_id, quantity, stock_type, created_at, updated_at) VALUES (1, {$inv->product_id}, {$inv->location_id}, {$estimatedInitial}, 'in', '{$inv->created_at}', '{$inv->created_at}')";
            }
        }
    }
    
    return $sqlStatements;
}
