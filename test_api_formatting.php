<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

Auth::loginUsingId(1);

echo "=== Testing API Formatting ===\n\n";

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

$service = new \App\Services\Appointment\AppointmentService();
$appointments = $service->getScheduledAppointments($filters);

echo "Total appointments: " . $appointments->count() . "\n\n";

// Try to format like the API does
$events = [];
$errors = [];

foreach ($appointments as $appointment) {
    try {
        $duration = explode(':', $appointment->service->duration ?? '00:00');
        $events[$appointment->id] = [
            'id' => $appointment->id,
            'service' => $appointment->service->name ?? '',
            'patient' => $appointment->name ?: ($appointment->patient->name ?? ''),
            'created_by' => $appointment->user->name ?? '',
            'duration' => $appointment->service->duration ?? '00:00',
            'start' => \Carbon\Carbon::parse($appointment->scheduled_date)->format('Y-m-d') . ' ' . \Carbon\Carbon::parse($appointment->scheduled_time)->format('H:i'),
            'end' => \Carbon\Carbon::parse($appointment->scheduled_date)->format('Y-m-d') . ' ' . \Carbon\Carbon::parse($appointment->scheduled_time)->addHours($duration[0] ?? 0)->addMinutes($duration[1] ?? 0)->format('H:i'),
            'color' => $appointment->service->color ?? '#fff',
            'resourceId' => $appointment->doctor_id,
        ];
        echo "✓ ID {$appointment->id} formatted successfully\n";
    } catch (\Exception $e) {
        $errors[] = [
            'id' => $appointment->id,
            'error' => $e->getMessage(),
            'service_id' => $appointment->service_id,
            'patient_id' => $appointment->patient_id,
        ];
        echo "❌ ID {$appointment->id} - ERROR: {$e->getMessage()}\n";
    }
}

echo "\nSuccessfully formatted: " . count($events) . " events\n";
echo "Errors: " . count($errors) . "\n";

if (count($errors) > 0) {
    echo "\nError details:\n";
    foreach ($errors as $error) {
        echo "  ID {$error['id']}: {$error['error']}\n";
        echo "    Service ID: {$error['service_id']}, Patient ID: {$error['patient_id']}\n";
    }
}

echo "\n=== COMPLETE ===\n";
