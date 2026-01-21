<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing API Load with Relationships ===\n\n";

$location_id = 46;
$doctor_id = 98211;
$date = '2026-01-20';
$account_id = 1;

echo "Loading appointments with relationships...\n\n";

$appointments = \App\Models\Appointments::with([
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
->get();

echo "Found " . $appointments->count() . " appointments\n\n";

if ($appointments->count() > 0) {
    echo "Checking first appointment relationships:\n";
    $first = $appointments->first();
    
    echo "  ID: {$first->id}\n";
    echo "  Patient ID: {$first->patient_id}\n";
    echo "  Service ID: {$first->service_id}\n";
    echo "  Has patient relation: " . ($first->patient ? 'YES' : 'NO') . "\n";
    echo "  Has service relation: " . ($first->service ? 'YES' : 'NO') . "\n";
    echo "  Has user relation: " . ($first->user ? 'YES' : 'NO') . "\n";
    
    if (!$first->service) {
        echo "\n  ❌ SERVICE IS NULL - This will cause error in API!\n";
        echo "  Service ID {$first->service_id} might not exist in services table\n";
    }
    
    if (!$first->patient) {
        echo "\n  ❌ PATIENT IS NULL - This will cause error in API!\n";
    }
    
    echo "\n";
    
    // Try to format like API does
    echo "Attempting to format like API:\n";
    try {
        $duration = explode(':', $first->service->duration ?? '00:00');
        echo "  Duration: " . ($first->service->duration ?? 'NULL') . "\n";
        echo "  Service name: " . ($first->service->name ?? 'NULL') . "\n";
        echo "  Patient name: " . ($first->patient->name ?? 'NULL') . "\n";
        echo "  ✓ Formatting successful\n";
    } catch (\Exception $e) {
        echo "  ❌ ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n=== COMPLETE ===\n";
