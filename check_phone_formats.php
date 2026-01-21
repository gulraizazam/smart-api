<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Phone Number Formats in Database ===\n\n";

// Get sample of phone numbers
$samples = DB::table('leads')
    ->select('id', 'phone')
    ->whereNotNull('phone')
    ->limit(20)
    ->get();

echo "Sample phone numbers from database:\n";
foreach ($samples as $lead) {
    $startsWithZero = substr($lead->phone, 0, 1) === '0' ? 'YES' : 'NO';
    echo "ID: {$lead->id}, Phone: {$lead->phone}, Starts with 0: $startsWithZero\n";
}

echo "\n--- Checking specific phone 03026767666 ---\n";
$exact = DB::table('leads')->where('phone', '03026767666')->first();
if ($exact) {
    echo "✓ Found with exact match: 03026767666\n";
}

$without = DB::table('leads')->where('phone', '3026767666')->first();
if ($without) {
    echo "✓ Found with cleaned match: 3026767666\n";
} else {
    echo "❌ NOT found with cleaned match: 3026767666\n";
}

echo "\n=== COMPLETE ===\n";
