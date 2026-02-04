<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TicketStatus;
use App\Models\User;
use App\Models\Branch;

echo "Testing ticket creation logic...\n\n";

// Test 1: Check if statuses exist
echo "1. Checking ticket statuses:\n";
$statuses = TicketStatus::all();
echo "Found " . $statuses->count() . " statuses\n";

$pendingStatus = TicketStatus::where('name', 'Pending')->first();
echo "Pending status: " . ($pendingStatus ? "Found (ID: {$pendingStatus->id})" : "NOT FOUND") . "\n";

$defaultStatus = TicketStatus::getDefault();
echo "Default status: " . ($defaultStatus ? "Found (ID: {$defaultStatus->id}, Name: {$defaultStatus->name})" : "NOT FOUND") . "\n";

$firstStatus = TicketStatus::first();
echo "First status: " . ($firstStatus ? "Found (ID: {$firstStatus->id}, Name: {$firstStatus->name})" : "NOT FOUND") . "\n";

// Test 2: Simulate the status assignment logic from TicketController
echo "\n2. Simulating status assignment logic:\n";

$data = [];

// Always try to start customer tickets in a clear "Pending" state
$pendingStatus = TicketStatus::where('name', 'Pending')->first();
if ($pendingStatus) {
    $data['status_id'] = $pendingStatus->id;
    echo "✓ Assigned Pending status (ID: {$pendingStatus->id})\n";
} else {
    $defaultStatus = TicketStatus::getDefault();
    $data['status_id'] = $defaultStatus ? $defaultStatus->id : TicketStatus::first()?->id;
    if ($defaultStatus) {
        echo "✓ Assigned default status (ID: {$defaultStatus->id}, Name: {$defaultStatus->name})\n";
    } elseif (TicketStatus::first()) {
        $first = TicketStatus::first();
        echo "✓ Assigned first available status (ID: {$first->id}, Name: {$first->name})\n";
    } else {
        echo "✗ NO STATUS FOUND - This would cause the error!\n";
    }
}

if (!$data['status_id']) {
    echo "✗ ERROR: No status_id assigned - this would trigger the 'No ticket status configured' error\n";
} else {
    echo "✓ Status assignment successful: status_id = {$data['status_id']}\n";
}

// Test 3: Check branches
echo "\n3. Checking branches:\n";
$branches = Branch::all();
echo "Found " . $branches->count() . " branches\n";
if ($branches->count() > 0) {
    foreach ($branches as $branch) {
        echo "- {$branch->name} (ID: {$branch->id}, Active: " . ($branch->is_active ? 'Yes' : 'No') . ")\n";
    }
}

// Test 4: Check if there are any users
echo "\n4. Checking users:\n";
$users = User::count();
echo "Found {$users} users\n";

echo "\nTest completed.\n";