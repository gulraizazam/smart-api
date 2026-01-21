<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Cancelled Status Filter ===\n\n";

$location_id = 46;
$doctor_id = 98211;
$date = '2026-01-20';
$account_id = 1;

// Get cancelled status
$cancelledStatus = \App\Helpers\AppointmentHelper::getCancelledStatus($account_id);
echo "Cancelled status ID: " . ($cancelledStatus ? $cancelledStatus->id : 'NULL') . "\n\n";

// Query WITHOUT cancelled filter
echo "--- WITHOUT cancelled filter ---\n";
$without_filter = \App\Models\Appointments::where('account_id', $account_id)
    ->whereNotNull('scheduled_date')
    ->whereNotNull('scheduled_time')
    ->where('location_id', $location_id)
    ->where('doctor_id', $doctor_id)
    ->where('scheduled_date', $date)
    ->get();

echo "Found: " . $without_filter->count() . " appointments\n";
foreach ($without_filter as $apt) {
    echo "  ID: {$apt->id}, base_appointment_status_id: {$apt->base_appointment_status_id}\n";
}

// Query WITH cancelled filter (like the service does)
echo "\n--- WITH cancelled filter (base_appointment_status_id != {$cancelledStatus->id}) ---\n";
$with_filter = \App\Models\Appointments::where('account_id', $account_id)
    ->whereNotNull('scheduled_date')
    ->whereNotNull('scheduled_time')
    ->where('location_id', $location_id)
    ->where('doctor_id', $doctor_id)
    ->where('scheduled_date', $date)
    ->where('base_appointment_status_id', '!=', $cancelledStatus->id)
    ->get();

echo "Found: " . $with_filter->count() . " appointments\n";
foreach ($with_filter as $apt) {
    echo "  ID: {$apt->id}, base_appointment_status_id: {$apt->base_appointment_status_id}\n";
}

// Check which appointments are being filtered out
echo "\n--- Filtered OUT appointments ---\n";
$filtered_out = $without_filter->diff($with_filter);
echo "Filtered out: " . $filtered_out->count() . " appointments\n";
foreach ($filtered_out as $apt) {
    echo "  ID: {$apt->id}, base_appointment_status_id: {$apt->base_appointment_status_id} (Cancelled)\n";
}

echo "\n=== COMPLETE ===\n";
