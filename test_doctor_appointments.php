<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Doctor Appointments ===\n\n";

$location_id = 46;
$doctor_id_1 = 98211; // Not showing
$doctor_id_2 = 187747; // Showing (from your response)
$date = '2026-01-20';

echo "Testing for location: $location_id\n";
echo "Date: $date\n\n";

// Check doctor 1 (not showing)
echo "--- Doctor ID: $doctor_id_1 ---\n";
$appointments_1 = DB::table('appointments')
    ->where('location_id', $location_id)
    ->where('doctor_id', $doctor_id_1)
    ->whereNotNull('scheduled_date')
    ->whereNotNull('scheduled_time')
    ->where('scheduled_date', $date)
    ->get();

echo "Found " . $appointments_1->count() . " appointments\n";
if ($appointments_1->count() > 0) {
    foreach ($appointments_1 as $apt) {
        echo "  ID: {$apt->id}, Patient: {$apt->patient_id}, Time: {$apt->scheduled_time}, Service: {$apt->service_id}\n";
    }
}
echo "\n";

// Check doctor 2 (showing)
echo "--- Doctor ID: $doctor_id_2 ---\n";
$appointments_2 = DB::table('appointments')
    ->where('location_id', $location_id)
    ->where('doctor_id', $doctor_id_2)
    ->whereNotNull('scheduled_date')
    ->whereNotNull('scheduled_time')
    ->where('scheduled_date', $date)
    ->get();

echo "Found " . $appointments_2->count() . " appointments\n";
if ($appointments_2->count() > 0) {
    foreach ($appointments_2 as $apt) {
        echo "  ID: {$apt->id}, Patient: {$apt->patient_id}, Time: {$apt->scheduled_time}, Service: {$apt->service_id}\n";
    }
}
echo "\n";

// Check if there's a cancelled status filter issue
echo "--- Checking Cancelled Status Filter ---\n";
$cancelledStatus = \App\Helpers\AppointmentHelper::getCancelledStatus(1);
if ($cancelledStatus) {
    echo "Cancelled status ID: {$cancelledStatus->id}\n";
    
    $cancelled_count_1 = DB::table('appointments')
        ->where('location_id', $location_id)
        ->where('doctor_id', $doctor_id_1)
        ->where('scheduled_date', $date)
        ->where('base_appointment_status_id', $cancelledStatus->id)
        ->count();
    
    echo "Doctor $doctor_id_1 - Cancelled appointments: $cancelled_count_1\n";
    
    $cancelled_count_2 = DB::table('appointments')
        ->where('location_id', $location_id)
        ->where('doctor_id', $doctor_id_2)
        ->where('scheduled_date', $date)
        ->where('base_appointment_status_id', $cancelledStatus->id)
        ->count();
    
    echo "Doctor $doctor_id_2 - Cancelled appointments: $cancelled_count_2\n";
}

echo "\n=== COMPLETE ===\n";
