<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Simulate authenticated user
Auth::loginUsingId(1);

echo "=== Testing Final API Response ===\n\n";

$location_id = 46;
$doctor_id = 98211;
$start = '2026-01-20T00:00:00';
$end = '2026-01-20T23:59:59';

$filters = [
    'location_id' => $location_id,
    'doctor_id' => $doctor_id,
    'scheduled_date_from' => \Carbon\Carbon::parse($start)->format('Y-m-d'),
    'scheduled_date_to' => \Carbon\Carbon::parse($end)->format('Y-m-d'),
];

echo "Filters:\n";
print_r($filters);
echo "\n";

// Call the service method directly
$service = new \App\Services\Appointment\AppointmentService();
$appointments = $service->getScheduledAppointments($filters);

echo "Appointments returned: " . $appointments->count() . "\n\n";

if ($appointments->count() > 0) {
    echo "Appointment IDs:\n";
    foreach ($appointments as $apt) {
        echo "  ID: {$apt->id}, Patient: {$apt->patient_id}, Service: {$apt->service_id}, Time: {$apt->scheduled_time}\n";
    }
} else {
    echo "❌ NO APPOINTMENTS RETURNED\n";
}

// Check what's in the database
echo "\n--- Database Check ---\n";
$db_count = DB::table('appointments')
    ->where('location_id', $location_id)
    ->where('doctor_id', $doctor_id)
    ->where('scheduled_date', '2026-01-20')
    ->whereNotNull('scheduled_date')
    ->whereNotNull('scheduled_time')
    ->count();

echo "Appointments in DB: $db_count\n";

echo "\n=== COMPLETE ===\n";
