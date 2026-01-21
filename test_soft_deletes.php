<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Soft Deletes ===\n\n";

$location_id = 46;
$doctor_id = 98211;
$date = '2026-01-20';

// Check deleted_at values
echo "--- Checking deleted_at status ---\n";
$all = DB::table('appointments')
    ->where('location_id', $location_id)
    ->where('doctor_id', $doctor_id)
    ->where('scheduled_date', $date)
    ->whereNotNull('scheduled_date')
    ->whereNotNull('scheduled_time')
    ->get();

echo "Total in DB: " . $all->count() . "\n\n";

$not_deleted = 0;
$deleted = 0;
foreach ($all as $apt) {
    if ($apt->deleted_at === null) {
        $not_deleted++;
    } else {
        $deleted++;
        echo "  ID: {$apt->id} - SOFT DELETED at {$apt->deleted_at}\n";
    }
}

echo "\nNot deleted: $not_deleted\n";
echo "Soft deleted: $deleted\n";

// Test with withTrashed
echo "\n--- Using withTrashed() ---\n";
$with_trashed = \App\Models\Appointments::withTrashed()
    ->where('account_id', 1)
    ->whereNotNull('scheduled_date')
    ->whereNotNull('scheduled_time')
    ->where('location_id', $location_id)
    ->where('doctor_id', $doctor_id)
    ->where('scheduled_date', $date)
    ->get();

echo "Count with withTrashed: " . $with_trashed->count() . "\n";

echo "\n=== COMPLETE ===\n";
