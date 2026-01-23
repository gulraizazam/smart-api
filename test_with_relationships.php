<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing With Relationships ===\n\n";

$location_id = 46;
$doctor_id = 98211;
$date = '2026-01-20';
$account_id = 1;

$cancelledStatus = \App\Helpers\AppointmentHelper::getCancelledStatus($account_id);

// Test WITHOUT relationships
echo "--- WITHOUT relationships ---\n";
$without = \App\Models\Appointments::where('account_id', $account_id)
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

echo "Count: " . $without->count() . "\n";
foreach ($without as $apt) {
    echo "  ID: {$apt->id}\n";
}

// Test WITH relationships (like the service does)
echo "\n--- WITH relationships ---\n";
$with = \App\Models\Appointments::with([
    'appointment_type',
    'appointment_status',
    'service',
    'location',
    'doctor',
    'patient',
    'resource'
])
->where('account_id', $account_id)
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

echo "Count: " . $with->count() . "\n";
foreach ($with as $apt) {
    $has_service = $apt->service ? 'YES' : 'NO';
    $has_patient = $apt->patient ? 'YES' : 'NO';
    echo "  ID: {$apt->id}, Service: $has_service, Patient: $has_patient\n";
}

// Check which appointments are missing relationships
echo "\n--- Checking missing relationships ---\n";
foreach ($without as $apt) {
    $full = \App\Models\Appointments::with(['service', 'patient'])->find($apt->id);
    if (!$full->service || !$full->patient) {
        echo "  ID: {$apt->id} - Missing: ";
        if (!$full->service) echo "service ";
        if (!$full->patient) echo "patient ";
        echo "\n";
    }
}

echo "\n=== COMPLETE ===\n";
