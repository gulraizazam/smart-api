<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$searchPhone = '03026767666';

echo "=== Testing Phone: $searchPhone ===\n\n";

// Clean the phone
$cleaned = str_replace([' ', '-', '+', 'C-', 'c-'], '', $searchPhone);
$phone = ltrim($cleaned, '0');
if (substr($phone, 0, 2) === '92') {
    $phone = substr($phone, 2);
}

echo "Cleaned phone: $phone\n\n";

// Search database
$leads = DB::table('leads')
    ->select('id', 'name', 'phone', 'active', 'account_id', 'lead_status_id', 'deleted_at')
    ->where(function($q) use ($phone, $cleaned, $searchPhone) {
        $q->where('phone', $phone)
          ->orWhere('phone', $cleaned)
          ->orWhere('phone', $searchPhone);
    })
    ->get();

if ($leads->isEmpty()) {
    echo "❌ NO LEADS FOUND in database\n";
    exit;
}

echo "✓ Found " . $leads->count() . " lead(s):\n\n";

foreach ($leads as $lead) {
    echo "Lead ID: {$lead->id}\n";
    echo "  Name: {$lead->name}\n";
    echo "  Phone: {$lead->phone}\n";
    echo "  Active: {$lead->active} " . ($lead->active == 0 ? '(BOOKED/INACTIVE)' : '(ACTIVE)') . "\n";
    echo "  Account: {$lead->account_id}\n";
    echo "  Deleted: " . ($lead->deleted_at ? 'YES' : 'NO') . "\n";
    
    if ($lead->lead_status_id) {
        $status = DB::table('lead_statuses')->where('id', $lead->lead_status_id)->first();
        if ($status) {
            echo "  Status: {$status->name}\n";
        }
    }
    echo "\n";
}

// Test with getLeadidAjax
echo "--- Testing getLeadidAjax ---\n";
$account_id = $leads->first()->account_id;

$results = \App\Models\Leads::getLeadidAjax($searchPhone, $account_id);
echo "Results: " . count($results) . " lead(s)\n\n";

if (count($results) > 0) {
    foreach ($results as $lead) {
        echo "  ✓ ID: {$lead->id}, Name: {$lead->name}, Phone: {$lead->phone}\n";
    }
} else {
    echo "  ❌ NO RESULTS from getLeadidAjax\n";
}

echo "\n=== COMPLETE ===\n";
