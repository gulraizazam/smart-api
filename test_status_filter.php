<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing appointment_status_id Filter ===\n\n";

$location_id = 46;
$doctor_id = 98211;
$date = '2026-01-20';
$account_id = 1;

// Check appointment_status_id values
echo "--- Checking appointment_status_id values ---\n";
$all_appointments = DB::table('appointments')
    ->where('location_id', $location_id)
    ->where('doctor_id', $doctor_id)
    ->where('scheduled_date', $date)
    ->whereNotNull('scheduled_date')
    ->whereNotNull('scheduled_time')
    ->get();

echo "Total appointments: " . $all_appointments->count() . "\n\n";

$status_counts = [];
foreach ($all_appointments as $apt) {
    $status = $apt->appointment_status_id ?? 'NULL';
    if (!isset($status_counts[$status])) {
        $status_counts[$status] = 0;
    }
    $status_counts[$status]++;
}

echo "Breakdown by appointment_status_id:\n";
foreach ($status_counts as $status => $count) {
    echo "  $status: $count appointments\n";
}

// Get cancelled status
$cancelledStatus = \App\Helpers\AppointmentHelper::getCancelledStatus($account_id);
echo "\nCancelled status ID: " . ($cancelledStatus ? $cancelledStatus->id : 'NULL') . "\n\n";

// Test with new filter
echo "--- Testing with appointment_status_id filter ---\n";
$filtered = \App\Models\Appointments::where('account_id', $account_id)
    ->whereNotNull('scheduled_date')
    ->whereNotNull('scheduled_time')
    ->where('location_id', $location_id)
    ->where('doctor_id', $doctor_id)
    ->where('scheduled_date', $date)
    ->where(function($q) use ($cancelledStatus) {
        $q->where('appointment_status_id', '!=', $cancelledStatus->id)
          ->orWhereNull('appointment_status_id');
    })
    ->get();

echo "Appointments after filter: " . $filtered->count() . "\n";
foreach ($filtered as $apt) {
    echo "  ID: {$apt->id}, appointment_status_id: " . ($apt->appointment_status_id ?? 'NULL') . "\n";
}

echo "\n=== COMPLETE ===\n";
