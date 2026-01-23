<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing SQL Query ===\n\n";

$location_id = 46;
$doctor_id = 98211;
$date = '2026-01-20';
$account_id = 1;

$cancelledStatus = \App\Helpers\AppointmentHelper::getCancelledStatus($account_id);
echo "Cancelled status ID: {$cancelledStatus->id}\n\n";

// Build the query
$query = \App\Models\Appointments::where('account_id', $account_id)
    ->whereNotNull('scheduled_date')
    ->whereNotNull('scheduled_time')
    ->where('location_id', $location_id)
    ->where('doctor_id', $doctor_id)
    ->where('scheduled_date', $date)
    ->where(function($q) use ($cancelledStatus) {
        $q->where('appointment_status_id', '!=', $cancelledStatus->id)
          ->orWhereNull('appointment_status_id');
    });

// Get the SQL
echo "Generated SQL:\n";
echo $query->toSql() . "\n\n";

echo "Bindings:\n";
print_r($query->getBindings());
echo "\n";

// Execute and count
$results = $query->get();
echo "Results: " . $results->count() . " appointments\n\n";

// Now test without the cancelled filter
echo "--- Testing WITHOUT cancelled filter ---\n";
$query2 = \App\Models\Appointments::where('account_id', $account_id)
    ->whereNotNull('scheduled_date')
    ->whereNotNull('scheduled_time')
    ->where('location_id', $location_id)
    ->where('doctor_id', $doctor_id)
    ->where('scheduled_date', $date);

echo "SQL: " . $query2->toSql() . "\n";
$results2 = $query2->get();
echo "Results: " . $results2->count() . " appointments\n";

echo "\n=== COMPLETE ===\n";
