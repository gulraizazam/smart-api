<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Account Filter ===\n\n";

$location_id = 46;
$doctor_id = 98211;
$date = '2026-01-20';

// Check account_id values
echo "--- Checking account_id values ---\n";
$all = DB::table('appointments')
    ->where('location_id', $location_id)
    ->where('doctor_id', $doctor_id)
    ->where('scheduled_date', $date)
    ->whereNotNull('scheduled_date')
    ->whereNotNull('scheduled_time')
    ->get();

echo "Total: " . $all->count() . "\n";
foreach ($all as $apt) {
    echo "  ID: {$apt->id}, account_id: {$apt->account_id}, deleted_at: " . ($apt->deleted_at ?? 'NULL') . "\n";
}

// Test without account_id filter
echo "\n--- Testing WITHOUT account_id filter ---\n";
$without_account = \App\Models\Appointments::withoutGlobalScopes()
    ->whereNotNull('scheduled_date')
    ->whereNotNull('scheduled_time')
    ->where('location_id', $location_id)
    ->where('doctor_id', $doctor_id)
    ->where('scheduled_date', $date)
    ->get();

echo "Count: " . $without_account->count() . "\n";

echo "\n=== COMPLETE ===\n";
